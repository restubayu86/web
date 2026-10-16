(() => {
  const PLAYBACK_KEY = "playbackState";
  const STATUS_INTERVAL_MS = 2000;
  const TRACK_TIMEOUT_MS = 30000;
  const AD_SKIP_SELECTOR = [
    ".ytp-ad-skip-button",
    ".ytp-ad-skip-button-modern",
    "button.ytp-skip-ad-button",
    ".ytp-skip-ad-button",
    ".ytp-ad-skip-button-container button",
  ].join(",");
  // Penanda iklan sedang tayang (selain tombol skip), dipakai untuk status iklan di LOG.
  const AD_TOGGLE_SELECTOR = [
    ".ad-showing",
    ".ad-interrupting",
    "ytmusic-player-bar[ad-showing]",
  ].join(",");
  const AD_POLL_INTERVAL_MS = 500; // iklan pendek hanya ~5 detik, jadi dipantau lebih rapat dari status biasa
  const SHORT_AD_MAX_SECONDS = 8; // iklan <= 8 detik dianggap pendek (bumper, tidak bisa di-skip)
  const SKIP_BUTTON_WAIT_MS = 7000; // iklan panjang: tunggu tombol Skip sebelum dipercepat
  const FAST_FORWARD_RATE = 16;
  const MAX_FORCE_SEEK_SECONDS = 120; // jangan pernah seek ke akhir bila durasi > ini (bukan iklan)
  const SKIP_CLICK_COOLDOWN_MS = 1500;

  let playback = { urls: [], index: 0, active: false, adSkipDelaySeconds: 0 };
  let statusTimer = null;
  let trackStartedUrl = null;
  let skipTimer = null;
  let adState = null;
  let adIndex = 0; // urutan iklan dalam satu rangkaian (ad pod): 1, 2, ...
  let adSavedMedia = null; // muted/playbackRate asli sebelum iklan dipercepat
  let adPollTimer = null;

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

  function navigateToCurrentTrack() {
    const url = currentUrl();
    if (!url) return false;
    trackStartedUrl = null;
    if (location.href.split("&list=")[0] !== url) {
      location.assign(url);
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
        const media = mediaElement();
        if (media) media.pause();
        reportStatus();
        return { ok: true };
      }
      case "stop": {
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
    // 'ended' milik iklan (mis. setelah dipercepat) bukan akhir lagu: jangan pindah track.
    if (adState || document.querySelector(AD_TOGGLE_SELECTOR)) {
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

  function adDurationSeconds(media) {
    const duration = media?.duration;
    return Number.isFinite(duration) && duration > 0 ? duration : 0;
  }

  function adKindFor(seconds) {
    if (!seconds) return null; // durasi belum diketahui
    return seconds <= SHORT_AD_MAX_SECONDS ? "short" : "long";
  }

  function isSkipButtonReady(button) {
    return (
      Boolean(button) &&
      !button.disabled &&
      button.getAttribute("aria-disabled") !== "true"
    );
  }

  function clickElement(element) {
    for (const type of ["pointerdown", "mousedown", "pointerup", "mouseup"]) {
      element.dispatchEvent(
        new MouseEvent(type, { bubbles: true, cancelable: true, view: window }),
      );
    }
    element.click();
  }

  function adEventFields(extra = {}) {
    return {
      index: adState.index,
      kind: adState.kind,
      durationSec: adState.duration || null,
      ...extra,
    };
  }

  function beginAd(media, button) {
    adIndex += 1;
    const seconds = adDurationSeconds(media);
    adState = {
      index: adIndex,
      startedAt: Date.now(),
      duration: seconds,
      kind: adKindFor(seconds),
      lastTime: media?.currentTime || 0,
      skipClicked: false,
      skipClickedAt: 0,
      fastForwarded: false,
      waitingReported: false,
    };
    emitAdEvent(
      "adDetected",
      adEventFields({
        skippable: Boolean(button),
        skipDelaySeconds: playback.adSkipDelaySeconds,
      }),
    );
  }

  function restoreMedia() {
    const media = mediaElement();
    if (media && adSavedMedia) {
      media.muted = adSavedMedia.muted;
      media.playbackRate = adSavedMedia.rate || 1;
    }
    adSavedMedia = null;
  }

  // carryOver = iklan berikutnya langsung tayang (ad pod): jangan lepas mute dulu.
  function endAd(carryOver) {
    if (!adState) return;
    if (skipTimer) {
      clearTimeout(skipTimer);
      skipTimer = null;
    }
    emitAdEvent(
      "adEnded",
      adEventFields({
        durationMs: Date.now() - adState.startedAt,
        skipped: adState.skipClicked,
        fastForwarded: adState.fastForwarded,
      }),
    );
    if (!carryOver) restoreMedia();
    adState = null;
  }

  // Iklan kedua dalam satu rangkaian tayang tanpa jeda: durasi berubah atau waktu mundur.
  function looksLikeNewAd(media) {
    if (!adState || !media) return false;
    const duration = adDurationSeconds(media);
    const durationChanged =
      duration > 0 &&
      adState.duration > 0 &&
      Math.abs(duration - adState.duration) > 0.5;
    const timeWentBack = (media.currentTime || 0) + 1 < adState.lastTime;
    return durationChanged || timeWentBack;
  }

  function scheduleSkipClick() {
    if (skipTimer) return;
    if (Date.now() - adState.skipClickedAt < SKIP_CLICK_COOLDOWN_MS) return;
    skipTimer = setTimeout(
      () => {
        skipTimer = null;
        const current = document.querySelector(AD_SKIP_SELECTOR);
        if (!adState || !isSkipButtonReady(current)) return;
        clickElement(current);
        adState.skipClicked = true;
        adState.skipClickedAt = Date.now();
        emitAdEvent(
          "adSkipClicked",
          adEventFields({ waitedMs: Date.now() - adState.startedAt }),
        );
      },
      Math.max(0, playback.adSkipDelaySeconds * 1000),
    );
  }

  // Iklan tanpa tombol Skip (pendek maupun panjang): mute + percepat + lompat ke akhir.
  function fastForwardAd(media) {
    if (!adState || !media) return;
    if (!adSavedMedia) {
      adSavedMedia = { muted: media.muted, rate: media.playbackRate };
    }
    media.muted = true;
    media.playbackRate = FAST_FORWARD_RATE;
    const duration = adDurationSeconds(media);
    if (duration > 0 && duration <= MAX_FORCE_SEEK_SECONDS) {
      try {
        media.currentTime = Math.max(0, duration - 0.1);
      } catch (_error) {
        // sebagian iklan menolak seek; percepatan 16x tetap berjalan
      }
    }
    adState.fastForwarded = true;
    emitAdEvent(
      "adFastForward",
      adEventFields({
        elapsedMs: Date.now() - adState.startedAt,
        rate: FAST_FORWARD_RATE,
      }),
    );
  }

  // Dipanggil tiap AD_POLL_INTERVAL_MS saat playlist aktif.
  // Alur: adDetected -> (adWaiting) -> adSkipClicked | adFastForward -> adEnded
  function adTick() {
    const media = mediaElement();
    const button = document.querySelector(AD_SKIP_SELECTOR);
    const showing =
      Boolean(button) || Boolean(document.querySelector(AD_TOGGLE_SELECTOR));

    if (!showing) {
      if (adState) endAd(false);
      adIndex = 0; // rangkaian iklan berikutnya mulai lagi dari iklan ke-1
      return;
    }

    if (adState && looksLikeNewAd(media)) endAd(true);
    if (!adState) beginAd(media, button);

    if (media) {
      const duration = adDurationSeconds(media);
      if (!adState.duration && duration > 0) {
        adState.duration = duration;
        adState.kind = adKindFor(duration);
      }
      adState.lastTime = media.currentTime || 0;
    }

    if (isSkipButtonReady(button)) {
      scheduleSkipClick();
      return;
    }
    if (button) return; // tombol ada tapi masih hitung mundur: tunggu sampai aktif

    // Tidak ada tombol Skip. Iklan pendek langsung dipercepat; iklan panjang
    // diberi waktu dulu karena tombol baru muncul setelah ~5 detik.
    const elapsed = Date.now() - adState.startedAt;
    const waitMs =
      adState.kind === "short"
        ? 1000
        : Math.max(SKIP_BUTTON_WAIT_MS, playback.adSkipDelaySeconds * 1000);
    if (adState.kind !== "short" && !adState.waitingReported) {
      adState.waitingReported = true;
      emitAdEvent("adWaiting", adEventFields({ waitSeconds: waitMs / 1000 }));
    }
    if (!adState.fastForwarded && elapsed >= waitMs) fastForwardAd(media);
  }

  async function initialize() {
    const saved = await chrome.storage.local.get(PLAYBACK_KEY);
    const state = saved[PLAYBACK_KEY];
    if (state && Array.isArray(state.urls)) playback = state;

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
          endAd(false); // playlist dihentikan: lepas mute/percepatan
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
