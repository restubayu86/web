"""
BayzBot - Control Panel (GUI + backend WebSocket server)

Backend NYATA untuk ekstensi Chrome BayzBot. Menjalankan server
WebSocket (default ws://localhost:8765) SEKALIGUS jendela GUI kontrol.

Fitur:
    - LOGIN / LOGOUT APLIKASI -> gerbang akses ke Control Panel ini
      sendiri (username + password lokal, di-hash+salt, tidak pernah
      disimpan plaintext). Ini BUKAN login akun Google/YouTube Music.
    - "Buka Login Google" / "Buka Logout Google" (tombol terpisah) ->
      membawa tab YouTube Music ke depan (fokus) ke halaman login/logout
      Google supaya user bisa login manual di sana. BayzBot TIDAK PERNAH
      mengetik/menyimpan password Google.
    - Load & Refresh playlist.txt -> satu URL lagu YouTube Music per baris
      (bukan URL playlist), mis. https://music.youtube.com/watch?v=xxxx
    - Play / Restart / Next / Previous / Stop playlist.
      "Next"/"Previous" = pindah URL di ANTRIAN KITA, BUKAN tombol next
      bawaan YouTube.
    - Show Playlist -> jendela terpisah: judul, artis, album, jumlah
      diputar, status tiap URL di antrian.
    - Status jumlah putar playlist (loop counter) & status RAM (%).
    - Auto run -> otomatis mulai memutar playlist begitu ekstensi konek.
    - Halaman Option -> Total loop (bisa infinity), interval watchdog,
      jeda skip iklan (detik), on/off notifikasi Telegram, token & chat ID,
      ganti password aplikasi.
    - Notifikasi Telegram: lagu pertama diputar, pindah lagu, playlist
      stop, error/putus koneksi, instance ditutup, dan RAM > 85% (paksa,
      selalu terkirim walau notifikasi Telegram di-nonaktifkan).

Jalankan:
    pip install websockets
    python backend_gui.py

Backend berjalan tanpa jendela Control Panel secara default.
Gunakan `python backend_gui.py --gui` untuk membuka Control Panel.
"""

from __future__ import annotations

import asyncio
import ctypes
import hmac
import hashlib
import json
import os
import re
import secrets
import sys
import traceback
import websockets
import queue
import random
import time
import threading
from concurrent.futures import ThreadPoolExecutor
import urllib.request
import urllib.parse
import urllib.error
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from datetime import datetime

import tkinter as tk
from tkinter import ttk, filedialog, messagebox

if sys.stdout and hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass
if sys.stderr and hasattr(sys.stderr, 'reconfigure'):
    try:
        sys.stderr.reconfigure(encoding='utf-8')
    except Exception:
        pass

# ---------------------------------------------------------------------------
# Konfigurasi
# ---------------------------------------------------------------------------

CONFIG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "gui_config.json")
HOST = "localhost"
DEFAULT_PORT = 8765
HTTP_API_PORT = 8766


def get_ws_port() -> int:
    """Urutan prioritas: argumen CLI --port=XXXX > gui_config.json > default.
    Berguna untuk menjalankan BEBERAPA salinan backend_gui.py sekaligus
    (mis. simulasi banyak user Chrome Web Store) -- tiap salinan dikasih
    port sendiri-sendiri supaya tidak saling tabrakan/nyambung ke
    instance Chrome yang salah."""
    for arg in sys.argv[1:]:
        if arg.startswith("--port="):
            try:
                return int(arg.split("=", 1)[1])
            except ValueError:
                pass
    if state is not None:
        try:
            return int(state.config.get("ws_port", DEFAULT_PORT))
        except (TypeError, ValueError):
            pass
    return DEFAULT_PORT


def get_http_api_port() -> int:
    try:
        return int(state.config.get("http_api_port", HTTP_API_PORT))
    except (TypeError, ValueError):
        return HTTP_API_PORT


def is_port_available(port: int, host: str = "localhost") -> bool:
    """Cek apakah port sudah terpakai."""
    import socket
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(1)
        result = sock.connect_ex((host, port))
        sock.close()
        return result != 0
    except Exception:
        return True

RAM_ALERT_THRESHOLD = 85.0     # % — notifikasi Telegram PAKSA kalau lewat ini
RAM_CHECK_INTERVAL_SECONDS = 20
MAX_WEB_RECOVERY_ATTEMPTS = 3
TELEGRAM_EXECUTOR = ThreadPoolExecutor(max_workers=2, thread_name_prefix="bayzbot-telegram")

DEFAULT_CONFIG = {
    "total_loop": -1,               # -1 = infinity
    "watchdog_interval": 30,        # detik
    "ad_skip_delay_seconds": 0,     # 0 = skip iklan langsung begitu terlihat
    "ws_port": 8765,                # ganti kalau jalankan >1 backend sekaligus di 1 PC
    "http_api_port": HTTP_API_PORT,
    "http_api_token": "",
    "telegram_enabled": True,
    "telegram_bot_token": "",
    "telegram_chat_id": "",
    "auto_run": False,
    "shuffle_playlist": True,       # putar playlist dengan urutan acak (diacak ulang tiap loop)
    "last_playlist_path": "",
    # Proxy settings
    "use_proxy": False,
    "proxy_url": "",                # contoh: http://proxy.example.com:8080
    # Login APLIKASI (BUKAN akun Google) — password disimpan sebagai hash
    # PBKDF2-SHA256 + salt acak, tidak pernah plaintext.
    "auth_username": "",
    "auth_password_hash": "",
    "auth_password_salt": "",
}


def load_config() -> dict:
    if os.path.isfile(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                data = json.load(f)
            merged = dict(DEFAULT_CONFIG)
            merged.update(data)
            if not merged.get("http_api_token"):
                merged["http_api_token"] = secrets.token_urlsafe(32)
                save_config(merged)
            return merged
        except Exception:
            pass
    config = dict(DEFAULT_CONFIG)
    config["http_api_token"] = secrets.token_urlsafe(32)
    save_config(config)
    return config


def save_config(cfg: dict) -> None:
    try:
        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(cfg, f, indent=2, ensure_ascii=False)
    except Exception as e:
        print(f"Gagal menyimpan config: {e}")

# ---------------------------------------------------------------------------
# IP Geolocation & Country Flags
# ---------------------------------------------------------------------------

_ip_cache = {}
_country_flags = {
    "US": "🇺🇸", "GB": "🇬🇧", "DE": "🇩🇪", "FR": "🇫🇷", "JP": "🇯🇵",
    "CN": "🇨🇳", "IN": "🇮🇳", "BR": "🇧🇷", "RU": "🇷🇺", "AU": "🇦🇺",
    "CA": "🇨🇦", "MX": "🇲🇽", "SG": "🇸🇬", "HK": "🇭🇰", "KR": "🇰🇷",
    "ID": "🇮🇩", "TH": "🇹🇭", "MY": "🇲🇾", "PH": "🇵🇭", "VN": "🇻🇳",
}

def get_country_flag(country_code: str) -> str:
    """Dapatkan emoji flag dari country code (contoh: 'US' -> '🇺🇸')."""
    return _country_flags.get(country_code.upper(), "🌐")

def get_public_ip() -> tuple[str | None, str | None]:
    """Dapatkan public IP dan country code. Return (IP, country_code) atau (None, None) kalau gagal."""
    if "ip_data" in _ip_cache:
        return _ip_cache["ip_data"]
    
    try:
        req = urllib.request.Request(
            "http://ip-api.com/json/",
            headers={"User-Agent": "BayzBot/1.0"}
        )
        with urllib.request.urlopen(req, timeout=5) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            ip = data.get("query")
            country = data.get("countryCode")
            result = (ip, country)
            _ip_cache["ip_data"] = result
            return result
    except Exception:
        pass
    
    return (None, None)

def get_ip_display() -> str:
    """Dapatkan display string untuk IP dengan country flag (contoh: '1.2.3.4 🇺🇸 (US)')."""
    ip, country = get_public_ip()
    if ip and country:
        flag = get_country_flag(country)
        return f"{ip} {flag} ({country})"
    elif ip:
        return ip
    return "IP tidak terdeteksi"

def fetch_youtube_title(url: str) -> dict | None:
    """Fetch YouTube video title dan artist dari oEmbed API. Return {'title', 'artist'} atau None."""
    try:
        oembed_url = f"https://www.youtube.com/oembed?url={urllib.parse.quote(url)}&format=json"
        req = urllib.request.Request(oembed_url)
        with urllib.request.urlopen(req, timeout=5) as res:
            data = json.loads(res.read().decode('utf-8'))
            return {
                "title": data.get("title", ""),
                "artist": data.get("author_name", "")
            }
    except Exception:
        return None

# ---------------------------------------------------------------------------
# Telegram
# ---------------------------------------------------------------------------

def send_telegram_message(token: str, chat_id: str, text: str) -> bool:
    """Kirim pesan Telegram; panggilan ini dijalankan di thread worker."""
    if not token or not chat_id:
        return False
    try:
        url = f"https://api.telegram.org/bot{token}/sendMessage"
        data = urllib.parse.urlencode({"chat_id": chat_id, "text": text}).encode("utf-8")
        req = urllib.request.Request(url, data=data, method="POST")
        with urllib.request.urlopen(req, timeout=10) as resp:
            return resp.status == 200
    except Exception as exc:
        print(f"[Telegram] Gagal mengirim notifikasi: {exc}", flush=True)
        return False


def notify_telegram(text: str, force: bool = False) -> None:
    """Jadwalkan notifikasi agar request jaringan tidak memblokir event loop."""
    if not force and not state.config.get("telegram_enabled", True):
        return
    token = state.config.get("telegram_bot_token", "")
    chat_id = state.config.get("telegram_chat_id", "")
    if not token or not chat_id:
        return
    try:
        future = TELEGRAM_EXECUTOR.submit(send_telegram_message, token, chat_id, text)
    except RuntimeError as exc:
        print(f"[Telegram] Notifikasi tidak dapat dijadwalkan: {exc}", flush=True)
        return

    def report_failure(result):
        try:
            if not result.result():
                print("[Telegram] Notifikasi tidak terkirim.", flush=True)
        except Exception as exc:
            print(f"[Telegram] Pengiriman notifikasi gagal: {exc}", flush=True)

    future.add_done_callback(report_failure)


def telegram_polling():
    """Thread worker untuk polling perintah Telegram (long-polling getUpdates)."""
    offset = 0
    while True:
        try:
            cfg = load_config()
            token = (cfg.get("telegram_bot_token") or "").strip()
            chat_id_cfg = str(cfg.get("telegram_chat_id") or "").strip()
            if not token or not chat_id_cfg:
                time.sleep(10)
                continue

            url = f"https://api.telegram.org/bot{token}/getUpdates?timeout=30"
            if offset:
                url += f"&offset={offset}"
            with urllib.request.urlopen(url, timeout=40) as resp:
                data = json.loads(resp.read().decode("utf-8"))
                if not data.get("ok"):
                    time.sleep(5)
                    continue
                for update in data.get("result", []):
                    try:
                        offset = int(update.get("update_id", 0)) + 1
                    except Exception:
                        offset = 0
                    msg = update.get("message") or update.get("edited_message") or {}
                    text = (msg.get("text") or "").strip()
                    if not text.startswith("/"):
                        continue
                    sender_chat_id = str((msg.get("chat") or {}).get("id", "")).strip()
                    if sender_chat_id != chat_id_cfg:
                        continue
                    cmd = text.split()[0].lower().split("@")[0]
                    response = None
                    if cmd == "/status":
                        ram = get_ram_percent()
                        ram_str = f"{ram:.1f}%" if ram is not None else "N/A"
                        gui_state = "Running" if state.is_running else "Stopped"
                        web_active = sum(1 for s in state.web_sessions.values() if s.get("active"))
                        clients = len(state.clients)
                        response = (
                            f"BayzBot Status: OK\n"
                            f"RAM: {ram_str}\n"
                            f"GUI Stream: {gui_state} (loop {state.loop_count})\n"
                            f"Web Sessions Active: {web_active}\n"
                            f"Instances Connected: {clients}"
                        )
                    elif cmd == "/ram":
                        ram = get_ram_percent()
                        ram_str = f"{ram:.1f}%" if ram is not None else "N/A"
                        response = f"RAM Usage: {ram_str}"
                    elif cmd in ("/stopstream", "/stop"):
                        stopped = 0
                        if state.is_running:
                            state.is_running = False
                            stopped += 1
                        for sid, sess in list(state.web_sessions.items()):
                            if sess.get("active"):
                                sess["active"] = False
                                stopped += 1
                        if stopped:
                            response = f"Stream dihentikan ({stopped} sesi)."
                        else:
                            response = "Tidak ada stream aktif."
                    elif cmd == "/ping":
                        response = "Pong! BayzBot aktif."
                    elif cmd == "/playlist":
                        total = len(state.playlist_urls)
                        idx = state.playlist_index
                        cur = state.play_order[idx] if 0 <= idx < len(state.play_order) else "-"
                        response = f"Playlist: {total} lagu\nIndex: {idx + 1 if idx >= 0 else 0}/{total}\nCurrent: {cur}"
                    elif cmd == "/loops":
                        response = f"Loop count: {state.loop_count}"
                    elif cmd == "/clients":
                        clients = len(state.clients)
                        response = f"Instances connected: {clients}"
                        if clients:
                            for meta in state.clients.values():
                                label = meta.get("label") or meta.get("instance_id") or "unknown"
                                response += f"\n- {label}"
                    elif cmd in ("/help", "/start"):
                        response = (
                            "Perintah tersedia:\n"
                            "/status - Status bot & stream\n"
                            "/ram - Status RAM\n"
                            "/stopstream /stop - Hentikan semua stream\n"
                            "/playlist - Info playlist\n"
                            "/loops - Info loop count\n"
                            "/clients - Daftar instance terhubung\n"
                            "/ping - Cek bot aktif\n"
                            "/help - Bantuan"
                        )
                    else:
                        response = "Perintah tidak dikenal. Coba /help"
                    if response:
                        send_telegram_message(token, sender_chat_id, response)
        except Exception as e:
            print(f"[Telegram Polling] Error: {e}", flush=True)
            try:
                traceback.print_exc()
            except Exception:
                pass
            time.sleep(5)


# ---------------------------------------------------------------------------
# Login APLIKASI (BUKAN akun Google) — hash password lokal
# ---------------------------------------------------------------------------

def hash_password(password: str, salt: bytes) -> str:
    return hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"), salt, 100_000).hex()


def verify_password(password: str, salt_hex: str, hash_hex: str) -> bool:
    try:
        salt = bytes.fromhex(salt_hex)
    except Exception:
        return False
    if not salt_hex or not hash_hex:
        return False
    return hash_password(password, salt) == hash_hex


def has_app_account() -> bool:
    return bool(state.config.get("auth_username") and state.config.get("auth_password_hash"))


# ---------------------------------------------------------------------------
# RAM
# ---------------------------------------------------------------------------

def get_ram_percent() -> float | None:
    """Persentase RAM terpakai. Windows: via ctypes (tanpa dependensi
    tambahan). Fallback ke psutil kalau ada & bukan Windows."""
    try:
        class MEMORYSTATUSEX(ctypes.Structure):
            _fields_ = [
                ("dwLength", ctypes.c_ulong),
                ("dwMemoryLoad", ctypes.c_ulong),
                ("ullTotalPhys", ctypes.c_ulonglong),
                ("ullAvailPhys", ctypes.c_ulonglong),
                ("ullTotalPageFile", ctypes.c_ulonglong),
                ("ullAvailPageFile", ctypes.c_ulonglong),
                ("ullTotalVirtual", ctypes.c_ulonglong),
                ("ullAvailVirtual", ctypes.c_ulonglong),
                ("sullAvailExtendedVirtual", ctypes.c_ulonglong),
            ]

        stat = MEMORYSTATUSEX()
        stat.dwLength = ctypes.sizeof(MEMORYSTATUSEX)
        ctypes.windll.kernel32.GlobalMemoryStatusEx(ctypes.byref(stat))  # type: ignore[attr-defined]
        return float(stat.dwMemoryLoad)
    except Exception:
        pass
    try:
        import psutil  # opsional, hanya dipakai sebagai fallback non-Windows
        return psutil.virtual_memory().percent
    except Exception:
        return None


# ---------------------------------------------------------------------------
# Backend state (dipakai bersama antara thread asyncio & thread GUI)
# ---------------------------------------------------------------------------

class BackendState:
    def __init__(self, event_queue: "queue.Queue | None"):
        self.event_queue = event_queue
        self.loop: asyncio.AbstractEventLoop | None = None
        # clients: {websocket: {"instance_id": str|None, "label": str|None,
        #                        "connected_at": float}}
        # PENTING: dict per-koneksi, BUKAN set polos lagi -- supaya kita
        # bisa tahu websocket mana representasi instance Chrome yang mana,
        # dan mengirim perintah ke SATU instance yang dipilih user, bukan
        # broadcast ke semua (itu penyebab "GUI kadang memutar ke instance
        # yang salah" saat beberapa Chrome dites bersamaan).
        self.clients: dict = {}
        self.selected_instance_id: str | None = None
        self.pending_commands: dict[str, asyncio.Future] = {}
        self.web_sessions: dict[str, dict] = {}

        self.playlist_urls: list[str] = []
        # play_order = urutan yang BENAR-BENAR dikirim ke ekstensi (hasil
        # shuffle kalau mode acak aktif). playlist_urls tetap urutan asli file.
        self.play_order: list[str] = []
        self.playlist_path: str = ""
        self.playlist_index: int = -1
        self.is_running: bool = False
        self.loop_count: int = 0

        # song_stats[url] = {"title","artist","album","play_count","status"}
        self.song_stats: dict = {}
        self.pending_notification: dict | None = None

        self.current_status: dict = {}
        self.last_status_time: float = 0.0

        self.config: dict = load_config()

    def emit(self, kind: str, data: dict | None = None):
        """Kirim event ke GUI thread lewat queue (thread-safe)."""
        if self.event_queue is not None:
            self.event_queue.put({"kind": kind, "data": data or {}, "ts": time.time()})
        elif kind == "log":
            entry = data or {}
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            level = entry.get("level", "INFO")
            text = entry.get("text", "")
            # Gunakan encoding utf-8 untuk menghindari error karakter khusus
            try:
                print(f"{timestamp} [{level}] {text}", flush=True)
            except UnicodeEncodeError:
                # Fallback: encode ke ascii dengan replace, atau strip karakter khusus
                safe_text = text.encode("ascii", errors="replace").decode("ascii")
                print(f"{timestamp} [{level}] {safe_text}", flush=True)

    def active_order(self) -> list[str]:
        """Urutan putar yang sedang dipakai (acak/asli). Fallback ke urutan
        file kalau belum ada sesi yang berjalan."""
        return self.play_order if self.play_order else self.playlist_urls

    def emit_playlist_stats(self):
        self.emit("playlist_stats_changed", {
            "playlist_urls": list(self.active_order()),
            "song_stats": dict(self.song_stats),
            "index": self.playlist_index,
        })

    def emit_clients_changed(self):
        """Kirim daftar instance yang sedang tersambung ke GUI thread,
        supaya dropdown pemilih instance selalu up to date."""
        clients_info = [
            {
                "instance_id": meta.get("instance_id"),
                "label": meta.get("label"),
                "connected_at": meta.get("connected_at"),
                "instance_ip": meta.get("instance_ip"),
                "instance_country": meta.get("instance_country"),
                "proxy_enabled": meta.get("proxy_enabled", False),
            }
            for meta in self.clients.values()
        ]
        self.emit("clients_changed", {
            "clients": clients_info,
            "selected_instance_id": self.selected_instance_id,
        })


state: BackendState = None  # diisi di main()


# ---------------------------------------------------------------------------
# WebSocket server (jalan di thread terpisah, event loop asyncio sendiri)
# ---------------------------------------------------------------------------

async def broadcast_all(command: dict):
    """Kirim ke SEMUA instance yang tersambung. Sengaja TIDAK dipakai untuk
    perintah playback (play/pause/next/dst) -- itu justru akar masalah
    "GUI kadang tidak sesuai instance yang dituju" saat lebih dari satu
    Chrome/ekstensi tersambung ke backend yang sama. Simpan cuma untuk
    kasus yang MEMANG harus ke semua instance sekaligus (kalau ada)."""
    if not state.clients:
        state.emit("log", {"text": "Tidak ada ekstensi yang tersambung, perintah dibatalkan."})
        return
    data = json.dumps(command)
    for ws in list(state.clients.keys()):
        try:
            await ws.send(data)
        except Exception:
            pass


async def send_to_selected(command: dict):
    """Kirim HANYA ke instance yang sedang dipilih di dropdown Control
    Panel. Kalau cuma ada satu instance yang tersambung, otomatis dianggap
    "dipilih" (supaya pemakaian normal 1-instance tidak berubah sama
    sekali). Kalau ada >1 instance dan belum ada yang dipilih, perintah
    DIBATALKAN + beri tahu user lewat log, alih-alih diam-diam salah
    sasaran atau nyasar ke semua instance sekaligus."""
    if not state.clients:
        state.emit("log", {"text": "Tidak ada ekstensi yang tersambung, perintah dibatalkan."})
        return

    target_ws = None
    if state.selected_instance_id:
        for ws, meta in state.clients.items():
            if meta.get("instance_id") == state.selected_instance_id:
                target_ws = ws
                break
        if target_ws is None:
            state.emit("log", {
                "text": "Instance yang dipilih sudah terputus. Pilih instance lain di dropdown."
            })
            return
    elif len(state.clients) == 1:
        target_ws = next(iter(state.clients))
    else:
        state.emit("log", {
            "text": f"Ada {len(state.clients)} instance Chrome tersambung tapi belum "
                    f"ada yang dipilih. Pilih salah satu di dropdown instance dulu."
        })
        return

    profile_id = state.clients.get(target_ws, {}).get("browser_profile_id")
    web_session = state.web_sessions.get(profile_id)
    if web_session and web_session.get("active"):
        state.emit("log", {
            "text": "Perintah GUI dibatalkan: profil ini sedang dikendalikan oleh sesi playback dari web."
        })
        return

    try:
        await target_ws.send(json.dumps(command))
    except Exception:
        pass


def build_play_order(previous_last: str | None = None) -> list[str]:
    """Susun urutan putar. Mode acak: urutan diacak ulang (Fisher-Yates via
    random.shuffle) tiap sesi/loop. previous_last = lagu terakhir di urutan
    sebelumnya; kalau hasil acak kebetulan MULAI dengan lagu yang sama,
    tukar posisi supaya tidak ada lagu yang diputar dua kali beruntun saat
    pindah loop."""
    urls = list(state.playlist_urls)
    if state.config.get("shuffle_playlist", True) and len(urls) > 1:
        random.shuffle(urls)
        if previous_last and urls[0] == previous_last:
            j = random.randrange(1, len(urls))
            urls[0], urls[j] = urls[j], urls[0]
    return urls


async def start_playlist_playback():
    """Kirim playPlaylist ke ekstensi untuk urls yang sudah di-load. Ini
    dianggap SESI BARU: statistik per-lagu direset dari nol."""
    if not state.playlist_urls:
        state.emit("log", {"text": "Playlist belum di-load."})
        return
    state.is_running = True
    state.last_status_time = time.time()
    state.song_stats = {}
    state.playlist_index = -1
    state.play_order = build_play_order()
    await send_to_selected({
        "type": "command", "action": "playPlaylist",
        "urls": state.play_order, "resetStats": True,
    })
    mode = "urutan acak" if state.config.get("shuffle_playlist", True) and len(state.play_order) > 1 else "urutan asli"
    state.emit("log", {"text": f"Memulai playlist ({len(state.play_order)} URL, {mode})."})
    state.emit_playlist_stats()
    state.emit("running_changed", {"is_running": True})


async def stop_playlist_playback(reason: str = "Dihentikan manual"):
    state.is_running = False
    await send_to_selected({"type": "command", "action": "pause"})
    state.emit("log", {"text": f"Playlist dihentikan: {reason}"})
    state.emit("running_changed", {"is_running": False})
    notify_telegram(f"[BayzBot] Playlist berhenti.\nAlasan: {reason}\nTotal loop: {state.loop_count}")


async def handle_playlist_completed():
    state.loop_count += 1
    state.emit("loop_changed", {"loop_count": state.loop_count})

    target = state.config.get("total_loop", -1)
    if target is not None and target > 0 and state.loop_count >= target:
        await stop_playlist_playback(f"Target {target} loop tercapai")
        return

    state.emit("log", {"text": f"Loop ke-{state.loop_count} selesai, mengulang playlist..."})
    # Mode acak: urutan diacak ulang tiap loop. resetStats=False -> statistik
    # per-lagu (jumlah diputar) tetap terakumulasi antar-loop walau urutannya beda.
    last = state.play_order[-1] if state.play_order else None
    state.play_order = build_play_order(previous_last=last)
    await send_to_selected({
        "type": "command", "action": "playPlaylist",
        "urls": state.play_order, "resetStats": False,
    })
    state.emit_playlist_stats()
    state.last_status_time = time.time()


def handle_playlist_index_change(url: str, index: int, total: int, is_start: bool):
    for u, s in state.song_stats.items():
        if s.get("status") == "Playing":
            s["status"] = "Selesai"

    entry = state.song_stats.get(url) or {
        "title": None, "artist": None, "album": None, "play_count": 0, "status": "Menunggu"
    }
    entry["play_count"] = entry.get("play_count", 0) + 1
    entry["status"] = "Playing"
    state.song_stats[url] = entry
    state.playlist_index = index

    state.pending_notification = {"kind": "first" if is_start else "change", "index": index, "total": total}

    state.emit("log", {"text": f"{'Memulai' if is_start else 'Lanjut ke'} URL #{index + 1}/{total}"})
    state.emit_playlist_stats()


def handle_status_payload(payload: dict):
    state.current_status = payload
    state.last_status_time = time.time()

    order = state.active_order()
    if state.playlist_index >= 0 and state.playlist_index < len(order):
        active_url = order[state.playlist_index]
        entry = state.song_stats.get(active_url)
        if entry is not None:
            entry["title"] = payload.get("title") or entry.get("title")
            entry["artist"] = payload.get("artist") or entry.get("artist")
            entry["album"] = payload.get("album") or entry.get("album")
            state.emit_playlist_stats()

    if state.pending_notification and payload.get("title"):
        kind = state.pending_notification["kind"]
        title = payload.get("title") or "Tidak diketahui"
        artist = payload.get("artist") or ""
        if kind == "first":
            notify_telegram(f"[BayzBot] Mulai memutar playlist.\nLagu pertama: {title} - {artist}")
        else:
            idx = state.pending_notification["index"]
            total = state.pending_notification["total"]
            notify_telegram(f"[BayzBot] Berpindah lagu ({idx + 1}/{total}): {title} - {artist}")
        state.pending_notification = None

    state.emit("status", payload)


def _connections_for_profile(profile_id: str) -> list:
    return [
        ws for ws, meta in state.clients.items()
        if meta.get("browser_profile_id") == profile_id
    ]


async def _send_profile_command(profile_id: str, action: str, timeout: int = 20, **extra) -> dict:
    matches = _connections_for_profile(profile_id)
    if len(matches) != 1:
        raise RuntimeError(
            "Tidak ada ekstensi yang terhubung untuk profil Dolphin ini."
            if not matches else
            "Lebih dari satu ekstensi terhubung dengan ID profil Dolphin yang sama."
        )

    request_id = secrets.token_urlsafe(18)
    future = asyncio.get_running_loop().create_future()
    state.pending_commands[request_id] = future
    try:
        await matches[0].send(json.dumps({
            "type": "command",
            "action": action,
            "requestId": request_id,
            **extra,
        }))
        return await asyncio.wait_for(future, timeout=timeout)
    finally:
        state.pending_commands.pop(request_id, None)


def _next_web_order(session: dict) -> list[str]:
    urls = list(session["urls"])
    if session["shuffle"] and len(urls) > 1:
        previous_last = session.get("last_url")
        random.shuffle(urls)
        if previous_last and urls[0] == previous_last:
            swap_index = random.randrange(1, len(urls))
            urls[0], urls[swap_index] = urls[swap_index], urls[0]
    return urls


async def _start_web_playlist(
    profile_id: str,
    urls: list[str],
    settings: dict,
    tracks: list[dict] | None = None,
) -> dict:
    matches = _connections_for_profile(profile_id)
    if not matches:
        raise RuntimeError("Ekstensi untuk profil Dolphin tersebut belum terhubung.")
    if (
        state.is_running
        and state.selected_instance_id
        == state.clients[matches[0]].get("instance_id")
    ):
        raise RuntimeError("Hentikan sesi playlist GUI sebelum memulai playback dari web pada instance yang sama.")

    old_session = state.web_sessions.get(profile_id)
    if old_session and old_session.get("active"):
        await _send_profile_command(profile_id, "stop")

    session = {
        "urls": urls,
        "shuffle": bool(settings.get("shuffle", False)),
        "total_loop": int(settings.get("total_loop", -1)),
        "watchdog_interval": int(settings.get("watchdog_interval", 30)),
        "ad_skip_delay_seconds": int(settings.get("ad_skip_delay_seconds", 0)),
        "loops_completed": 0,
        "total_plays": 0,
        "current_song": None,
        "current_artist": None,
        "track_metadata": {
            track["url"]: {
                "title": track.get("title"),
                "artist": track.get("artist"),
            }
            for track in (tracks or [])
        },
        "last_url": None,
        "last_status_at": time.time(),
        "received_status": False,
        "last_recovery_at": 0.0,
        "last_watchdog_log_at": 0.0,
        "recovery_attempts": 0,
        "paused": False,
        "starting": True,
        "active": True,
    }
    state.web_sessions[profile_id] = session
    state.emit("log", {
        "text": f"Watchdog mulai memantau stream profil Dolphin {profile_id} "
                f"(interval {session['watchdog_interval']} detik)."
    })
    order = _next_web_order(session)
    try:
        result = await _send_profile_command(
            profile_id,
            "playPlaylist",
            urls=order,
            resetStats=True,
            adSkipDelaySeconds=session["ad_skip_delay_seconds"],
            timeout=50,
        )
    except (RuntimeError, asyncio.TimeoutError) as exc:
        session["active"] = False
        session["starting"] = False
        state.emit("log", {
            "level": "ERROR",
            "text": f"Stream profil Dolphin {profile_id} gagal dimulai: {exc}"
        })
        raise
    if not result.get("ok"):
        session["active"] = False
        session["starting"] = False
        state.emit("log", {
            "level": "ERROR",
            "text": f"Stream profil Dolphin {profile_id} gagal dimulai: "
                    f"{result.get('error') or 'perintah ditolak'}"
        })
        raise RuntimeError(result.get("error") or "Ekstensi menolak perintah playlist.")
    session["starting"] = False
    state.emit("log", {
        "text": f"Stream profil Dolphin {profile_id} dimulai "
                f"({len(order)} lagu; watchdog setiap {session['watchdog_interval']} detik)."
    })

    return {
        "success": True,
        "playing": False,
        "profile_id": profile_id,
        "current_song": session.get("current_song"),
        "current_artist": session.get("current_artist"),
        "message": "Perintah playlist diterima; menunggu status playback terkonfirmasi.",
    }


async def _stop_web_playlist(profile_id: str) -> dict:
    session = state.web_sessions.get(profile_id)
    if session:
        session["active"] = False
        state.emit("log", {
            "text": f"Stream profil Dolphin {profile_id} dihentikan; watchdog berhenti memantau."
        })
    result = await _send_profile_command(profile_id, "stop")
    if not result.get("ok"):
        raise RuntimeError(result.get("error") or "Ekstensi menolak perintah berhenti.")
    return {"success": True, "profile_id": profile_id}


async def _control_web_playlist(profile_id: str, action: str) -> dict:
    session = state.web_sessions.get(profile_id)
    if action == "stop":
        if not session or not session.get("active"):
            return {"success": True, "profile_id": profile_id, "action": "stop"}
        return await _stop_web_playlist(profile_id)

    if not session or not session.get("active"):
        raise RuntimeError("Tidak ada playlist BayzBot aktif untuk profil ini.")

    result = await _send_profile_command(profile_id, action)
    if not result.get("ok"):
        raise RuntimeError(result.get("error") or f"Ekstensi menolak perintah {action}.")

    if action == "pause":
        session["paused"] = True
        session["not_playing_since"] = None
        state.emit("log", {"text": f"Stream profil Dolphin {profile_id} dijeda."})
    elif action in {"play", "restart"}:
        session["paused"] = False
        session["not_playing_since"] = None
        session["last_status_at"] = time.time()
        state.emit("log", {"text": f"Stream profil Dolphin {profile_id} melanjutkan playback ({action})."})

    return {"success": True, "profile_id": profile_id, "action": action}


async def _handle_web_playlist_completed(profile_id: str):
    session = state.web_sessions.get(profile_id)
    if not session or not session.get("active"):
        return

    session["loops_completed"] += 1
    target = session["total_loop"]
    state.emit("log", {
        "text": f"Stream profil Dolphin {profile_id}: playlist selesai "
                f"(loop {session['loops_completed']}"
                f"{'/' + str(target) if target > 0 else ''})."
    })
    if target > 0 and session["loops_completed"] >= target:
        session["active"] = False
        await _send_profile_command(profile_id, "stop")
        state.emit("log", {
            "text": f"Playlist profil Dolphin {profile_id} selesai ({target} loop)."
        })
        return

    order = _next_web_order(session)
    session["last_url"] = order[-1] if order else None
    try:
        result = await _send_profile_command(
            profile_id,
            "playPlaylist",
            urls=order,
            resetStats=False,
            adSkipDelaySeconds=session["ad_skip_delay_seconds"],
            timeout=50,
        )
    except (RuntimeError, asyncio.TimeoutError) as exc:
        session["active"] = False
        state.emit("log", {
            "text": f"Playlist profil Dolphin {profile_id} gagal mengulang: {exc}"
        })
        return
    if not result.get("ok"):
        session["active"] = False
        state.emit("log", {
            "text": f"Playlist profil Dolphin {profile_id} gagal mengulang: "
                    f"{result.get('error') or 'perintah ditolak'}"
        })


AD_EVENTS = {"adDetected", "adSkipClicked", "adUnskippable", "adEnded"}


def _ad_seconds(ms) -> str:
    try:
        return f"{max(0.0, float(ms)) / 1000:.1f}"
    except (TypeError, ValueError):
        return "?"


def log_ad_event(ws, msg) -> None:
    """Tulis status skip iklan dari ekstensi ke LOG (satu baris per perubahan status)."""
    meta = state.clients.get(ws) or {}
    profile_id = meta.get("browser_profile_id")
    session = state.web_sessions.get(profile_id) if profile_id else None
    if session is not None:
        who = f"Stream profil Dolphin {profile_id}"
        counters = session
    else:
        who = f"Instance {meta.get('label') or meta.get('instance_id') or 'tidak dikenal'}"
        counters = meta

    ev = msg.get("event")
    if ev == "adDetected":
        counters["ads_detected"] = int(counters.get("ads_detected", 0)) + 1
        if msg.get("skippable"):
            try:
                delay = int(msg.get("skipDelaySeconds") or 0)
            except (TypeError, ValueError):
                delay = 0
            when = f"skip otomatis setelah {delay} detik" if delay > 0 else "skip langsung"
            text = f"iklan terdeteksi (tombol Skip tersedia, {when})."
        else:
            text = "iklan terdeteksi (tombol Skip belum muncul)."
    elif ev == "adSkipClicked":
        text = f"tombol Skip iklan diklik ({_ad_seconds(msg.get('waitedMs'))} detik sejak iklan muncul)."
    elif ev == "adUnskippable":
        text = (f"iklan tidak bisa di-skip, menunggu selesai sendiri "
                f"({_ad_seconds(msg.get('elapsedMs'))} detik berjalan).")
    elif ev == "adEnded":
        skipped = msg.get("skipped") is True
        if skipped:
            counters["ads_skipped"] = int(counters.get("ads_skipped", 0)) + 1
        how = "di-skip bot" if skipped else "habis sendiri"
        text = (f"iklan selesai - {how} (durasi {_ad_seconds(msg.get('durationMs'))} detik). "
                f"Total iklan: {int(counters.get('ads_detected', 0))}, "
                f"di-skip: {int(counters.get('ads_skipped', 0))}.")
    else:
        return
    state.emit("log", {"text": f"{who}: {text}"})


async def ws_handler(ws):
    state.clients[ws] = {
        "instance_id": None,
        "label": None,
        "browser_profile_id": None,
        "connected_at": time.time(),
        "current_status": {},
    }
    state.emit("conn_changed", {"connected": True})
    state.emit("log", {"text": "Ekstensi tersambung (menunggu handshake instance)..."})
    state.emit_clients_changed()
    try:
        async for raw in ws:
            try:
                msg = json.loads(raw)
            except json.JSONDecodeError:
                continue

            msg_type = msg.get("type")
            if msg_type == "status":
                payload = msg.get("payload", {})
                state.clients[ws]["current_status"] = payload
                browser_profile_id = state.clients[ws].get("browser_profile_id")
                session = state.web_sessions.get(browser_profile_id)
                if session and session.get("active"):
                    session["last_status_at"] = time.time()
                    session["received_status"] = True
                    if payload.get("isPlaying"):
                        session["not_playing_since"] = None
                        session["recovery_attempts"] = 0
                        title = payload.get("title")
                        artist = payload.get("artist")
                        if title and title not in {"YouTube Music", "YouTube"}:
                            session["current_song"] = title
                        if artist:
                            session["current_artist"] = artist
                    elif session.get("not_playing_since") is None:
                        session["not_playing_since"] = time.time()
                if not session and state.clients[ws].get("instance_id") == state.selected_instance_id:
                    handle_status_payload(payload)
            elif msg_type == "event":
                ev = msg.get("event")
                if ev == "playlistCompleted":
                    browser_profile_id = state.clients[ws].get("browser_profile_id")
                    if browser_profile_id in state.web_sessions:
                        await _handle_web_playlist_completed(browser_profile_id)
                    elif state.clients[ws].get("instance_id") == state.selected_instance_id:
                        await handle_playlist_completed()
                elif ev == "playlistStarted":
                    browser_profile_id = state.clients[ws].get("browser_profile_id")
                    session = state.web_sessions.get(browser_profile_id)
                    if session:
                        track_url = msg.get("url")
                        session["last_url"] = track_url
                        session["total_plays"] += 1
                        track = session.get("track_metadata", {}).get(track_url, {})
                        session["current_song"] = track.get("title") or None
                        session["current_artist"] = track.get("artist") or None
                        title = session["current_song"] or track_url or "judul tidak tersedia"
                        artist = f" — {session['current_artist']}" if session["current_artist"] else ""
                        state.emit("log", {
                            "text": f"Stream profil Dolphin {browser_profile_id}: "
                                    f"memulai lagu #{msg.get('index', 0) + 1}/{msg.get('total', len(session['urls']))} "
                                    f"{title}{artist}."
                        })
                    elif state.clients[ws].get("instance_id") == state.selected_instance_id:
                        handle_playlist_index_change(msg.get("url"), msg.get("index", 0), msg.get("total", 1), True)
                elif ev == "playlistAdvanced":
                    browser_profile_id = state.clients[ws].get("browser_profile_id")
                    session = state.web_sessions.get(browser_profile_id)
                    if session:
                        track_url = msg.get("url")
                        session["last_url"] = track_url
                        session["total_plays"] += 1
                        track = session.get("track_metadata", {}).get(track_url, {})
                        session["current_song"] = track.get("title") or None
                        session["current_artist"] = track.get("artist") or None
                        title = session["current_song"] or track_url or "judul tidak tersedia"
                        artist = f" — {session['current_artist']}" if session["current_artist"] else ""
                        state.emit("log", {
                            "text": f"Stream profil Dolphin {browser_profile_id}: "
                                    f"lanjut ke lagu #{msg.get('index', 0) + 1}/{msg.get('total', len(session['urls']))} "
                                    f"{title}{artist}."
                        })
                    elif state.clients[ws].get("instance_id") == state.selected_instance_id:
                        handle_playlist_index_change(msg.get("url"), msg.get("index", 0), msg.get("total", 1), False)
                elif ev in AD_EVENTS:
                    log_ad_event(ws, msg)
            elif msg_type == "commandResult":
                future = state.pending_commands.get(msg.get("requestId"))
                if future and not future.done():
                    future.set_result(msg.get("result") or {"ok": False, "error": "Respons ekstensi kosong."})
            elif msg_type == "hello":
                instance_id = msg.get("instanceId")
                label = (msg.get("instanceLabel") or "").strip() or None
                browser_profile_id = str(msg.get("browserProfileId") or "").strip() or None
                instance_ip = msg.get("instanceIp")
                instance_country = msg.get("instanceCountry")
                proxy_enabled = msg.get("proxyEnabled", False)
                
                state.clients[ws]["instance_id"] = instance_id
                state.clients[ws]["label"] = label
                state.clients[ws]["browser_profile_id"] = browser_profile_id
                if "instanceIp" in msg:
                    state.clients[ws]["instance_ip"] = instance_ip
                if "instanceCountry" in msg:
                    state.clients[ws]["instance_country"] = instance_country
                if "proxyEnabled" in msg:
                    state.clients[ws]["proxy_enabled"] = proxy_enabled
                
                short_id = (instance_id or "?")[:8]
                display = f"{label} ({short_id})" if label else short_id
                
                # Build IP display for logging
                ip_display = ""
                if instance_ip:
                    flag = get_country_flag(instance_country)
                    ip_display = f" - IP: {instance_ip} {flag} ({instance_country})"
                if proxy_enabled:
                    ip_display += " [Proxy]"
                    
                state.emit("log", {
                    "text": f"Handshake dari ekstensi v{msg.get('version')} — instance {display}"
                            f"{' — Dolphin profile ' + browser_profile_id if browser_profile_id else ''}"
                            f"{ip_display}"
                })
                # Auto-pilih kalau ini satu-satunya instance yang SUDAH
                # HANDSHAKE (instance_id terisi) -- BUKAN sekadar satu-
                # satunya koneksi TCP mentah. Kalau dihitung dari koneksi
                # mentah, 2 instance yang connect hampir bersamaan bisa
                # sama-sama "terlihat sendirian" sesaat sebelum hello
                # keduanya selesai, sehingga auto-select gagal jalan untuk
                # keduanya (race condition yang sempat kejadian saat tes).
                handshaken = [m for m in state.clients.values() if m.get("instance_id")]
                if state.selected_instance_id is None and len(handshaken) == 1:
                    state.selected_instance_id = instance_id
                state.emit_clients_changed()
    except websockets.exceptions.ConnectionClosed:
        pass
    finally:
        disconnected_profile_id = state.clients.get(ws, {}).get("browser_profile_id")
        was_selected = (
            state.clients.get(ws, {}).get("instance_id") == state.selected_instance_id
            and state.selected_instance_id is not None
        )
        if disconnected_profile_id and state.web_sessions.get(disconnected_profile_id, {}).get("active"):
            state.emit("log", {
                "level": "WARNING",
                "text": f"Ekstensi stream profil Dolphin {disconnected_profile_id} terputus."
            })
        state.clients.pop(ws, None)
        if was_selected:
            state.selected_instance_id = None
            remaining_handshaken = [m for m in state.clients.values() if m.get("instance_id")]
            if len(remaining_handshaken) == 1:
                # Cuma tersisa 1 instance lain -> auto-pilih itu, supaya
                # dropdown yang tampil & state.selected_instance_id yang
                # dipakai send_to_selected() selalu konsisten satu sama
                # lain (bukan cuma kebetulan "berhasil" lewat fallback
                # len(clients)==1 di send_to_selected()).
                state.selected_instance_id = remaining_handshaken[0]["instance_id"]
                state.emit("log", {"text": "Instance yang dipilih terputus. Otomatis pindah ke instance yang tersisa."})
            elif state.clients:
                state.emit("log", {
                    "text": "Instance yang dipilih terputus. Pilih instance lain di dropdown."
                })
        if not state.clients:
            state.emit("conn_changed", {"connected": False})
            state.emit("log", {"text": "Ekstensi terputus."})
            if state.is_running:
                notify_telegram("[BayzBot] PERINGATAN: ekstensi terputus saat playlist sedang berjalan (error).")
        state.emit_clients_changed()


class BotAPIHandler(BaseHTTPRequestHandler):
    server_version = "BayzBot/1.0"

    def log_message(self, format, *args):
        print(f"[HTTP API] {self.address_string()} {format % args}", flush=True)

    def _json_response(self, status: int, payload: dict):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _authorized(self) -> bool:
        host = (self.headers.get("Host") or "").split(":", 1)[0].strip("[]").lower()
        if host not in {"127.0.0.1", "localhost"}:
            self._json_response(403, {"success": False, "message": "Host lokal saja yang diizinkan."})
            return False
        origin = self.headers.get("Origin")
        if origin:
            parsed_origin = urllib.parse.urlsplit(origin)
            if parsed_origin.hostname not in {"127.0.0.1", "localhost"}:
                self._json_response(403, {"success": False, "message": "Origin tidak diizinkan."})
                return False
        expected = "Bearer " + str(state.config.get("http_api_token", ""))
        if not state.config.get("http_api_token") or not hmac.compare_digest(
            self.headers.get("Authorization", ""), expected
        ):
            self._json_response(401, {"success": False, "message": "Token BayzBot tidak valid."})
            return False
        return True

    def _read_json(self) -> dict:
        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError as exc:
            raise ValueError("Content-Length tidak valid.") from exc
        if length < 1 or length > 1_000_000:
            raise ValueError("Ukuran request JSON harus antara 1 byte dan 1 MB.")
        try:
            payload = json.loads(self.rfile.read(length))
        except (json.JSONDecodeError, UnicodeDecodeError) as exc:
            raise ValueError("Body harus berisi JSON yang valid.") from exc
        if not isinstance(payload, dict):
            raise ValueError("Body JSON harus berupa object.")
        return payload

    def do_GET(self):
        if self.path == "/api/health":
            if not self._authorized():
                return
            profiles = sorted({
                str(meta["browser_profile_id"])
                for meta in state.clients.values()
                if meta.get("browser_profile_id")
            })
            self._json_response(200, {
                "success": True,
                "message": "BayzBot API siap.",
                "connected_profiles": profiles,
            })
            return
        if self.path == "/api/settings/revenue":
            if not self._authorized():
                return
            cfg = load_config()
            revenue = cfg.get("revenue_rates", [])
            self._json_response(200, {"success": True, "data": revenue})
            return
        session_match = re.fullmatch(r"/api/session/([0-9]+)", self.path)
        if session_match:
            if not self._authorized():
                return
            profile_id = session_match.group(1)
            session = state.web_sessions.get(profile_id)
            if not session:
                self._json_response(200, {"success": True, "managed": False})
                return
            matches = _connections_for_profile(profile_id)
            status = state.clients[matches[0]].get("current_status", {}) if matches else {}
            self._json_response(200, {
                "success": True,
                "managed": True,
                "active": bool(session.get("active")),
                "paused": bool(session.get("paused")),
                "playing": bool(status.get("isPlaying")),
                "current_song": session.get("current_song"),
                "current_artist": session.get("current_artist"),
                "total_plays": int(session.get("total_plays", 0)),
                "loops_completed": int(session.get("loops_completed", 0)),
            })
            return
        else:
            self._json_response(404, {"success": False, "message": "Endpoint tidak ditemukan."})
            return

    def do_POST(self):
        if self.path not in {"/api/play", "/api/stop", "/api/control"}:
            self._json_response(404, {"success": False, "message": "Endpoint tidak ditemukan."})
            return
        if not self._authorized():
            return
        try:
            payload = self._read_json()
            profile_id = str(payload.get("profile_id", "")).strip()
            if not profile_id.isdigit() or int(profile_id) < 1:
                raise ValueError("profile_id harus berupa ID profil Dolphin positif.")

            if self.path == "/api/stop":
                future = asyncio.run_coroutine_threadsafe(
                    _stop_web_playlist(profile_id), state.loop
                )
                result = future.result(timeout=25)
            elif self.path == "/api/control":
                action = payload.get("action")
                if action not in {"play", "pause", "stop", "next", "previous", "restart"}:
                    raise ValueError("action harus play, pause, stop, next, previous, atau restart.")
                future = asyncio.run_coroutine_threadsafe(
                    _control_web_playlist(profile_id, action), state.loop
                )
                result = future.result(timeout=25)
            else:
                urls = payload.get("urls")
                if not isinstance(urls, list) or not urls or len(urls) > 500:
                    raise ValueError("Playlist harus berisi 1 sampai 500 URL.")
                for url in urls:
                    parsed = urllib.parse.urlsplit(url) if isinstance(url, str) else None
                    query = urllib.parse.parse_qs(parsed.query) if parsed else {}
                    video_id = query.get("v", [""])[0]
                    if (
                        not parsed
                        or parsed.scheme != "https"
                        or parsed.hostname != "music.youtube.com"
                        or not re.fullmatch(r"[A-Za-z0-9_-]{6,20}", video_id)
                    ):
                        raise ValueError("URL lagu harus berupa URL YouTube Music yang valid.")
                tracks = payload.get("tracks", [])
                if not isinstance(tracks, list) or len(tracks) > 500:
                    raise ValueError("Metadata lagu tidak valid.")
                normalized_tracks = []
                valid_urls = set(urls)
                for track in tracks:
                    if not isinstance(track, dict):
                        raise ValueError("Metadata lagu tidak valid.")
                    track_url = track.get("url")
                    if not isinstance(track_url, str) or track_url not in valid_urls:
                        raise ValueError("Metadata lagu tidak sesuai dengan URL playlist.")
                    normalized_tracks.append({
                        "url": track_url,
                        "title": str(track.get("title") or "").strip()[:255] or None,
                        "artist": str(track.get("artist") or "").strip()[:255] or None,
                    })
                settings = payload.get("settings")
                if not isinstance(settings, dict):
                    raise ValueError("Pengaturan playlist tidak valid.")
                try:
                    total_loop = int(settings.get("total_loop", -1))
                    watchdog_interval = int(settings.get("watchdog_interval", 30))
                    ad_skip_delay = int(settings.get("ad_skip_delay_seconds", 0))
                except (TypeError, ValueError) as exc:
                    raise ValueError("Pengaturan loop, watchdog, dan skip iklan harus berupa angka.") from exc
                if total_loop == 0 or total_loop < -1:
                    raise ValueError("total_loop harus -1 (tanpa batas) atau bilangan positif.")
                if not 5 <= watchdog_interval <= 3600:
                    raise ValueError("watchdog_interval harus 5 sampai 3600 detik.")
                if not 0 <= ad_skip_delay <= 3600:
                    raise ValueError("ad_skip_delay_seconds harus 0 sampai 3600.")
                if not isinstance(settings.get("shuffle", False), bool):
                    raise ValueError("shuffle harus berupa boolean.")
                normalized_settings = {
                    "shuffle": bool(settings.get("shuffle", False)),
                    "total_loop": total_loop,
                    "watchdog_interval": watchdog_interval,
                    "ad_skip_delay_seconds": ad_skip_delay,
                }
                future = asyncio.run_coroutine_threadsafe(
                    _start_web_playlist(profile_id, urls, normalized_settings, normalized_tracks), state.loop
                )
                result = future.result(timeout=55)
            self._json_response(200, result)
        except (ValueError, RuntimeError, asyncio.TimeoutError) as exc:
            self._json_response(422, {"success": False, "message": str(exc)})
        except Exception as exc:
            print(f"[HTTP API] Request gagal: {exc}", flush=True)
            self._json_response(500, {"success": False, "message": "Terjadi kesalahan internal BayzBot."})


async def watchdog_loop():
    """Pantau playlist GUI dan setiap sesi stream web yang aktif."""
    already_alerted = False
    while True:
        interval = max(5, int(state.config.get("watchdog_interval", 30)))
        await asyncio.sleep(5)
        if not state.is_running:
            already_alerted = False
        else:
            elapsed = time.time() - state.last_status_time
            if elapsed > interval and not already_alerted:
                already_alerted = True
                state.emit("log", {"text": f"Watchdog: tidak ada status masuk selama {elapsed:.0f}s"})
                notify_telegram(
                    f"[BayzBot] PERINGATAN: tidak ada status dari ekstensi selama "
                    f"{elapsed:.0f} detik saat playlist seharusnya berjalan (kemungkinan macet)."
                )
            elif elapsed <= interval:
                already_alerted = False

        now = time.time()
        for profile_id, session in list(state.web_sessions.items()):
            if not session.get("active") or session.get("starting") or session.get("paused"):
                continue
            session_interval = session["watchdog_interval"]
            not_playing_since = session.get("not_playing_since")
            stale = now - session["last_status_at"] > session_interval
            paused = not_playing_since is not None and now - not_playing_since > session_interval
            recovery_ready = now - session["last_recovery_at"] >= session_interval
            if (stale or paused) and recovery_ready:
                if session["recovery_attempts"] >= MAX_WEB_RECOVERY_ATTEMPTS:
                    session["active"] = False
                    message = (
                        f"Watchdog menghentikan pemulihan profil Dolphin {profile_id} "
                        f"setelah {MAX_WEB_RECOVERY_ATTEMPTS} percobaan tanpa playback."
                    )
                    state.emit("log", {"level": "ERROR", "text": message})
                    notify_telegram(f"[BayzBot] {message}")
                    continue

                session["last_recovery_at"] = now
                session["not_playing_since"] = now
                session["recovery_attempts"] += 1
                state.emit("log", {
                    "level": "WARNING",
                    "text": f"Watchdog mendeteksi stream profil Dolphin {profile_id} "
                            f"tidak aktif selama lebih dari {session_interval} detik "
                            f"(percobaan pemulihan {session['recovery_attempts']}/{MAX_WEB_RECOVERY_ATTEMPTS})."
                })
                try:
                    result = await _send_profile_command(profile_id, "play")
                    if not result.get("ok"):
                        raise RuntimeError(result.get("error") or "Ekstensi menolak pemulihan playback.")
                    state.emit("log", {
                        "text": f"Watchdog mencoba memulihkan playback profil Dolphin {profile_id}."
                    })
                    notify_telegram(
                        f"[BayzBot] Watchdog mencoba memulihkan playback profil {profile_id} "
                        f"(percobaan {session['recovery_attempts']})."
                    )
                except (RuntimeError, asyncio.TimeoutError) as exc:
                    state.emit("log", {
                        "level": "ERROR",
                        "text": f"Watchdog gagal memulihkan profil Dolphin {profile_id}: {exc}"
                    })
                    notify_telegram(
                        f"[BayzBot] Watchdog gagal memulihkan profil {profile_id}: {exc}"
                    )

            elif (
                session.get("received_status")
                and not stale
                and not paused
                and now - session["last_watchdog_log_at"] >= session_interval
            ):
                session["last_watchdog_log_at"] = now
                status_age = max(0, int(now - session["last_status_at"]))
                current_song = session.get("current_song") or "judul belum diterima"
                state.emit("log", {
                    "text": f"Watchdog: stream profil Dolphin {profile_id} normal; "
                            f"{current_song}; status diterima {status_age} detik lalu."
                })


async def ram_monitor_loop():
    """Pantau RAM sistem tiap RAM_CHECK_INTERVAL_SECONDS detik. Kirim
    notifikasi Telegram PAKSA (walau toggle notif mati) kalau > 85%."""
    already_alerted = False
    while True:
        await asyncio.sleep(RAM_CHECK_INTERVAL_SECONDS)
        percent = get_ram_percent()
        state.emit("ram_status", {"percent": percent})
        if percent is None:
            continue
        if percent > RAM_ALERT_THRESHOLD and not already_alerted:
            already_alerted = True
            state.emit("log", {"text": f"RAM {percent:.0f}% melebihi ambang batas {RAM_ALERT_THRESHOLD:.0f}%!"})
            notify_telegram(
                f"[BayzBot] PERINGATAN: RAM komputer terpakai {percent:.0f}% "
                f"(> {RAM_ALERT_THRESHOLD:.0f}%).",
                force=True,
            )
        elif percent <= RAM_ALERT_THRESHOLD - 5:
            already_alerted = False  # histeresis, hindari alert berulang di ambang batas


def _backend_already_running(http_port: int) -> bool:
    """True kalau ada BayzBot lain yang menjawab di HTTP API (200, atau 401/403 = token beda)."""
    req = urllib.request.Request(
        f"http://127.0.0.1:{http_port}/api/health",
        headers={"Authorization": "Bearer " + str(state.config.get("http_api_token", ""))},
    )
    try:
        with urllib.request.urlopen(req, timeout=3) as r:
            return r.status == 200
    except urllib.error.HTTPError as e:
        return e.code in (401, 403)
    except Exception:
        return False


async def run_server():
    state.loop = asyncio.get_running_loop()
    port = get_ws_port()
    http_api_port = get_http_api_port()
    if http_api_port == port:
        raise RuntimeError("Port HTTP API harus berbeda dari port WebSocket.")
    
    if not is_port_available(port):
        if _backend_already_running(http_api_port):
            state.emit("log", {"text": f"[INFO] BayzBot lain sudah berjalan (port {port}/{http_api_port}); instance ini berhenti tanpa error."})
            return
        state.emit("log", {"text": f"[ERROR] Port {port} dipakai aplikasi lain, bukan BayzBot ini. Gunakan --port=XXXX atau ubah di Options."})
        state.emit("log", {"text": f"Cth: python backend_gui.py --port=8766"})
        await asyncio.sleep(2)
        raise RuntimeError(f"Port {port} tidak tersedia")
    
    state.emit("log", {"text": f"[OK] Server WebSocket jalan di ws://{HOST}:{port}"})

    http_server = None
    try:
        http_server = ThreadingHTTPServer(("127.0.0.1", http_api_port), BotAPIHandler)
        threading.Thread(target=http_server.serve_forever, name="bayzbot-http-api", daemon=True).start()
        state.emit("log", {
            "text": f"[OK] HTTP API jalan di http://127.0.0.1:{http_api_port} (loopback saja)"
        })
        
        # Start Telegram polling thread
        threading.Thread(target=telegram_polling, name="bayzbot-telegram-polling", daemon=True).start()
        state.emit("log", {"text": "[OK] Telegram polling started"})
        
        async with websockets.serve(ws_handler, HOST, port):
            await asyncio.gather(watchdog_loop(), ram_monitor_loop())
    except OSError as e:
        state.emit("log", {"text": f"[ERROR] Gagal bind port WebSocket/HTTP API: {e}"})
        if http_server is not None:
            http_server.shutdown()
        raise

def start_backend_thread():
    def _runner():
        try:
            asyncio.run(run_server())
        except Exception as e:
            state.emit("log", {"text": f"[ERROR] Server error: {e}"})
            import traceback
            traceback.print_exc()

    t = threading.Thread(target=_runner, daemon=True)
    t.start()
    return t


def run_coro_threadsafe(coro):
    """Jadwalkan coroutine dari GUI thread (sync) ke event loop asyncio."""
    if state.loop is None:
        state.emit("log", {"text": "Server belum siap, coba lagi sebentar."})
        return
    asyncio.run_coroutine_threadsafe(coro, state.loop)


# ---------------------------------------------------------------------------
# GUI (Tkinter) — jalan di main thread
# ---------------------------------------------------------------------------

class OptionsWindow(tk.Toplevel):
    def __init__(self, master, cfg: dict, on_save):
        super().__init__(master)
        self.title("BayzBot - Pengaturan")
        self.resizable(False, False)
        self.on_save = on_save
        self.cfg = cfg

        pad = {"padx": 12, "pady": 6}

        ttk.Label(self, text="Total loop playlist").grid(row=0, column=0, sticky="w", **pad)
        self.infinite_var = tk.BooleanVar(value=(cfg.get("total_loop", -1) is None or cfg.get("total_loop", -1) <= 0))
        self.total_loop_var = tk.StringVar(
            value="" if self.infinite_var.get() else str(cfg.get("total_loop", 1))
        )
        self.total_loop_entry = ttk.Entry(self, textvariable=self.total_loop_var, width=10)
        self.total_loop_entry.grid(row=0, column=1, sticky="w", **pad)
        self.infinite_check = ttk.Checkbutton(
            self, text="Infinity loop", variable=self.infinite_var, command=self._toggle_infinite
        )
        self.infinite_check.grid(row=0, column=2, sticky="w", **pad)
        self._toggle_infinite()

        ttk.Label(self, text="Interval watchdog (detik)").grid(row=1, column=0, sticky="w", **pad)
        self.watchdog_var = tk.StringVar(value=str(cfg.get("watchdog_interval", 30)))
        ttk.Entry(self, textvariable=self.watchdog_var, width=10).grid(row=1, column=1, sticky="w", **pad)

        ttk.Label(self, text="Skip iklan setelah (detik)").grid(row=2, column=0, sticky="w", **pad)
        self.ad_skip_var = tk.StringVar(value=str(cfg.get("ad_skip_delay_seconds", 0)))
        ttk.Entry(self, textvariable=self.ad_skip_var, width=10).grid(row=2, column=1, sticky="w", **pad)
        ttk.Label(self, text="0 = langsung skip begitu tombol terlihat", foreground="#777777").grid(
            row=2, column=2, sticky="w", **pad
        )

        ttk.Label(self, text="Port WebSocket backend").grid(row=3, column=0, sticky="w", **pad)
        self.ws_port_var = tk.StringVar(value=str(cfg.get("ws_port", 8765)))
        ttk.Entry(self, textvariable=self.ws_port_var, width=10).grid(row=3, column=1, sticky="w", **pad)
        ttk.Label(
            self, text="Ganti kalau jalankan >1 backend di 1 PC.\n"
                       "⚠️ Perlu RESTART aplikasi agar port baru berlaku.",
            foreground="#cc0000", justify="left",
        ).grid(row=3, column=2, sticky="w", **pad)

        ttk.Label(self, text="Port HTTP API web").grid(row=4, column=0, sticky="w", **pad)
        self.http_api_port_var = tk.StringVar(value=str(cfg.get("http_api_port", HTTP_API_PORT)))
        ttk.Entry(self, textvariable=self.http_api_port_var, width=10).grid(row=4, column=1, sticky="w", **pad)
        ttk.Label(self, text="Default 8766; URL ini di Settings web.", foreground="#777777").grid(
            row=4, column=2, sticky="w", **pad
        )
        ttk.Separator(self, orient="horizontal").grid(row=5, column=0, columnspan=3, sticky="ew", pady=8)

        # Proxy settings
        self.use_proxy_var = tk.BooleanVar(value=cfg.get("use_proxy", False))
        ttk.Checkbutton(
            self, text="Gunakan Proxy", variable=self.use_proxy_var, command=self._toggle_proxy
        ).grid(row=6, column=0, columnspan=2, sticky="w", **pad)

        ttk.Label(self, text="Proxy URL").grid(row=7, column=0, sticky="w", **pad)
        self.proxy_url_var = tk.StringVar(value=cfg.get("proxy_url", ""))
        self.proxy_url_entry = ttk.Entry(self, textvariable=self.proxy_url_var, width=32)
        self.proxy_url_entry.grid(row=7, column=1, columnspan=2, sticky="w", **pad)
        ttk.Label(
            self, text="Contoh: http://proxy.example.com:8080", foreground="#777777"
        ).grid(row=8, column=0, columnspan=3, sticky="w", padx=12)

        ttk.Separator(self, orient="horizontal").grid(row=9, column=0, columnspan=3, sticky="ew", pady=8)

        self.telegram_enabled_var = tk.BooleanVar(value=cfg.get("telegram_enabled", True))
        ttk.Checkbutton(
            self, text="Aktifkan notifikasi Telegram", variable=self.telegram_enabled_var
        ).grid(row=10, column=0, columnspan=2, sticky="w", **pad)
        ttk.Label(
            self, text="(Peringatan RAM > 85% tetap dikirim walau ini dimatikan)", foreground="#777777"
        ).grid(row=11, column=0, columnspan=3, sticky="w", padx=12)

        ttk.Label(self, text="Telegram Bot Token").grid(row=12, column=0, sticky="w", **pad)
        self.token_var = tk.StringVar(value=cfg.get("telegram_bot_token", ""))
        ttk.Entry(self, textvariable=self.token_var, width=32, show="*").grid(
            row=12, column=1, columnspan=2, sticky="w", **pad
        )

        ttk.Label(self, text="Telegram Chat ID").grid(row=13, column=0, sticky="w", **pad)
        self.chat_id_var = tk.StringVar(value=cfg.get("telegram_chat_id", ""))
        ttk.Entry(self, textvariable=self.chat_id_var, width=32).grid(
        row=13, column=1, columnspan=2, sticky="w", **pad
        )

        ttk.Label(self, text="Web API untuk integrasi aplikasi web").grid(
        row=14, column=0, sticky="w", **pad
        )
        self.api_token_var = tk.StringVar(value=cfg.get("http_api_token", ""))
        self.api_token_entry = ttk.Entry(
            self, textvariable=self.api_token_var, width=42, state="readonly"
        )
        self.api_token_entry.grid(row=14, column=1, sticky="w", **pad)
        ttk.Button(self, text="Salin token", command=self._copy_api_token).grid(
            row=14, column=2, sticky="w", **pad
        )
        ttk.Label(
            self,
            text=f"URL: http://127.0.0.1:{cfg.get('http_api_port', HTTP_API_PORT)}  |  Tempel token di Settings web",
            foreground="#777777",
        ).grid(row=15, column=0, columnspan=3, sticky="w", padx=12)

        btn_frame = ttk.Frame(self)
        btn_frame.grid(row=16, column=0, columnspan=3, pady=12)
        ttk.Button(btn_frame, text="Ganti Password Aplikasi", command=self._change_password).pack(side="left", padx=6)
        ttk.Button(btn_frame, text="Test Telegram", command=self._test_telegram).pack(side="left", padx=6)
        ttk.Button(btn_frame, text="Simpan", command=self._save).pack(side="left", padx=6)
        ttk.Button(btn_frame, text="Batal", command=self.destroy).pack(side="left", padx=6)

    def _toggle_proxy(self):
        state_ = "normal" if self.use_proxy_var.get() else "disabled"
        self.proxy_url_entry.configure(state=state_)

    def _change_password(self):
        ChangePasswordDialog(self)

    def _copy_api_token(self):
        self.clipboard_clear()
        self.clipboard_append(self.api_token_var.get())
        messagebox.showinfo("BayzBot", "Token Web API disalin ke clipboard.")

    def _toggle_infinite(self):
        state_ = "disabled" if self.infinite_var.get() else "normal"
        self.total_loop_entry.configure(state=state_)

    def _test_telegram(self):
        ok = send_telegram_message(
            self.token_var.get().strip(), self.chat_id_var.get().strip(),
            "[BayzBot] Ini pesan tes dari BayzBot Control Panel."
        )
        if ok:
            messagebox.showinfo("BayzBot", "Pesan tes berhasil dikirim. Cek Telegram kamu.")
        else:
            messagebox.showerror("BayzBot", "Gagal mengirim pesan tes. Cek kembali Bot Token & Chat ID.")

    def _save(self):
        try:
            total_loop = -1 if self.infinite_var.get() else int(self.total_loop_var.get().strip() or "1")
        except ValueError:
            messagebox.showerror("BayzBot", "Total loop harus berupa angka.")
            return
        try:
            watchdog_interval = int(self.watchdog_var.get().strip() or "30")
            if not 5 <= watchdog_interval <= 3600:
                raise ValueError
        except ValueError:
            messagebox.showerror("BayzBot", "Interval watchdog harus antara 5 dan 3600 detik.")
            return
        try:
            ad_skip_delay = max(0, int(self.ad_skip_var.get().strip() or "0"))
            if ad_skip_delay > 3600:
                raise ValueError
        except ValueError:
            messagebox.showerror("BayzBot", "Jeda skip iklan harus antara 0 dan 3600 detik.")
            return
        try:
            ws_port = int(self.ws_port_var.get().strip() or "8765")
            if not (1 <= ws_port <= 65535):
                raise ValueError
        except ValueError:
            messagebox.showerror("BayzBot", "Port WebSocket harus angka 1-65535.")
            return
        try:
            http_api_port = int(self.http_api_port_var.get().strip() or str(HTTP_API_PORT))
            if not (1 <= http_api_port <= 65535):
                raise ValueError
        except ValueError:
            messagebox.showerror("BayzBot", "Port HTTP API harus angka 1-65535.")
            return
        if http_api_port == ws_port:
            messagebox.showerror("BayzBot", "Port HTTP API harus berbeda dari port WebSocket.")
            return

        port_changed = ws_port != self.cfg.get("ws_port", 8765)
        api_port_changed = http_api_port != self.cfg.get("http_api_port", HTTP_API_PORT)
        
        if port_changed and not is_port_available(ws_port):
            messagebox.showerror(
                "BayzBot",
                f"Port {ws_port} sudah terpakai oleh aplikasi lain.\n"
                f"Pilih port yang berbeda atau tutup aplikasi yang menggunakan port tersebut."
            )
            return
        if api_port_changed and not is_port_available(http_api_port):
            messagebox.showerror(
                "BayzBot",
                f"Port HTTP API {http_api_port} sudah terpakai.\n"
                "Pilih port yang berbeda atau tutup aplikasi yang menggunakannya."
            )
            return
        
        self.cfg["total_loop"] = total_loop
        self.cfg["watchdog_interval"] = watchdog_interval
        self.cfg["ad_skip_delay_seconds"] = ad_skip_delay
        self.cfg["ws_port"] = ws_port
        self.cfg["http_api_port"] = http_api_port
        self.cfg["telegram_enabled"] = self.telegram_enabled_var.get()
        self.cfg["telegram_bot_token"] = self.token_var.get().strip()
        self.cfg["telegram_chat_id"] = self.chat_id_var.get().strip()
        self.cfg["use_proxy"] = self.use_proxy_var.get()
        self.cfg["proxy_url"] = self.proxy_url_var.get().strip()
        save_config(self.cfg)
        self.on_save(self.cfg)
        
        if port_changed or api_port_changed:
            messagebox.showinfo(
                "BayzBot",
                f"⚠️ Port WebSocket: {ws_port}; HTTP API: {http_api_port}.\n\n"
                f"Restart aplikasi agar perubahan berlaku."
            )
        else:
            messagebox.showinfo("BayzBot", "Pengaturan berhasil disimpan.")
        self.destroy()


class PlaylistWindow(tk.Toplevel):
    """Jendela terpisah menampilkan antrian playlist + statistik tiap URL."""

    COLUMNS = ("no", "song", "artist", "album", "count", "status")
    HEADERS = {"no": "#", "song": "URL / Judul", "artist": "Artis", "album": "Album",
               "count": "Diputar", "status": "Status"}
    WIDTHS = {"no": 32, "song": 220, "artist": 120, "album": 120, "count": 60, "status": 80}

    def __init__(self, master):
        super().__init__(master)
        self.title("BayzBot - Playlist & Statistik")
        self.geometry("620x340")

        top = ttk.Frame(self)
        top.pack(fill="x", padx=8, pady=(8, 0))
        ttk.Button(top, text="Refresh", command=self.refresh).pack(side="right")

        self.tree = ttk.Treeview(self, columns=self.COLUMNS, show="headings")
        for c in self.COLUMNS:
            self.tree.heading(c, text=self.HEADERS[c])
            self.tree.column(c, width=self.WIDTHS[c], anchor="w")
        self.tree.pack(fill="both", expand=True, padx=8, pady=8)
        self.refresh()
        
        # Auto-refresh setiap 1 detik untuk menampilkan title yang baru di-fetch
        self._auto_refresh()

    def _auto_refresh(self):
        """Auto-refresh playlist display setiap 1 detik."""
        if self.winfo_exists():
            self.refresh()
            self.after(1000, self._auto_refresh)

    def refresh(self):
        for item in self.tree.get_children():
            self.tree.delete(item)
        for i, url in enumerate(state.active_order()):
            stat = state.song_stats.get(url, {})
            label = stat.get("title") or url
            self.tree.insert("", "end", values=(
                i + 1, label, stat.get("artist") or "-", stat.get("album") or "-",
                stat.get("play_count", 0), stat.get("status", "Menunggu"),
            ))


class AuthDialog(tk.Toplevel):
    """
    Layar LOGIN APLIKASI (gerbang akses ke BayzBot Control Panel itu
    sendiri) — BUKAN login akun Google/YouTube Music (itu tombol
    terpisah "Buka Login Google" di dalam Control Panel).

    Kalau belum pernah ada akun (auth_username/hash kosong), dialog ini
    otomatis berubah jadi layar "buat akun" dulu.
    """

    def __init__(self, master, on_success):
        print("[CHECKPOINT] AuthDialog.__init__ mulai...", flush=True)
        super().__init__(master)
        print("[CHECKPOINT] tk.Toplevel.__init__ (AuthDialog) selesai.", flush=True)
        self.on_success = on_success
        self.is_setup_mode = not has_app_account()
        self.title("BayzBot - Buat Akun" if self.is_setup_mode else "BayzBot - Login")
        self.resizable(False, False)
        self.protocol("WM_DELETE_WINDOW", self._on_cancel)

        pad = {"padx": 14, "pady": 6}
        row = 0
        if self.is_setup_mode:
            ttk.Label(
                self,
                text="Belum ada akun. Buat username & password untuk\n"
                     "mengunci akses ke BayzBot Control Panel ini.",
                justify="left",
            ).grid(row=row, column=0, columnspan=2, **pad)
            row += 1

        ttk.Label(self, text="Username").grid(row=row, column=0, sticky="w", **pad)
        self.username_var = tk.StringVar(value=state.config.get("auth_username", "") if not self.is_setup_mode else "")
        username_entry = ttk.Entry(self, textvariable=self.username_var, width=24)
        username_entry.grid(row=row, column=1, **pad)
        row += 1

        ttk.Label(self, text="Password").grid(row=row, column=0, sticky="w", **pad)
        self.password_var = tk.StringVar()
        pw_entry = ttk.Entry(self, textvariable=self.password_var, show="*", width=24)
        pw_entry.grid(row=row, column=1, **pad)
        pw_entry.bind("<Return>", lambda e: self._submit())
        row += 1

        if self.is_setup_mode:
            ttk.Label(self, text="Ulangi Password").grid(row=row, column=0, sticky="w", **pad)
            self.password2_var = tk.StringVar()
            pw2_entry = ttk.Entry(self, textvariable=self.password2_var, show="*", width=24)
            pw2_entry.grid(row=row, column=1, **pad)
            pw2_entry.bind("<Return>", lambda e: self._submit())
            row += 1

        self.error_label = ttk.Label(self, text="", foreground="#c0392b")
        self.error_label.grid(row=row, column=0, columnspan=2)
        row += 1

        btn_frame = ttk.Frame(self)
        btn_frame.grid(row=row, column=0, columnspan=2, pady=10)
        ttk.Button(
            btn_frame, text="Buat Akun" if self.is_setup_mode else "Login", command=self._submit
        ).pack(side="left", padx=6)
        ttk.Button(btn_frame, text="Keluar", command=self._on_cancel).pack(side="left", padx=6)
        print("[CHECKPOINT] Semua widget AuthDialog selesai dibuat.", flush=True)

        # ROOT CAUSE (dikonfirmasi lewat pengujian, bukan cuma dugaan):
        # master (App) di-withdraw() SEBELUM dialog ini dibuat. Kalau
        # Toplevel ini di-transient()-kan ke master yang withdrawn, window
        # manager menganggap dialog ini "ikut" status master-nya dan TIDAK
        # PERNAH benar-benar di-map ke layar -- walau kita paksa
        # deiconify()/lift()/topmost/grab_set()/focus_force() sesudahnya
        # (semua fungsi itu sukses tanpa error, tapi window tetap
        # IsUnMapped). Ini persis bug "GUI tidak mau muncul" yang dilaporkan.
        #
        # FIX: JANGAN panggil self.transient(master) di sini. Toplevel tanpa
        # relasi transient ke owner yang withdrawn adalah window independen
        # yang akan selalu bisa di-map oleh window manager. grab_set() tetap
        # membuatnya modal (memblokir input ke window lain) walau tanpa
        # transient.
        print("[CHECKPOINT] (fix) skip transient() karena master withdrawn...", flush=True)
        self.deiconify()
        print("[CHECKPOINT] deiconify() OK. Panggil update_idletasks()...", flush=True)
        self.update_idletasks()
        # Tengahkan dialog di layar supaya tidak nongol di pojok kiri-atas
        # (posisi default ini kadang jadi alasan lain orang mengira "GUI
        # tidak muncul" padahal sebenarnya muncul di luar area yang terlihat).
        w = self.winfo_width()
        h = self.winfo_height()
        sw = self.winfo_screenwidth()
        sh = self.winfo_screenheight()
        self.geometry(f"+{max(0, (sw - w) // 2)}+{max(0, (sh - h) // 2)}")
        print("[CHECKPOINT] update_idletasks() OK. Panggil lift()...", flush=True)
        self.lift()
        print("[CHECKPOINT] lift() OK. Set -topmost...", flush=True)
        self.attributes("-topmost", True)
        print("[CHECKPOINT] -topmost OK. Panggil grab_set()...", flush=True)
        self.after(50, lambda: self.attributes("-topmost", False))
        self.grab_set()
        print("[CHECKPOINT] grab_set() OK. Panggil focus_force()...", flush=True)
        self.focus_force()
        print("[CHECKPOINT] focus_force() OK. AuthDialog.__init__ SELESAI TOTAL.", flush=True)
        (username_entry if self.is_setup_mode else pw_entry).focus_set()

    def _submit(self):
        username = self.username_var.get().strip()
        password = self.password_var.get()

        if self.is_setup_mode:
            password2 = self.password2_var.get()
            if not username or not password:
                self.error_label.configure(text="Username & password wajib diisi.")
                return
            if password != password2:
                self.error_label.configure(text="Password tidak sama.")
                return
            salt = secrets.token_bytes(16)
            state.config["auth_username"] = username
            state.config["auth_password_salt"] = salt.hex()
            state.config["auth_password_hash"] = hash_password(password, salt)
            save_config(state.config)
            self.destroy()
            self.on_success()
            return

        stored_username = state.config.get("auth_username", "")
        stored_salt = state.config.get("auth_password_salt", "")
        stored_hash = state.config.get("auth_password_hash", "")
        if username == stored_username and verify_password(password, stored_salt, stored_hash):
            self.destroy()
            self.on_success()
        else:
            self.error_label.configure(text="Username atau password salah.")
            self.password_var.set("")

    def _on_cancel(self):
        self.destroy()
        # Tanpa login berhasil, aplikasi (dan server WebSocket-nya) ditutup.
        self.master.destroy()


class ChangePasswordDialog(tk.Toplevel):
    """Ganti password aplikasi — minta password lama dulu untuk verifikasi."""

    def __init__(self, master):
        super().__init__(master)
        self.title("Ganti Password Aplikasi")
        self.resizable(False, False)

        pad = {"padx": 14, "pady": 6}
        ttk.Label(self, text="Password Lama").grid(row=0, column=0, sticky="w", **pad)
        self.old_var = tk.StringVar()
        ttk.Entry(self, textvariable=self.old_var, show="*", width=24).grid(row=0, column=1, **pad)

        ttk.Label(self, text="Password Baru").grid(row=1, column=0, sticky="w", **pad)
        self.new_var = tk.StringVar()
        ttk.Entry(self, textvariable=self.new_var, show="*", width=24).grid(row=1, column=1, **pad)

        ttk.Label(self, text="Ulangi Password Baru").grid(row=2, column=0, sticky="w", **pad)
        self.new2_var = tk.StringVar()
        ttk.Entry(self, textvariable=self.new2_var, show="*", width=24).grid(row=2, column=1, **pad)

        self.error_label = ttk.Label(self, text="", foreground="#c0392b")
        self.error_label.grid(row=3, column=0, columnspan=2)

        btn_frame = ttk.Frame(self)
        btn_frame.grid(row=4, column=0, columnspan=2, pady=10)
        ttk.Button(btn_frame, text="Simpan", command=self._save).pack(side="left", padx=6)
        ttk.Button(btn_frame, text="Batal", command=self.destroy).pack(side="left", padx=6)

        self.transient(master)
        self.grab_set()

    def _save(self):
        stored_salt = state.config.get("auth_password_salt", "")
        stored_hash = state.config.get("auth_password_hash", "")
        if not verify_password(self.old_var.get(), stored_salt, stored_hash):
            self.error_label.configure(text="Password lama salah.")
            return
        if not self.new_var.get():
            self.error_label.configure(text="Password baru tidak boleh kosong.")
            return
        if self.new_var.get() != self.new2_var.get():
            self.error_label.configure(text="Password baru tidak sama.")
            return
        salt = secrets.token_bytes(16)
        state.config["auth_password_salt"] = salt.hex()
        state.config["auth_password_hash"] = hash_password(self.new_var.get(), salt)
        save_config(state.config)
        messagebox.showinfo("BayzBot", "Password berhasil diganti.")
        self.destroy()


class UnlockDialog(tk.Toplevel):
    """
    Dialog KUNCI LAYAR — dipicu tombol "Kunci" di Control Panel. BEDA
    dari AuthDialog/Logout: hanya minta PASSWORD (username tidak perlu
    diketik ulang, cukup verifikasi terhadap akun yang sedang login).
    Server WebSocket & playback TETAP berjalan di background selama
    terkunci — cuma window Control Panel yang disembunyikan.
    """

    def __init__(self, master, on_success):
        super().__init__(master)
        self.on_success = on_success
        self.title("BayzBot - Terkunci")
        self.resizable(False, False)
        self.protocol("WM_DELETE_WINDOW", self._on_cancel)

        pad = {"padx": 14, "pady": 6}
        ttk.Label(self, text="Control Panel terkunci.").grid(
            row=0, column=0, columnspan=2, sticky="w", **pad
        )
        ttk.Label(
            self, text=f"Login sebagai: {state.config.get('auth_username', '-')}",
            foreground="#777777",
        ).grid(row=1, column=0, columnspan=2, sticky="w", padx=14)

        ttk.Label(self, text="Password").grid(row=2, column=0, sticky="w", **pad)
        self.password_var = tk.StringVar()
        pw_entry = ttk.Entry(self, textvariable=self.password_var, show="*", width=24)
        pw_entry.grid(row=2, column=1, **pad)
        pw_entry.bind("<Return>", lambda e: self._submit())

        self.error_label = ttk.Label(self, text="", foreground="#c0392b")
        self.error_label.grid(row=3, column=0, columnspan=2)

        btn_frame = ttk.Frame(self)
        btn_frame.grid(row=4, column=0, columnspan=2, pady=10)
        ttk.Button(btn_frame, text="Buka", command=self._submit).pack(side="left", padx=6)
        ttk.Button(btn_frame, text="Keluar Aplikasi", command=self._on_cancel).pack(side="left", padx=6)

        # NOTE: sama seperti AuthDialog di atas -- SENGAJA TIDAK memanggil
        # self.transient(master), karena master (App) sedang withdrawn
        # saat dialog ini dibuat. Toplevel independen memastikan window
        # manager selalu mau menampilkannya (lihat catatan panjang di
        # AuthDialog.__init__ untuk penjelasan akar masalahnya).
        self.deiconify()
        self.update_idletasks()
        w, h = self.winfo_width(), self.winfo_height()
        sw, sh = self.winfo_screenwidth(), self.winfo_screenheight()
        self.geometry(f"+{max(0, (sw - w) // 2)}+{max(0, (sh - h) // 2)}")
        self.lift()
        self.attributes("-topmost", True)
        self.after(50, lambda: self.attributes("-topmost", False))
        self.grab_set()
        self.focus_force()
        pw_entry.focus_set()

    def _submit(self):
        stored_salt = state.config.get("auth_password_salt", "")
        stored_hash = state.config.get("auth_password_hash", "")
        if verify_password(self.password_var.get(), stored_salt, stored_hash):
            self.destroy()
            self.on_success()
        else:
            self.error_label.configure(text="Password salah.")
            self.password_var.set("")

    def _on_cancel(self):
        self.destroy()
        # Tanpa unlock berhasil, satu-satunya pilihan aman adalah keluar
        # aplikasi sepenuhnya (sama seperti AuthDialog._on_cancel).
        self.master.destroy()


class App(tk.Tk):
    def __init__(self, event_queue: "queue.Queue"):
        print("[CHECKPOINT] App.__init__ mulai (super().__init__())...", flush=True)
        super().__init__()
        print("[CHECKPOINT] tk.Tk() berhasil dibuat.", flush=True)
        self.event_queue = event_queue
        self.title("BayzBot Control Panel")
        self.geometry("540x600")
        self.resizable(False, False)
        self.playlist_window: PlaylistWindow | None = None
        self.withdraw()  # sembunyikan sampai berhasil LOGIN APLIKASI
        print("[CHECKPOINT] root.withdraw() selesai.", flush=True)

        self._build_ui()
        print("[CHECKPOINT] _build_ui() selesai.", flush=True)
        self.after(150, self._poll_events)
        self.protocol("WM_DELETE_WINDOW", self._on_close)

        if state.config.get("last_playlist_path") and os.path.isfile(state.config["last_playlist_path"]):
            self._load_playlist_file(state.config["last_playlist_path"], silent=True)
        print("[CHECKPOINT] cek playlist path selesai. Membuat AuthDialog...", flush=True)

        # Gerbang login APLIKASI (bukan akun Google) — window baru
        # ditampilkan setelah ini berhasil.
        AuthDialog(self, on_success=self.deiconify)
        print("[CHECKPOINT] AuthDialog(...) selesai dipanggil (constructor return).", flush=True)

    # ------------------------------------------------------------
    def _build_ui(self):
        pad = {"padx": 12, "pady": 6}

        top = ttk.Frame(self)
        top.pack(fill="x", **pad)
        self.conn_label = ttk.Label(top, text="\u25cf Backend belum ada koneksi", foreground="#999999")
        self.conn_label.pack(side="left")
        self.ram_label = ttk.Label(top, text="RAM: -")
        self.ram_label.pack(side="left", padx=12)
        
        # Port info
        current_port = state.config.get("ws_port", DEFAULT_PORT)
        port_info = f"Port: {current_port}"
        self.port_label = ttk.Label(top, text=port_info, foreground="#666666", font=("TkDefaultFont", 9))
        self.port_label.pack(side="left", padx=12)
        
        # Shuffle flag status
        shuffle_status = "🔀 Acak: ON" if state.config.get("shuffle_playlist", True) else "🔀 Acak: OFF"
        self.shuffle_status_label = ttk.Label(top, text=shuffle_status, foreground="#666666", font=("TkDefaultFont", 9))
        self.shuffle_status_label.pack(side="left", padx=12)
        
        # Buttons frame (right side)
        buttons_frame = ttk.Frame(top)
        buttons_frame.pack(side="right", padx=6)
        ttk.Button(buttons_frame, text="\U0001F512", command=self._lock_app, width=2).pack(side="left", padx=2)
        ttk.Button(buttons_frame, text="⚙️", command=self._open_options, width=2).pack(side="left", padx=2)
        ttk.Button(buttons_frame, text="Exit", command=self._logout_app, width=5).pack(side="left", padx=2)
        
        # IP and Proxy info frame (below main frame)
        info_frame = ttk.Frame(self)
        info_frame.pack(fill="x", padx=12, pady=(0, 6))
        
        # IP and Country Flag
        self.ip_label = ttk.Label(info_frame, text="IP: memuat...", foreground="#666666", font=("TkDefaultFont", 9))
        self.ip_label.pack(side="left", padx=12)
        
        # Proxy status
        proxy_status = "🌐 Proxy: ON" if state.config.get("use_proxy") else "🌐 Proxy: OFF"
        self.proxy_label = ttk.Label(info_frame, text=proxy_status, foreground="#666666", font=("TkDefaultFont", 9))
        self.proxy_label.pack(side="left", padx=12)
        # Update IP in background
        self.after(100, self._update_ip_display)

        # ------------------------------------------------------------
        # Pemilih INSTANCE Chrome yang dikendalikan. Perlu kalau lebih
        # dari satu Chrome/ekstensi kebetulan tersambung ke backend yang
        # sama (mis. lagi tes beberapa Chrome sekaligus di 1 PC). Kalau
        # cuma 1 instance yang konek, ini auto-terpilih & tidak perlu
        # disentuh -- tidak mengubah pemakaian normal sehari-hari.
        # ------------------------------------------------------------
        instance_frame = ttk.Frame(self)
        instance_frame.pack(fill="x", padx=12, pady=(0, 6))
        ttk.Label(instance_frame, text="Instance Chrome aktif:").pack(side="left")
        self._instance_display_to_id: dict[str, str | None] = {}
        self.instance_var = tk.StringVar(value="")
        self.instance_combo = ttk.Combobox(
            instance_frame, textvariable=self.instance_var, state="readonly", width=36, values=[]
        )
        self.instance_combo.pack(side="left", padx=8)
        self.instance_combo.bind("<<ComboboxSelected>>", self._on_instance_selected)
        self.instance_count_label = ttk.Label(instance_frame, text="(0 instance tersambung)", foreground="#777777")
        self.instance_count_label.pack(side="left", padx=6)

        auth_frame = ttk.LabelFrame(self, text="Akun Google (YouTube Music)")
        auth_frame.pack(fill="x", **pad)
        ttk.Button(auth_frame, text="Buka Login Google", command=self._do_login).pack(side="left", padx=8, pady=8)
        ttk.Button(auth_frame, text="Buka Logout Google", command=self._do_logout).pack(side="left", padx=8, pady=8)
        ttk.Label(
            auth_frame,
            text="Membawa tab YouTube Music ke depan ke halaman login/logout Google.\n"
                 "Login dilakukan manual di browser (BayzBot tidak menyimpan password Google).",
            foreground="#666666", wraplength=380, justify="left",
        ).pack(side="left", padx=8)

        pl_frame = ttk.LabelFrame(self, text="Playlist (list URL lagu YouTube Music)")
        pl_frame.pack(fill="x", **pad)

        row1 = ttk.Frame(pl_frame)
        row1.pack(fill="x", padx=8, pady=(8, 4))
        ttk.Button(row1, text="Load playlist.txt", command=self._browse_playlist).pack(side="left")
        ttk.Button(row1, text="Refresh", command=self._refresh_playlist).pack(side="left", padx=6)
        ttk.Button(row1, text="Show Playlist", command=self._show_playlist).pack(side="left", padx=6)
        self.playlist_label = ttk.Label(row1, text="Belum ada playlist di-load")
        self.playlist_label.pack(side="left", padx=8)

        row2 = ttk.Frame(pl_frame)
        row2.pack(fill="x", padx=8, pady=(4, 4))
        self.play_btn = ttk.Button(row2, text="\u25b6 Play", command=self._play_playlist)
        self.play_btn.pack(side="left")
        self.stop_btn = ttk.Button(row2, text="\u25a0 Stop", command=self._stop_playlist)
        self.stop_btn.pack(side="left", padx=6)
        ttk.Button(row2, text="\u21bb Restart", command=self._restart_song).pack(side="left", padx=6)
        ttk.Button(row2, text="\u25c0 Prev", command=self._previous_song).pack(side="left", padx=6)
        ttk.Button(row2, text="Next \u25b6", command=self._next_song).pack(side="left", padx=6)

        row3 = ttk.Frame(pl_frame)
        row3.pack(fill="x", padx=8, pady=(4, 8))
        self.auto_run_var = tk.BooleanVar(value=state.config.get("auto_run", False))
        ttk.Checkbutton(
            row3, text="Auto run (mulai otomatis saat ekstensi konek)",
            variable=self.auto_run_var, command=self._toggle_auto_run,
        ).pack(side="left")
        self.shuffle_var = tk.BooleanVar(value=state.config.get("shuffle_playlist", True))
        ttk.Checkbutton(
            row3, text="\U0001F500 Acak urutan lagu",
            variable=self.shuffle_var, command=self._toggle_shuffle,
        ).pack(side="left", padx=(16, 0))

        status_frame = ttk.LabelFrame(self, text="Status")
        status_frame.pack(fill="x", **pad)
        self.loop_status_label = ttk.Label(status_frame, text="Jumlah putar playlist: 0")
        self.loop_status_label.pack(anchor="w", padx=8, pady=(8, 2))
        self.song_status_label = ttk.Label(status_frame, text="Sedang main: -")
        self.song_status_label.pack(anchor="w", padx=8, pady=(2, 8))

        log_frame = ttk.LabelFrame(self, text="Log")
        log_frame.pack(fill="both", expand=True, **pad)
        log_header = ttk.Frame(log_frame)
        log_header.pack(fill="x", padx=6, pady=(6, 0))
        ttk.Button(log_header, text="Clear Log", command=self._clear_log).pack(side="right")
        self.log_text = tk.Text(log_frame, height=10, state="disabled", wrap="word")
        self.log_text.pack(fill="both", expand=True, padx=6, pady=6)

    # ------------------------------------------------------------
    def _log(self, text: str):
        ts = datetime.now().strftime("%H:%M:%S")
        self.log_text.configure(state="normal")
        self.log_text.insert("end", f"[{ts}] {text}\n")
        self.log_text.see("end")
        self.log_text.configure(state="disabled")

    def _clear_log(self):
        self.log_text.configure(state="normal")
        self.log_text.delete("1.0", "end")
        self.log_text.configure(state="disabled")

    def _update_ip_display(self):
        """Update IP and country flag display in background."""
        try:
            ip_display = get_ip_display()
            self.ip_label.configure(text=f"IP: {ip_display}")
            
            # Broadcast config to all connected clients
            run_coro_threadsafe(broadcast_all({
                "type": "config",
                "config": {
                    "shuffle": state.config.get("shuffle_playlist", True),
                    "autoRun": state.config.get("auto_run", False),
                    "autoSkip": True,
                    "proxy": state.config.get("use_proxy", False),
                    "ip": ip_display,
                }
            }))
        except Exception:
            self.ip_label.configure(text="IP: tidak terdeteksi")
        
        self.after(30000, self._update_ip_display)  # Update every 30 seconds

    # ------------------------------------------------------------
    # Pemilih instance Chrome (multi-instance routing)
    # ------------------------------------------------------------
    def _refresh_instance_picker(self, data: dict):
        clients = data.get("clients", [])
        selected_id = data.get("selected_instance_id")

        # Cuma tampilkan klien yang SUDAH handshake (instance_id terisi).
        # Klien yang baru connect TCP tapi belum kirim "hello" akan
        # muncul sendiri begitu handshake-nya selesai (biasanya <1 detik) --
        # menampilkannya sebagai "belum-handshake" di dropdown cuma bikin
        # bingung dan sempat menyebabkan bug tampilan basi.
        handshaken = [c for c in clients if c.get("instance_id")]

        self._instance_display_to_id = {}
        display_values = []
        selected_display = ""
        for c in handshaken:
            inst_id = c.get("instance_id")
            label = c.get("label")
            short_id = inst_id[:8]
            display = f"{label} ({short_id})" if label else short_id
            # Hindari tabrakan kalau ada 2 display string identik
            base_display = display
            n = 2
            while display in self._instance_display_to_id:
                display = f"{base_display} #{n}"
                n += 1
            self._instance_display_to_id[display] = inst_id
            display_values.append(display)
            if inst_id == selected_id and selected_id is not None:
                selected_display = display

        self.instance_combo.configure(values=display_values)
        pending = len(clients) - len(handshaken)
        count_text = f"({len(handshaken)} instance tersambung"
        if pending > 0:
            count_text += f", {pending} sedang handshake"
        count_text += ")"
        self.instance_count_label.configure(text=count_text)

        if selected_display:
            self.instance_var.set(selected_display)
        elif len(display_values) == 1:
            self.instance_var.set(display_values[0])
        else:
            # 0 kandidat, ATAU >1 kandidat tapi belum ada yang dipilih --
            # KEDUANYA harus mengosongkan tampilan (jangan sampai
            # menyisakan teks pilihan lama/basi dari state sebelumnya).
            # Untuk kasus >1 kandidat, ini sengaja dibiarkan kosong supaya
            # user SADAR harus memilih manual, bukan diam-diam salah satu
            # dipilihkan otomatis.
            self.instance_var.set("")

    def _on_instance_selected(self, _event=None):
        display = self.instance_var.get()
        inst_id = self._instance_display_to_id.get(display)
        state.selected_instance_id = inst_id
        self._log(f"Instance aktif diganti ke: {display}")

    def _poll_events(self):
        try:
            while True:
                ev = self.event_queue.get_nowait()
                self._handle_event(ev)
        except queue.Empty:
            pass
        self.after(150, self._poll_events)

    def _handle_event(self, ev: dict):
        kind = ev["kind"]
        data = ev["data"]
        if kind == "log":
            self._log(data.get("text", ""))
        elif kind == "conn_changed":
            connected = data.get("connected", False)
            if connected:
                self.conn_label.configure(text="\u25cf Ekstensi tersambung", foreground="#2e8b57")
            else:
                self.conn_label.configure(text="\u25cf Ekstensi terputus", foreground="#c0392b")
        elif kind == "status":
            title = data.get("title") or "-"
            artist = data.get("artist") or ""
            playing = "Playing" if data.get("isPlaying") else "Paused"
            self.song_status_label.configure(text=f"Sedang main: {title} - {artist} ({playing})")
        elif kind == "loop_changed":
            self.loop_status_label.configure(text=f"Jumlah putar playlist: {data.get('loop_count', 0)}")
        elif kind == "running_changed":
            is_running = data.get("is_running", False)
            self.play_btn.configure(state="disabled" if is_running else "normal")
            self.stop_btn.configure(state="normal" if is_running else "disabled")
        elif kind == "ram_status":
            percent = data.get("percent")
            if percent is None:
                self.ram_label.configure(text="RAM: -", foreground="#333333")
            else:
                self.ram_label.configure(
                    text=f"RAM: {percent:.0f}%",
                    foreground="#c0392b" if percent > RAM_ALERT_THRESHOLD else "#333333",
                )
        elif kind == "playlist_stats_changed":
            if self.playlist_window is not None and self.playlist_window.winfo_exists():
                self.playlist_window.refresh()
        elif kind == "clients_changed":
            self._refresh_instance_picker(data)

    # ------------------------------------------------------------
    def _browse_playlist(self):
        path = filedialog.askopenfilename(
            title="Pilih playlist.txt", filetypes=[("Text file", "*.txt"), ("Semua file", "*.*")]
        )
        if path:
            self._load_playlist_file(path)

    def _load_playlist_file(self, path: str, silent: bool = False):
        try:
            with open(path, "r", encoding="utf-8") as f:
                urls = [line.strip() for line in f if line.strip().lower().startswith("http")]
        except Exception as e:
            messagebox.showerror("BayzBot", f"Gagal membaca file: {e}")
            return
        if not urls:
            messagebox.showwarning("BayzBot", "Tidak ada URL yang ditemukan di file itu.")
            return
        state.playlist_urls = urls
        if not state.is_running:
            state.play_order = []
        state.playlist_path = path
        state.config["last_playlist_path"] = path
        save_config(state.config)
        self.playlist_label.configure(text=f"{os.path.basename(path)} ({len(urls)} URL)")
        if not silent:
            self._log(f"Playlist di-load: {os.path.basename(path)} ({len(urls)} URL)")
        
        # Fetch YouTube titles di background untuk setiap URL
        def _fetch_titles():
            for url in urls:
                if url not in state.song_stats:
                    data = fetch_youtube_title(url)
                    if data:
                        state.song_stats[url] = {
                            "title": data["title"],
                            "artist": data["artist"],
                            "play_count": 0,
                            "status": "Menunggu"
                        }
                time.sleep(0.2)  # Hindari rate limiting
        
        t = threading.Thread(target=_fetch_titles, daemon=True)
        t.start()

    def _refresh_playlist(self):
        if not state.playlist_path:
            messagebox.showwarning("BayzBot", "Belum ada file playlist yang di-load.")
            return
        self._load_playlist_file(state.playlist_path)
        self._log("Playlist di-refresh ulang dari file.")

    def _show_playlist(self):
        if self.playlist_window is not None and self.playlist_window.winfo_exists():
            self.playlist_window.refresh()
            self.playlist_window.lift()
            return
        self.playlist_window = PlaylistWindow(self)

    def _play_playlist(self):
        if not state.playlist_urls:
            messagebox.showwarning("BayzBot", "Load playlist.txt dulu.")
            return
        state.loop_count = 0
        self.loop_status_label.configure(text="Jumlah putar playlist: 0")
        run_coro_threadsafe(start_playlist_playback())

    def _stop_playlist(self):
        run_coro_threadsafe(stop_playlist_playback("Dihentikan manual dari GUI"))

    def _restart_song(self):
        run_coro_threadsafe(send_to_selected({"type": "command", "action": "restart"}))
        self._log("Mengulang lagu saat ini dari awal...")

    def _next_song(self):
        run_coro_threadsafe(send_to_selected({"type": "command", "action": "next"}))
        self._log("Pindah ke URL berikutnya di playlist...")

    def _previous_song(self):
        run_coro_threadsafe(send_to_selected({"type": "command", "action": "previous"}))
        self._log("Pindah ke URL sebelumnya di playlist...")

    def _toggle_shuffle(self):
        state.config["shuffle_playlist"] = self.shuffle_var.get()
        save_config(state.config)
        
        # Update status label di top bar
        shuffle_status = "🔀 Acak: ON" if self.shuffle_var.get() else "🔀 Acak: OFF"
        self.shuffle_status_label.configure(text=shuffle_status)
        
        # Broadcast config change to all clients
        run_coro_threadsafe(broadcast_all({
            "type": "config",
            "config": {
                "shuffle": self.shuffle_var.get(),
                "autoRun": state.config.get("auto_run", False),
                "autoSkip": True,
                "proxy": state.config.get("use_proxy", False),
            }
        }))
        
        if state.is_running:
            self._log("Mode acak " + ("DIAKTIFKAN" if self.shuffle_var.get() else "DIMATIKAN")
                      + " - berlaku mulai loop berikutnya.")
        else:
            self._log("Mode acak " + ("DIAKTIFKAN." if self.shuffle_var.get() else "DIMATIKAN."))

    def _toggle_auto_run(self):
        state.config["auto_run"] = self.auto_run_var.get()
        save_config(state.config)

    def _do_login(self):
        run_coro_threadsafe(send_to_selected({"type": "command", "action": "login"}))
        self._log("Membawa tab YouTube Music ke depan untuk login Google...")

    def _do_logout(self):
        run_coro_threadsafe(send_to_selected({"type": "command", "action": "logout"}))
        self._log("Membawa tab YouTube Music ke depan untuk logout Google...")
        messagebox.showinfo(
            "BayzBot",
            "Ini akan logout SEMUA akun Google di profil Chrome tersebut, "
            "bukan cuma YouTube Music."
        )

    def _logout_app(self):
        """Logout dari APLIKASI BayzBot Control Panel (bukan akun Google).
        Server WebSocket & playback TETAP berjalan di background — cuma
        GUI-nya yang dikunci sampai login lagi."""
        self._log("Logout dari aplikasi. Perlu login lagi untuk buka Control Panel.")
        self.withdraw()
        AuthDialog(self, on_success=self.deiconify)

    def _lock_app(self):
        """Kunci Control Panel (lebih cepat dari Logout — cuma minta
        password, username tidak perlu diketik ulang). Server WebSocket &
        playback TETAP berjalan di background selama terkunci."""
        self._log("Control Panel dikunci. Masukkan password untuk membuka lagi.")
        self.withdraw()
        UnlockDialog(self, on_success=self.deiconify)

    def _open_options(self):
        def _on_save(cfg):
            self._log("Pengaturan disimpan.")
            # Update port display
            current_port = cfg.get("ws_port", DEFAULT_PORT)
            self.port_label.configure(text=f"Port: {current_port}")
            
            # Update proxy status
            proxy_status = "🌐 Proxy: ON" if cfg.get("use_proxy") else "🌐 Proxy: OFF"
            self.proxy_label.configure(text=proxy_status)
            
            # Update shuffle status
            shuffle_status = "🔀 Acak: ON" if cfg.get("shuffle_playlist", True) else "🔀 Acak: OFF"
            self.shuffle_status_label.configure(text=shuffle_status)
            
            run_coro_threadsafe(send_to_selected({
                "type": "command", "action": "setConfig",
                "adSkipDelaySeconds": cfg.get("ad_skip_delay_seconds", 0),
            }))
        OptionsWindow(self, state.config, on_save=_on_save)

    def _on_close(self):
        if state.is_running:
            notify_telegram(
                f"[BayzBot] Aplikasi ditutup saat playlist sedang berjalan.\n"
                f"Total loop yang sempat berjalan: {state.loop_count}"
            )
        self.destroy()


# ---------------------------------------------------------------------------
# Auto-run: dipantau lewat event queue, dipicu saat conn_changed=connected
# ---------------------------------------------------------------------------

_auto_run_triggered_once = False


def maybe_auto_run(app: "App", ev: dict):
    global _auto_run_triggered_once
    if ev["kind"] != "conn_changed" or not ev["data"].get("connected"):
        return
    if not state.config.get("auto_run"):
        return
    if not state.playlist_urls:
        return
    if _auto_run_triggered_once or state.is_running:
        return
    _auto_run_triggered_once = True
    app._log("Auto run aktif: memulai playlist otomatis...")
    app._play_playlist()


def main():
    global state
    if "--gui" not in sys.argv[1:]:
        state = BackendState(None)
        asyncio.run(run_server())
        return

    print("[CHECKPOINT] main() mulai.", flush=True)
    event_queue = queue.Queue()
    state = BackendState(event_queue)
    print("[CHECKPOINT] BackendState dibuat.", flush=True)

    start_backend_thread()
    print("[CHECKPOINT] start_backend_thread() dipanggil (server WS di thread lain).", flush=True)

    app = App(event_queue)
    print("[CHECKPOINT] App(event_queue) selesai dibuat. Masuk mainloop()...", flush=True)

    original_handle = app._handle_event

    def patched_handle(ev):
        original_handle(ev)
        maybe_auto_run(app, ev)

    app._handle_event = patched_handle

    app.mainloop()
    print("[CHECKPOINT] mainloop() SELESAI (window ditutup/di-destroy).", flush=True)


if __name__ == "__main__":
    try:
        main()
    except Exception:
        import traceback
        print("[FATAL ERROR] Exception tidak tertangani:", flush=True)
        traceback.print_exc()
        if "--gui" in sys.argv[1:]:
            input("\nTekan Enter untuk menutup...")