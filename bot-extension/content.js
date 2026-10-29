(() => {
  // Jangan jalankan dua instance di tab yang sama (injeksi manual + manifest). Instance lama yang
  // konteks ekstensinya sudah tidak valid (ekstensi di-reload) tidak dihitung.
  if (typeof globalThis.__bayzbotAlive === "function") {
    try {
      if (globalThis.__bayzbotAlive()) return;
    } catch (_error) {
      // instance lama rusak: lanjutkan sebagai instance baru
    }
  }
  globalThis.__bayzbotAlive = () => Boolean(chrome.runtime?.id);

  const PLAYBACK_KEY = "playbackState";
  const STATUS_INTERVAL_MS = 2000;
  const TRACK_TIMEOUT_MS = 30000;
  const AD_SKIP_SELECTOR = [
    ".ytp-ad-skip-button",
    ".ytp-ad-skip-button-modern",
    "button.ytp-skip-ad-button",
    ".ytp-skip-ad-button",
    ".ytp-ad-skip-button-container button",
    ".ytp-ad-skip-button-slot button",
    "[id^='skip-button']",
  ].join(",");
  // Penanda iklan sedang tayang (class/atribut yang di-toggle YouTube saat iklan).
  const AD_TOGGLE_SELECTOR = [
    ".ad-showing",
    ".ad-interrupting",
    "ytmusic-player-bar[ad-showing]",
    "ytmusic-player[ad-showing]",
  ].join(",");
  // Overlay iklan: hanya dihitung bila terlihat DAN berisi teks (wadahnya bisa tetap ada saat kosong).
  const AD_OVERLAY_SELECTOR = [
    ".ytp-ad-player-overlay",
    ".ytp-ad-preview-container",
    ".ytp-ad-text",
    ".ytp-ad-simple-ad-badge",
    ".ytp-ad-badge",
  ].join(",");
  const SKIP_TEXT_PATTERN = /^(skip( ads?)?|lewati( iklan)?|lompati( iklan)?)$/i;
  const AD_POLL_INTERVAL_MS = 500; // iklan pendek hanya ~5 detik, jadi dipantau lebih rapat dari status biasa
  const AD_END_DEBOUNCE_TICKS = 2; // iklan dianggap selesai setelah 2 poll berturut-turut tanpa penanda
  const SHORT_AD_MAX_SECONDS = 8; // iklan <= 8 detik dilabeli pendek (bumper) di LOG; hanya informasi
  const AD_ACT_OFFSET_SECONDS = 1; // iklan panjang ditindak pada detik ke-(batas skip + 1)
  const BUTTON_DISABLED_GRACE_MS = 3000; // tombol Skip ada tapi non-aktif selama ini -> muat ulang playlist
  const AD_RELOAD_MAX = 2; // batas muat ulang playlist karena iklan (per jendela waktu di bawah)
  const AD_RELOAD_WINDOW_MS = 180000;
  const AD_RELOAD_KEY = "bayzbot.adReloads"; // sessionStorage: bertahan walau halaman dimuat ulang
  const SKIP_VERIFY_MS = 1200; // jeda memeriksa apakah klik Skip berhasil sebelum mencoba lagi
  const SKIP_MAX_ATTEMPTS = 3;
  const SONG_END_WINDOW_SECONDS = 4; // iklan yang muncul <= 4 detik sebelum lagu habis = iklan setelah lagu
  const TRACK_VERIFY_INTERVAL_MS = 1000;
  const TRACK_GRACE_MS = 4000; // jeda setelah navigasi/iklan sebelum lagu diperiksa
  const TRACK_MISMATCH_CONFIRM_MS = 2000; // lagu salah harus bertahan selama ini sebelum dikoreksi
  const TRACK_FIX_MAX_ATTEMPTS = 3; // per 60 detik; lewat itu lagu dilewati
  const RESUME_AFTER_AD_MS = 6000;

  let playback = { urls: [], index: 0, active: false, adSkipDelaySeconds: 0 };
  let statusTimer = null;
  let trackStartedUrl = null;
  let adState = null;
  let adIndex = 0; // urutan iklan dalam satu rangkaian (ad pod): 1, 2, ...
  let adPollTimer = null;
  let adPodCtx = null; // konteks rangkaian iklan: apakah muncul tepat setelah lagu selesai
  let userPaused = false; // true bila dijeda lewat perintah; mencegah auto-resume
  let resumeAfterAdUntil = 0;
  let lastTrackVerifyAt = 0;
  let songSnapshot = { index: -1, time: 0, duration: 0, at: 0, ended: false };
  const trackGuard = {
    graceUntil: 0,
    mismatchSince: 0,
    mismatchActive: false,
    fixes: [],
  };

  function mediaElement() {
    return (
      document.querySelector("video.html5-main-video") ||
      document.querySelector("video") ||
      document.querySelector("audio")
    );
  }

  function metadata() {
    const title =
      document.querySelector("ytmusic-player-bar .title") ||
      document.querySelector("ytmusic-player-bar yt-formatted-string.title") ||
      document.querySelector("h1.title") ||
      document.querySelector("meta[property='og:title']");
    const artist =
      document.querySelector("ytmusic-player-bar .byline") ||
      document.querySelector("ytmusic-player-bar .subtitle") ||
      document.querySelector(".subtitle yt-formatted-string");
    const pageTitle = document.title
      .replace(/\s*-\s*YouTube(?: Music)?$/, "")
      .trim();
    const titleText =
      title?.content?.trim() ||
      title?.textContent?.trim() ||
      (/^(?:YouTube(?: Music)?|Music)$/i.test(pageTitle) ? "" : pageTitle);
    const artistText = artist?.textContent?.trim() || "";
    return { title: titleText, artist: artistText, album: "" };
  }

  function emit(message) {
    void chrome.runtime.sendMessage(message).catch(() => {});
  }

  function reportStatus() {
    const media = mediaElement();
    emit({
      type: "status",
      payload: {
        isPlaying: Boolean(media && !media.paused && !media.ended),
        currentTime: media?.currentTime || 0,
        duration: Number.isFinite(media?.duration) ? media.duration : 0,
        ...metadata(),
      },
    });
  }

  function persistPlayback() {
    return chrome.storage.local.set({ [PLAYBACK_KEY]: playback });
  }

  function currentUrl() {
    return playback.urls[playback.index] || "";
  }

  function emitTrackStarted() {
    const url = currentUrl();
    if (!url || trackStartedUrl === url) return;
    trackStartedUrl = url;
    emit({
      type: "event",
      event: playback.index === 0 ? "playlistStarted" : "playlistAdvanced",
      url,
      index: playback.index,
      total: playback.urls.length,
    });
  }

  async function playMedia() {
    const deadline = Date.now() + TRACK_TIMEOUT_MS;
    while (Date.now() < deadline) {
      const media = mediaElement();
      if (media && media.readyState >= 2) {
        try {
          await media.play();
        } catch (error) {
          const wasMuted = media.muted;
          try {
            media.muted = true;
            await media.play();
            setTimeout(() => {
              media.muted = wasMuted;
            }, 1500);
          } catch (mutedError) {
            return {
              ok: false,
              error: `Browser blocked playback: ${mutedError.message || error.message}`,
            };
          }
        }
        emitTrackStarted();
        reportStatus();
        return { ok: true };
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    return {
      ok: false,
      error: "Timed out waiting for the YouTube Music player to load.",
    };
  }

  function navigateTo(url) {
    location.assign(url);
  }

  function videoIdOf(url) {
    try {
      return new URL(url, location.href).searchParams.get("v") || "";
    } catch (_error) {
      return "";
    }
  }

  function navigateToCurrentTrack() {
    const url = currentUrl();
    if (!url) return false;
    trackStartedUrl = null;
    trackGuard.graceUntil = Date.now() + TRACK_GRACE_MS;
    if (location.href.split("&list=")[0] !== url) {
      navigateTo(url);
      return true;
    }
    return false;
  }

  async function startPlaylist(command) {
    if (
      !Array.isArray(command.urls) ||
      command.urls.length < 1 ||
      command.urls.some(
        (url) =>
          typeof url !== "string" ||
          !/^https:\/\/music\.youtube\.com\/watch\?v=[A-Za-z0-9_-]{6,20}/.test(
            url,
          ),
      )
    ) {
      return {
        ok: false,
        error: "Playlist must contain valid YouTube Music track URLs.",
      };
    }
    userPaused = false;
    playback = {
      urls: command.urls,
      index: 0,
      active: true,
      adSkipDelaySeconds: Math.max(0, Number(command.adSkipDelaySeconds) || 0),
    };
    await persistPlayback();
    const navigating = navigateToCurrentTrack();
    if (!navigating) return playMedia();
    return { ok: true };
  }

  async function moveTrack(offset) {
    if (!playback.active || playback.urls.length === 0) {
      return { ok: false, error: "No active BayzBot playlist." };
    }
    userPaused = false;
    const nextIndex = playback.index + offset;
    if (nextIndex < 0 || nextIndex >= playback.urls.length) {
      return { ok: false, error: "There is no track in that direction." };
    }
    playback.index = nextIndex;
    await persistPlayback();
    const navigating = navigateToCurrentTrack();
    if (!navigating) return playMedia();
    return { ok: true };
  }

  async function handleCommand(command) {
    switch (command.action) {
      case "playPlaylist":
        return startPlaylist(command);
      case "play": {
        userPaused = false;
        const media = mediaElement();
        if (!media)
          return { ok: false, error: "YouTube Music player is not available." };
        try {
          await media.play();
          reportStatus();
          return { ok: true };
        } catch (error) {
          return {
            ok: false,
            error: `Browser blocked playback: ${error.message}`,
          };
        }
      }
      case "pause": {
        userPaused = true;
        const media = mediaElement();
        if (media) media.pause();
        reportStatus();
        return { ok: true };
      }
      case "stop": {
        userPaused = true;
        playback.active = false;
        await persistPlayback();
        if (mediaElement()) mediaElement().pause();
        reportStatus();
        return { ok: true };
      }
      case "next":
        return moveTrack(1);
      case "previous":
        return moveTrack(-1);
      case "restart":
        if (!playback.active)
          return { ok: false, error: "No active BayzBot playlist." };
        return startPlaylist({
          urls: playback.urls,
          adSkipDelaySeconds: playback.adSkipDelaySeconds,
        });
      default:
        return { ok: false, error: `Unsupported command: ${command.action}` };
    }
  }

  chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
    if (message?.type !== "command") return;
    void handleCommand(message)
      .then(sendResponse)
      .catch((error) =>
        sendResponse({
          ok: false,
          error: error.message || "Player command failed.",
        }),
      );
    return true;
  });

  function onMediaEnded() {
    // 'ended' saat iklan tayang milik iklan, bukan lagu: jangan pindah lagu.
    // Iklan yang menyela di ujung lagu ditangani oleh endAd (advanceAfterAd).
    if (adState || adSignals().showing) {
      reportStatus();
      return;
    }
    if (playback.active && playback.index === playback.urls.length - 1) {
      emit({ type: "event", event: "playlistCompleted" });
    } else if (playback.active) {
      void moveTrack(1);
    }
    reportStatus();
  }

  function attachMediaListeners() {
    const media = mediaElement();
    if (!media || media.dataset.bayzbotAttached === "true") return;
    media.dataset.bayzbotAttached = "true";
    media.addEventListener("playing", () => {
      emitTrackStarted();
      reportStatus();
    });
    media.addEventListener("ended", onMediaEnded);
    media.addEventListener("pause", reportStatus);
  }

  function emitAdEvent(event, extra = {}) {
    emit({ type: "event", event, ...extra });
  }

  // ---- Deteksi elemen ----
  function isRendered(element) {
    if (!element || !element.isConnected) return false;
    for (let node = element; node; node = node.parentElement) {
      const style = getComputedStyle(node);
      if (style.display === "none" || style.visibility === "hidden") return false;
      if (node === element && style.opacity !== "" && Number(style.opacity) === 0) return false;
    }
    // Di browser sungguhan pakai ukuran layout; lingkungan tanpa layout (uji) dilewati.
    if (document.documentElement.getClientRects().length > 0) {
      return element.getClientRects().length > 0;
    }
    return true;
  }

  function isSkipButtonReady(button) {
    return (
      Boolean(button) &&
      !button.disabled &&
      button.getAttribute("aria-disabled") !== "true" &&
      !button.closest("[aria-disabled='true']") &&
      getComputedStyle(button).pointerEvents !== "none"
    );
  }

  // Tombol Skip yang TERLIHAT: lewat selector, lalu cadangan berdasarkan teks persis
  // (Skip / Skip Ad / Lewati / Lompati) bila YouTube mengganti nama class-nya.
  function findSkipButton() {
    for (const element of document.querySelectorAll(AD_SKIP_SELECTOR)) {
      if (isRendered(element)) return element;
    }
    const scope =
      document.querySelector("#movie_player, .html5-video-player, ytmusic-player") ||
      document;
    for (const element of scope.querySelectorAll(
      "button, [role='button'], [class*='skip']",
    )) {
      const label = (
        element.getAttribute("aria-label") ||
        element.textContent ||
        ""
      ).trim();
      if (label.length <= 20 && SKIP_TEXT_PATTERN.test(label) && isRendered(element)) {
        return element;
      }
    }
    return null;
  }

  function hasVisibleAdOverlay() {
    for (const element of document.querySelectorAll(AD_OVERLAY_SELECTOR)) {
      if (isRendered(element) && (element.textContent || "").trim() !== "") return true;
    }
    return false;
  }

  function adSignals() {
    const signals = [];
    if (document.querySelector(AD_TOGGLE_SELECTOR)) signals.push("toggle");
    const button = findSkipButton();
    if (button) signals.push("skip-button");
    if (hasVisibleAdOverlay()) signals.push("overlay");
    return { showing: signals.length > 0, signals, button };
  }

  function adDurationSeconds(media) {
    const duration = media?.duration;
    return Number.isFinite(duration) && duration > 0 ? duration : 0;
  }

  function adKindFor(seconds) {
    if (!seconds) return null; // durasi belum diketahui
    return seconds <= SHORT_AD_MAX_SECONDS ? "short" : "long";
  }

  // Batas waktu skip (detik) dari Settings: iklan yang durasinya <= batas ini diputar
  // sampai selesai; iklan yang lebih lama di-skip setelah batas ini terlewati.
  function adSkipThresholdSeconds() {
    return Math.max(0, Number(playback.adSkipDelaySeconds) || 0);
  }

  function pointerSequence(element) {
    const rect = element.getBoundingClientRect();
    const init = {
      bubbles: true,
      cancelable: true,
      view: window,
      clientX: rect.left + rect.width / 2,
      clientY: rect.top + rect.height / 2,
    };
    for (const type of ["pointerdown", "mousedown", "pointerup", "mouseup", "click"]) {
      element.dispatchEvent(new MouseEvent(type, init));
    }
  }

  // Klik bertingkat: tiap percobaan memakai cara yang lebih "keras" dari sebelumnya.
  function clickSkip(element, attempt) {
    if (attempt <= 1) {
      element.click();
    } else if (attempt === 2) {
      pointerSequence(element);
    } else if (attempt === 3) {
      const target = element.closest("button, [role='button']") || element.parentElement || element;
      pointerSequence(target);
      target.click();
    } else {
      element.focus?.();
      for (const key of ["Enter", " "]) {
        for (const type of ["keydown", "keyup"]) {
          element.dispatchEvent(new KeyboardEvent(type, { key, bubbles: true, cancelable: true }));
        }
      }
      element.click();
    }
  }

  function adDebugSnapshot(reason, signals = []) {
    const media = mediaElement();
    const player = document.querySelector("#movie_player, .html5-video-player");
    const candidates = [];
    for (const element of document.querySelectorAll(
      "[class*='skip'], [id*='skip'], button, [role='button']",
    )) {
      const label = (element.getAttribute("aria-label") || element.textContent || "").trim();
      const cls = String(element.className?.baseVal ?? element.className ?? "");
      if (!/skip|lewati|lompati|\bad\b|iklan/i.test(`${label} ${cls} ${element.id}`)) continue;
      candidates.push(
        `${element.tagName.toLowerCase()}.${cls.slice(0, 40)}|${label.slice(0, 18)}|${isRendered(element) ? "visible" : "hidden"}${isSkipButtonReady(element) ? "" : "+disabled"}`,
      );
      if (candidates.length >= 6) break;
    }
    return JSON.stringify({
      reason,
      signals,
      video: videoIdOf(location.href),
      playerClass: String(player?.className || "").slice(0, 100),
      media: media
        ? {
            duration: Number.isFinite(media.duration) ? Number(media.duration.toFixed(1)) : null,
            time: Number((media.currentTime || 0).toFixed(1)),
            paused: media.paused,
            muted: media.muted,
          }
        : null,
      candidates,
    }).slice(0, 900);
  }

  function adEventFields(extra = {}) {
    return {
      index: adState.index,
      kind: adState.kind,
      durationSec: adState.duration || null,
      ...extra,
    };
  }

  function computeAdContext() {
    const age = Date.now() - songSnapshot.at;
    const nearEnd =
      songSnapshot.duration > 0 &&
      (songSnapshot.ended ||
        songSnapshot.duration - songSnapshot.time <= SONG_END_WINDOW_SECONDS);
    return {
      indexAtStart: playback.index,
      afterSongEnd: songSnapshot.index === playback.index && nearEnd && age < 8000,
    };
  }

  function beginAd(media, signals) {
    adIndex += 1;
    const seconds = adDurationSeconds(media);
    if (!adPodCtx) adPodCtx = computeAdContext();
    adState = {
      index: adIndex,
      startedAt: Date.now(),
      duration: seconds,
      kind: adKindFor(seconds),
      src: media?.currentSrc || "",
      lastTime: media?.currentTime || 0,
      ctx: adPodCtx,
      decision: null, // "skip" | "play_full", ditentukan begitu durasi iklan diketahui
      attempts: 0,
      lastAttemptAt: 0,
      skipClicked: false,
      waitingReported: false,
      failedReported: false,
      missingTicks: 0,
    };
    const button = signals.includes("skip-button");
    emitAdEvent(
      "adDetected",
      adEventFields({
        skippable: button,
        skipDelaySeconds: adSkipThresholdSeconds(),
      }),
    );
    emitAdEvent("adDebug", {
      index: adState.index,
      snapshot: adDebugSnapshot("terdeteksi", signals),
    });
  }

  // carry = iklan berikutnya langsung tayang (rangkaian iklan): belum saatnya memeriksa lagu.
  function endAd(carry = false) {
    if (!adState) return;
    const finished = adState;
    emitAdEvent(
      "adEnded",
      adEventFields({
        durationMs: Date.now() - finished.startedAt,
        skipped: finished.skipClicked,
      }),
    );
    adState = null;
    if (carry) return;

    const ctx = finished.ctx;
    adPodCtx = null;
    trackGuard.mismatchSince = 0;
    trackGuard.graceUntil = Date.now() + TRACK_GRACE_MS;
    resumeAfterAdUntil = Date.now() + RESUME_AFTER_AD_MS;
    // Iklan menyela di ujung lagu: lagu itu sudah selesai, lanjutkan ke lagu playlist berikutnya
    // (jangan biarkan YouTube Music memilih lagu sendiri).
    if (ctx?.afterSongEnd && playback.active && playback.index === ctx.indexAtStart) {
      advanceAfterAd();
    }
  }

  function advanceAfterAd() {
    emitAdEvent("adAfterSong", {
      index: playback.index,
      total: playback.urls.length,
    });
    if (playback.index >= playback.urls.length - 1) {
      emit({ type: "event", event: "playlistCompleted" });
    } else {
      void moveTrack(1);
    }
  }

  // Iklan berikutnya dalam satu rangkaian tayang tanpa jeda: sumber/durasi berubah atau waktu mundur.
  function looksLikeNewAd(media) {
    if (!adState || !media) return false;
    const duration = adDurationSeconds(media);
    const durationChanged =
      duration > 0 &&
      adState.duration > 0 &&
      Math.abs(duration - adState.duration) > 0.5;
    const srcChanged =
      Boolean(media.currentSrc) &&
      Boolean(adState.src) &&
      media.currentSrc !== adState.src;
    const timeWentBack = (media.currentTime || 0) + 1 < adState.lastTime;
    return durationChanged || srcChanged || timeWentBack;
  }

  // Putuskan sekali per iklan: durasi <= batas skip -> putar penuh, lebih lama -> skip.
  function decideAd() {
    if (adState.decision || !adState.duration) return;
    const threshold = adSkipThresholdSeconds();
    adState.decision = adState.duration <= threshold ? "play_full" : "skip";
    emitAdEvent(
      "adDecision",
      adEventFields({ action: adState.decision, thresholdSeconds: threshold }),
    );
  }

  // Klik Skip (maks SKIP_MAX_ATTEMPTS kali, dengan jeda pemeriksaan). Mengembalikan "exhausted"
  // bila semua percobaan sudah dipakai dan iklan masih tayang.
  function attemptSkip(button, now) {
    if (adState.attempts >= SKIP_MAX_ATTEMPTS) {
      if (now - adState.lastAttemptAt < SKIP_VERIFY_MS) return "wait";
      if (!adState.failedReported) {
        adState.failedReported = true;
        emitAdEvent("adSkipFailed", adEventFields({ attempts: adState.attempts }));
      }
      return "exhausted";
    }
    // beri waktu iklan berganti sebelum menilai klik sebelumnya gagal
    if (now - adState.lastAttemptAt < SKIP_VERIFY_MS) return "wait";
    adState.attempts += 1;
    adState.lastAttemptAt = now;
    clickSkip(button, adState.attempts);
    adState.skipClicked = true;
    emitAdEvent(
      "adSkipClicked",
      adEventFields({
        attempt: adState.attempts,
        waitedMs: now - adState.startedAt,
      }),
    );
    return "clicked";
  }

  // ---- Muat ulang lagu playlist bila iklan tidak bisa di-skip lewat tombol ----
  function recentAdReloads(now) {
    try {
      const list = JSON.parse(sessionStorage.getItem(AD_RELOAD_KEY) || "[]");
      return list.filter((time) => Number.isFinite(time) && now - time < AD_RELOAD_WINDOW_MS);
    } catch (_error) {
      return [];
    }
  }

  function saveAdReloads(list) {
    try {
      sessionStorage.setItem(AD_RELOAD_KEY, JSON.stringify(list));
    } catch (_error) {
      // sessionStorage tidak tersedia: batas hanya berlaku selama halaman ini hidup
    }
  }

  // Cara terakhir "men-skip" iklan: kembali ke URL lagu playlist (memuat ulang halaman memutus
  // iklan yang sedang tayang). Dibatasi AD_RELOAD_MAX kali per AD_RELOAD_WINDOW_MS agar tidak
  // berulang-ulang bila iklan muncul lagi; sesudah itu iklan dibiarkan selesai.
  function forceReloadAd(reason, signals = []) {
    if (adState.reloadHandled) return;
    adState.reloadHandled = true;
    const now = Date.now();
    const recent = recentAdReloads(now);
    if (recent.length >= AD_RELOAD_MAX) {
      emitAdEvent("adReloadLimit", adEventFields({ reason, max: AD_RELOAD_MAX }));
      return;
    }
    recent.push(now);
    saveAdReloads(recent);
    emitAdEvent(
      "adForceReload",
      adEventFields({
        reason,
        attempt: recent.length,
        max: AD_RELOAD_MAX,
        thresholdSeconds: adSkipThresholdSeconds(),
      }),
    );
    emitAdEvent("adDebug", {
      index: adState.index,
      snapshot: adDebugSnapshot(`muat-ulang-${reason}`, signals),
    });
    trackGuard.graceUntil = now + TRACK_GRACE_MS;
    trackGuard.mismatchSince = 0;
    // Iklan menyela di ujung lagu: lagu itu sudah selesai, kembali ke lagu playlist BERIKUTNYA.
    const ctx = adState.ctx;
    if (ctx?.afterSongEnd && playback.active && playback.index === ctx.indexAtStart) {
      adPodCtx = null;
      advanceAfterAd();
      return;
    }
    trackStartedUrl = null;
    navigateTo(currentUrl());
  }

  // Dipanggil tiap AD_POLL_INTERVAL_MS saat playlist aktif.
  // Alur: adDetected -> adDecision -> (adWaiting sampai detik ke-batas+1) -> adSkipClicked (bisa berulang)
  //       | adForceReload (tanpa tombol / klik gagal) -> adEnded.  Iklan TIDAK dimute atau dipercepat.
  function adTick() {
    const media = mediaElement();
    const { showing, signals, button } = adSignals();
    const now = Date.now();

    if (!showing) {
      if (adState) {
        adState.missingTicks += 1; // debounce: penanda bisa hilang sesaat di antara iklan
        if (adState.missingTicks >= AD_END_DEBOUNCE_TICKS) endAd();
      } else {
        adIndex = 0; // rangkaian iklan berikutnya mulai lagi dari iklan ke-1
        updateSongSnapshot(media);
        resumeAfterAd(media, now);
        verifyPlaylistTrack(now);
      }
      return;
    }
    if (adState) adState.missingTicks = 0;

    if (adState && looksLikeNewAd(media)) endAd(true);
    if (!adState) beginAd(media, signals);

    if (media) {
      const duration = adDurationSeconds(media);
      if (!adState.duration && duration > 0) {
        adState.duration = duration;
        adState.kind = adKindFor(duration);
      }
      adState.lastTime = media.currentTime || 0;
    }

    decideAd();
    if (adState.decision === "play_full") return; // durasi <= batas skip: putar seluruhnya

    // Iklan lebih panjang dari batas: tunggu sampai detik ke-(batas + 1), lalu skip.
    const elapsed = now - adState.startedAt;
    const actAtMs = (adSkipThresholdSeconds() + AD_ACT_OFFSET_SECONDS) * 1000;
    if (elapsed < actAtMs) {
      if (!adState.waitingReported) {
        adState.waitingReported = true;
        emitAdEvent("adWaiting", adEventFields({ waitSeconds: actAtMs / 1000 }));
      }
      return;
    }
    if (!adState.decision) {
      // durasi iklan tidak pernah terbaca (mis. Infinity/NaN): dianggap panjang
      adState.decision = "skip";
      emitAdEvent(
        "adDecision",
        adEventFields({ action: "skip", thresholdSeconds: adSkipThresholdSeconds(), assumed: true }),
      );
    }

    if (isSkipButtonReady(button)) {
      const outcome = attemptSkip(button, now);
      if (outcome === "exhausted") forceReloadAd("skip-gagal", signals);
      return;
    }
    if (button) {
      // tombol ada tapi masih non-aktif: beri waktu sebentar, lalu muat ulang playlist
      adState.buttonWaitSince ??= now;
      if (now - adState.buttonWaitSince >= BUTTON_DISABLED_GRACE_MS) {
        forceReloadAd("tombol-nonaktif", signals);
      }
      return;
    }
    forceReloadAd("tanpa-tombol-skip", signals); // tidak ada tombol Skip sama sekali
  }

  // ---- Pastikan lagu yang diputar adalah lagu playlist ----
  function updateSongSnapshot(media) {
    if (!media || !playback.active) return;
    if (videoIdOf(location.href) !== videoIdOf(currentUrl())) return;
    songSnapshot = {
      index: playback.index,
      time: media.currentTime || 0,
      duration: Number.isFinite(media.duration) ? media.duration : 0,
      at: Date.now(),
      ended: Boolean(media.ended),
    };
  }

  // Setelah iklan, lagu playlist harus berjalan lagi (kecuali dijeda lewat perintah).
  function resumeAfterAd(media, now) {
    if (now >= resumeAfterAdUntil || !media || userPaused) return;
    if (!media.paused || media.ended) return;
    if (videoIdOf(location.href) !== videoIdOf(currentUrl())) return;
    resumeAfterAdUntil = 0;
    void Promise.resolve(media.play()).then(
      () => emitAdEvent("playResumedAfterAd", { index: playback.index }),
      () => {},
    );
  }

  function verifyPlaylistTrack(now) {
    if (!playback.active || now < trackGuard.graceUntil) return;
    if (now - lastTrackVerifyAt < TRACK_VERIFY_INTERVAL_MS) return;
    lastTrackVerifyAt = now;

    const expected = videoIdOf(currentUrl());
    const actual = videoIdOf(location.href);
    if (!expected) return;
    if (expected === actual) {
      if (trackGuard.mismatchActive) {
        trackGuard.mismatchActive = false;
        emitAdEvent("trackRestored", { index: playback.index, video: actual });
      }
      trackGuard.mismatchSince = 0;
      return;
    }
    if (!trackGuard.mismatchSince) {
      trackGuard.mismatchSince = now;
      return;
    }
    if (now - trackGuard.mismatchSince < TRACK_MISMATCH_CONFIRM_MS) return;
    fixTrack(now, expected, actual);
  }

  function fixTrack(now, expected, actual) {
    trackGuard.fixes = trackGuard.fixes.filter((time) => now - time < 60000);
    trackGuard.mismatchSince = 0;
    if (trackGuard.fixes.length >= TRACK_FIX_MAX_ATTEMPTS) {
      // Lagu playlist tidak bisa dipulihkan (mis. tidak tersedia): lewati agar playlist tidak macet.
      trackGuard.fixes = [];
      trackGuard.mismatchActive = false;
      emitAdEvent("trackMismatchFailed", {
        index: playback.index,
        total: playback.urls.length,
        expected,
        actual,
      });
      if (playback.index >= playback.urls.length - 1) {
        emit({ type: "event", event: "playlistCompleted" });
      } else {
        void moveTrack(1);
      }
      return;
    }
    trackGuard.fixes.push(now);
    trackGuard.mismatchActive = true;
    trackGuard.graceUntil = now + TRACK_GRACE_MS;
    emitAdEvent("trackMismatch", {
      index: playback.index,
      total: playback.urls.length,
      expected,
      actual,
      attempt: trackGuard.fixes.length,
    });
    trackStartedUrl = null;
    navigateTo(currentUrl());
  }

  async function initialize() {
    const saved = await chrome.storage.local.get(PLAYBACK_KEY);
    const state = saved[PLAYBACK_KEY];
    if (state && Array.isArray(state.urls)) playback = state;
    trackGuard.graceUntil = Date.now() + TRACK_GRACE_MS;

    attachMediaListeners();
    if (!statusTimer)
      statusTimer = setInterval(() => {
        attachMediaListeners();
        reportStatus();
      }, STATUS_INTERVAL_MS);
    if (!adPollTimer)
      adPollTimer = setInterval(() => {
        if (playback.active) {
          adTick();
        } else if (adState) {
          endAd(); // playlist dihentikan: hentikan pelacakan iklan
        }
      }, AD_POLL_INTERVAL_MS);

    if (playback.active && currentUrl()) {
      if (location.href.split("&list=")[0] === currentUrl()) {
        void playMedia();
      }
    }
    reportStatus();
  }

  void initialize();
})();
