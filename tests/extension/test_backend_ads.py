"""Tes unit log_ad_event / describe_ad_status di backend.py (event iklan v3).
Jalankan: python3 tests/extension/test_backend_ads.py
"""
import importlib.util
import os
import shutil
import sys
import tempfile
import unittest

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))


def load_backend():
    tmp = tempfile.mkdtemp()
    shutil.copy(os.path.join(ROOT, "backend.py"), tmp)
    spec = importlib.util.spec_from_file_location("backend_under_test", os.path.join(tmp, "backend.py"))
    m = importlib.util.module_from_spec(spec)
    sys.modules["backend_under_test"] = m
    spec.loader.exec_module(m)
    return m, tmp


class AdEventTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.m, cls.tmp = load_backend()

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(cls.tmp, ignore_errors=True)

    def setUp(self):
        m = self.m
        m.state = m.BackendState(None)
        self.logs = []
        m.state.emit = lambda kind, data=None: self.logs.append((kind, data or {}))
        self.ws = object()
        m.state.clients[self.ws] = {"label": "Uji", "instance_id": "i1"}
        self.meta = m.state.clients[self.ws]

    def fire(self, event, **kw):
        self.m.log_ad_event(self.ws, {"event": event, **kw})
        return self.logs[-1][1] if self.logs else None

    def test_decision_play_full_and_skip_text(self):
        self.fire("adDetected", index=1, kind="short", durationSec=5, skippable=False)
        d = self.fire("adDecision", index=1, kind="short", durationSec=5, action="play_full", thresholdSeconds=10)
        self.assertIn("diputar sampai selesai", d["text"])
        self.assertIn("10 detik", d["text"])
        self.fire("adEnded", index=1, skipped=False, durationMs=5000)
        self.fire("adDetected", index=2, kind="long", durationSec=180)
        d = self.fire("adDecision", index=2, kind="long", durationSec=180, action="skip", thresholdSeconds=10)
        self.assertIn("di-skip pada detik ke-11", d["text"])

    def test_decision_assumed(self):
        self.fire("adDetected", index=1)
        d = self.fire("adDecision", index=1, action="skip", thresholdSeconds=10, assumed=True)
        self.assertIn("durasi tidak terbaca", d["text"])

    def test_waiting_sets_phase(self):
        self.fire("adDetected", index=2, kind="long", durationSec=180)
        d = self.fire("adWaiting", index=2, kind="long", waitSeconds=11)
        self.assertIn("detik ke-11", d["text"])
        self.assertIn("menunggu", self.meta["ad_phase"])
        self.assertIn("sedang iklan ke-2", self.m.describe_ad_status(self.meta))

    def test_force_reload_logs_and_clears_active(self):
        self.fire("adDetected", index=2, kind="long", durationSec=180)
        d = self.fire("adForceReload", index=2, kind="long", durationSec=180,
                      reason="tanpa-tombol-skip", attempt=1, max=2)
        self.assertEqual(d["level"], "WARNING")
        self.assertIn("tidak punya tombol Skip", d["text"])
        self.assertIn("muat ulang 1/2", d["text"])
        self.assertEqual(self.meta["ads_reloaded"], 1)
        # halaman dimuat ulang -> tidak boleh tertinggal "sedang iklan"
        self.assertFalse(self.meta["ad_active"])
        status = self.m.describe_ad_status(self.meta)
        self.assertTrue(status.startswith("tidak ada iklan"))
        self.assertIn("dimuat ulang 1", status)

    def test_force_reload_reasons(self):
        for reason, frag in [("tombol-nonaktif", "tidak aktif"), ("skip-gagal", "tidak berhasil"), ("lain", "lain")]:
            d = self.fire("adForceReload", index=1, reason=reason, attempt=1, max=2)
            self.assertIn(frag, d["text"])
        self.assertEqual(self.meta["ads_reloaded"], 3)

    def test_reload_limit_keeps_ad_active(self):
        self.fire("adDetected", index=3, kind="long", durationSec=180)
        d = self.fire("adReloadLimit", index=3, reason="tanpa-tombol-skip", max=2)
        self.assertEqual(d["level"], "WARNING")
        self.assertIn("2x", d["text"])
        self.assertTrue(self.meta["ad_active"])
        self.assertNotIn("ads_reloaded", self.meta)
        self.fire("adEnded", index=3, skipped=False, durationMs=60000)
        self.assertFalse(self.meta["ad_active"])
        self.assertEqual(self.meta["ads_played_full"], 1)

    def test_skip_failed_text_mentions_reload(self):
        self.fire("adDetected", index=1)
        d = self.fire("adSkipFailed", index=1, attempts=3)
        self.assertIn("3 percobaan", d["text"])
        self.assertNotIn("dibiarkan sampai selesai", d["text"])
        self.assertIn("URL lagu playlist", d["text"])

    def test_skip_clicked_attempt_and_ended_skipped(self):
        self.fire("adDetected", index=1, kind="long", durationSec=60)
        d = self.fire("adSkipClicked", index=1, attempt=2, waitedMs=11200)
        self.assertIn("percobaan ke-2", d["text"])
        self.fire("adEnded", index=1, skipped=True, durationMs=12000)
        self.assertEqual(self.meta["ads_skipped"], 1)

    def test_after_song_and_legacy_unskippable_and_unknown(self):
        d = self.fire("adAfterSong", index=0, total=3)
        self.assertIn("lagu berikutnya", d["text"])
        self.fire("adUnskippable", index=1)
        self.assertIn("tidak punya tombol Skip", self.logs[-1][1]["text"])
        n = len(self.logs)
        self.fire("eventTidakDikenal")
        self.assertEqual(len(self.logs), n)

    def test_web_session_holder(self):
        m = self.m
        meta = m.state.clients[self.ws]
        meta["browser_profile_id"] = "P1"
        m.state.web_sessions["P1"] = {}
        self.fire("adDetected", index=1, kind="long", durationSec=100)
        self.fire("adForceReload", index=1, reason="skip-gagal", attempt=1, max=2)
        self.assertEqual(m.state.web_sessions["P1"]["ads_reloaded"], 1)
        self.assertIn("Dolphin P1", self.logs[-1][1]["text"])
        self.assertNotIn("ads_reloaded", meta)

    def test_ad_events_set(self):
        for name in ("adForceReload", "adReloadLimit", "adWaiting", "adDecision"):
            self.assertIn(name, self.m.AD_EVENTS)


if __name__ == "__main__":
    unittest.main(verbosity=2)
