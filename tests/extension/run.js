const h = require("./content.ad.test.js");
const { makeEnv, sleep, scenario, eq, URLS } = h;
const ACT = 400; // (T + offset) dalam ms

(async () => {
  await scenario("S14: tanpa iklan -> tidak ada event iklan", async () => {
    const e = await makeEnv();
    await sleep(300);
    const adEv = e.names().filter((n) => /^ad/.test(n));
    e.close();
    eq(adEv.length, 0, "event iklan");
  });

  await scenario("S1: iklan pendek (<=T) + tombol Skip -> tidak diklik, tidak reload", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 0.2, button: true });
    await sleep(ACT + 300);
    const clicks = e.button().__clicks;
    e.endAdNow();
    await sleep(150);
    const r = [clicks, e.count("adForceReload"), e.navs.length, e.last("adDecision")?.action, e.count("adEnded")];
    e.close();
    eq(r[0], 0, "klik"); eq(r[1], 0, "reload"); eq(r[2], 0, "navigasi"); eq(r[3], "play_full", "keputusan"); eq(r[4], 1, "adEnded");
  });

  await scenario("S2: iklan panjang + tombol Skip -> tidak diklik sebelum T+1, diklik sesudahnya", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 180, button: true });
    e.button().__works = true;
    await sleep(ACT - 150);
    const early = e.button()?.__clicks ?? 0;
    const waiting = e.count("adWaiting");
    await sleep(400);
    const clicked = e.count("adSkipClicked");
    await sleep(150);
    const out = [early, waiting, clicked, e.count("adForceReload"), e.navs.length];
    e.close();
    eq(out[0], 0, "klik dini"); eq(out[1], 1, "adWaiting"); eq(out[2], 1, "adSkipClicked"); eq(out[3], 0, "reload"); eq(out[4], 0, "navigasi");
  });

  await scenario("S3: iklan1 pendek lalu iklan2 panjang tanpa tombol -> reload ke URL playlist tepat 1x", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 0.2, src: "ad1.mp4" });
    await sleep(200);
    e.showAd({ duration: 180, src: "ad2.mp4" }); // berurutan tanpa jeda
    await sleep(ACT + 400);
    const out = [e.count("adDetected"), e.count("adForceReload"), e.navs.slice(), e.last("adForceReload")?.reason, e.last("adForceReload")?.index];
    await sleep(300);
    const navsLater = e.navs.length;
    e.close();
    eq(out[0], 2, "adDetected"); eq(out[1], 1, "adForceReload"); eq(out[2].length, 1, "navigasi");
    eq(out[2][0], URLS[0], "URL navigasi"); eq(out[3], "tanpa-tombol-skip", "alasan"); eq(out[4], 2, "indeks iklan"); eq(navsLater, 1, "navigasi lanjutan");
  });

  await scenario("S4: tombol ada, klik tak mempan -> 3 percobaan -> adSkipFailed -> reload skip-gagal", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 180, button: true });
    await sleep(ACT + 150 * 4 + 300);
    const out = [e.count("adSkipClicked"), e.count("adSkipFailed"), e.last("adForceReload")?.reason, e.navs.length];
    e.close();
    eq(out[0], 3, "klik"); eq(out[1], 1, "adSkipFailed"); eq(out[2], "skip-gagal", "alasan"); eq(out[3], 1, "navigasi");
  });

  await scenario("S5: tombol aria-disabled terus -> reload tombol-nonaktif", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 180, button: true, disabled: true });
    await sleep(ACT + 300 + 250);
    const out = [e.count("adSkipClicked"), e.last("adForceReload")?.reason, e.navs.length];
    e.close();
    eq(out[0], 0, "klik"); eq(out[1], "tombol-nonaktif", "alasan"); eq(out[2], 1, "navigasi");
  });

  await scenario("S6: batas muat ulang 2x -> iklan C: adReloadLimit tanpa navigasi", async () => {
    const e = await makeEnv({ session: [Date.now() - 1000, Date.now() - 500] });
    e.showAd({ duration: 180 });
    await sleep(ACT + 300);
    const out = [e.count("adForceReload"), e.count("adReloadLimit"), e.navs.length];
    e.close();
    eq(out[0], 0, "reload"); eq(out[1], 1, "adReloadLimit"); eq(out[2], 0, "navigasi");
  });

  await scenario("S6b: dua reload berturut (iklan A, B) lalu batas", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 180 });
    await sleep(ACT + 200);
    e.endAdNow(); await sleep(150);
    e.showAd({ duration: 180 });
    await sleep(ACT + 200);
    e.endAdNow(); await sleep(150);
    e.showAd({ duration: 180 });
    await sleep(ACT + 200);
    const out = [e.count("adForceReload"), e.count("adReloadLimit"), e.navs.length];
    e.close();
    eq(out[0], 2, "reload"); eq(out[1], 1, "adReloadLimit"); eq(out[2], 2, "navigasi");
  });

  await scenario("S7: iklan di ujung lagu + panjang tanpa tombol -> navigasi ke lagu BERIKUTNYA", async () => {
    const e = await makeEnv();
    e.m.duration = 200; e.m.currentTime = 198.5;
    await sleep(120); // snapshot lagu terambil
    e.showAd({ duration: 180 });
    await sleep(ACT + 300);
    const out = [e.count("adForceReload"), e.count("adAfterSong"), e.navs.slice(), e.store.playbackState.index];
    e.close();
    eq(out[0], 1, "reload"); eq(out[1], 1, "adAfterSong"); eq(out[2][0], URLS[1], "URL"); eq(out[3], 1, "indeks tersimpan");
  });

  await scenario("S8: durasi tak terbaca (Infinity) -> adDecision assumed lalu aksi pada T+1", async () => {
    const e = await makeEnv();
    e.showAd({ duration: Infinity });
    await sleep(ACT - 150);
    const before = e.count("adForceReload");
    await sleep(350);
    const d = e.last("adDecision");
    const out = [before, d?.assumed, d?.action, e.count("adForceReload")];
    e.close();
    eq(out[0], 0, "reload dini"); eq(out[1], true, "assumed"); eq(out[2], "skip", "aksi"); eq(out[3], 1, "reload");
  });

  await scenario("S9: dua iklan pendek berturut lalu satu panjang -> hanya yang panjang ditindak", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 0.2, src: "a1.mp4", button: true });
    await sleep(150);
    e.showAd({ duration: 0.25, src: "a2.mp4", button: true });
    await sleep(150);
    e.showAd({ duration: 180, src: "a3.mp4" });
    await sleep(ACT + 300);
    const acts = e.events.filter((x) => x.event === "adDecision").map((x) => x.action);
    const out = [acts.join(","), e.count("adSkipClicked"), e.count("adForceReload"), e.last("adForceReload")?.index];
    e.close();
    eq(out[0], "play_full,play_full,skip", "keputusan"); eq(out[1], 0, "klik"); eq(out[2], 1, "reload"); eq(out[3], 3, "indeks");
  });

  await scenario("S10: iklan > T tetapi berakhir sebelum T+1 -> tidak ada klik/reload", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 180, button: true });
    await sleep(200);
    e.endAdNow();
    await sleep(ACT + 200);
    const out = [e.count("adSkipClicked"), e.count("adForceReload"), e.navs.length, e.count("adEnded")];
    e.close();
    eq(out[0], 0, "klik"); eq(out[1], 0, "reload"); eq(out[2], 0, "navigasi"); eq(out[3], 1, "adEnded");
  });

  await scenario("S11: setelah iklan URL bukan lagu playlist -> dikoreksi, lalu lewati lagu", async () => {
    const e = await makeEnv();
    await sleep(300); // lewati masa tenggang awal
    e.win.history.pushState({}, "", "/watch?v=ZZZZZZZZZZZ");
    await sleep(500);
    const first = [e.count("trackMismatch"), e.navs.length];
    // koreksi tak berhasil (navigasi dimock) -> tetap salah
    await sleep(1800);
    const names = e.names();
    const failAt = names.indexOf("trackMismatchFailed");
    const before = names.slice(0, failAt).filter((n) => n === "trackMismatch").length;
    const out = [first[0], first[1], before, failAt, e.store.playbackState.index];
    e.close();
    eq(out[0] >= 1, true, "trackMismatch"); eq(out[1] >= 1, true, "navigasi koreksi");
    eq(out[3] >= 0, true, "trackMismatchFailed muncul"); eq(out[2], 3, "tepat 3 koreksi sebelum lagu dilewati"); eq(out[4] >= 1, true, "lagu dilewati");
  });

  await scenario("S12: deteksi iklan ke-2 via currentSrc / durasi / waktu mundur", async () => {
    for (const mode of ["src", "dur", "time"]) {
      const e = await makeEnv();
      e.showAd({ duration: 180, src: "a1.mp4" });
      await sleep(120);
      e.m.currentTime = 50;
      await sleep(70);
      if (mode === "src") e.m.currentSrc = "a2.mp4";
      if (mode === "dur") e.m.duration = 40;
      if (mode === "time") e.m.currentTime = 2;
      await sleep(120);
      const n = e.count("adDetected");
      e.close();
      eq(n, 2, "adDetected mode " + mode);
    }
  });

  await scenario("S13: reload tidak berulang untuk iklan yang sama", async () => {
    const e = await makeEnv();
    e.showAd({ duration: 180 });
    await sleep(ACT + 1200);
    const out = [e.count("adForceReload"), e.navs.length, e.count("adReloadLimit")];
    e.close();
    eq(out[0], 1, "reload"); eq(out[1], 1, "navigasi"); eq(out[2], 0, "adReloadLimit");
  });

  const { pass, fail } = h.report();
  console.log(`\n${pass} lolos, ${fail} gagal`);
  process.exit(fail ? 1 : 0);
})();
