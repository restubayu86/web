(() => {
    const PLAYBACK_KEY = "playbackState";
    const STATUS_INTERVAL_MS = 2000;
    const TRACK_TIMEOUT_MS = 30000;
    const AD_SKIP_SELECTOR = [
        ".ytp-ad-skip-button",
        ".ytp-ad-skip-button-modern",
        "button.ytp-skip-ad-button"
    ].join(",");

    let playback = { urls: [], index: 0, active: false, adSkipDelaySeconds: 0 };
    let statusTimer = null;
    let trackStartedUrl = null;
    let skipTimer = null;

    function mediaElement() {
        return document.querySelector("video.html5-main-video")
            || document.querySelector("video")
            || document.querySelector("audio");
    }

    function metadata() {
        const title = document.querySelector("ytmusic-player-bar .title")
            || document.querySelector("ytmusic-player-bar yt-formatted-string.title")
            || document.querySelector("h1.title")
            || document.querySelector("meta[property='og:title']");
        const artist = document.querySelector("ytmusic-player-bar .byline")
            || document.querySelector("ytmusic-player-bar .subtitle")
            || document.querySelector(".subtitle yt-formatted-string");
        const pageTitle = document.title.replace(/\s*-\s*YouTube(?: Music)?$/, "").trim();
        const titleText = title?.content?.trim()
            || title?.textContent?.trim()
            || (/^(?:YouTube(?: Music)?|Music)$/i.test(pageTitle) ? "" : pageTitle);
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
                ...metadata()
            }
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
            total: playback.urls.length
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
                        setTimeout(() => { media.muted = wasMuted; }, 1500);
                    } catch (mutedError) {
                        return { ok: false, error: `Browser blocked playback: ${mutedError.message || error.message}` };
                    }
                }
                emitTrackStarted();
                reportStatus();
                return { ok: true };
            }
            await new Promise(resolve => setTimeout(resolve, 500));
        }
        return { ok: false, error: "Timed out waiting for the YouTube Music player to load." };
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
        if (!Array.isArray(command.urls) || command.urls.length < 1
            || command.urls.some(url => typeof url !== "string" || !/^https:\/\/music\.youtube\.com\/watch\?v=[A-Za-z0-9_-]{6,20}/.test(url))) {
            return { ok: false, error: "Playlist must contain valid YouTube Music track URLs." };
        }
        playback = {
            urls: command.urls,
            index: 0,
            active: true,
            adSkipDelaySeconds: Math.max(0, Number(command.adSkipDelaySeconds) || 0)
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
                if (!media) return { ok: false, error: "YouTube Music player is not available." };
                try {
                    await media.play();
                    reportStatus();
                    return { ok: true };
                } catch (error) {
                    return { ok: false, error: `Browser blocked playback: ${error.message}` };
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
                if (!playback.active) return { ok: false, error: "No active BayzBot playlist." };
                return startPlaylist({ urls: playback.urls, adSkipDelaySeconds: playback.adSkipDelaySeconds });
            default:
                return { ok: false, error: `Unsupported command: ${command.action}` };
        }
    }

    chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
        if (message?.type !== "command") return;
        void handleCommand(message)
            .then(sendResponse)
            .catch(error => sendResponse({ ok: false, error: error.message || "Player command failed." }));
        return true;
    });

    function onMediaEnded() {
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

    function maybeSkipAd() {
        const button = document.querySelector(AD_SKIP_SELECTOR);
        if (!button || button.disabled || button.getAttribute("aria-disabled") === "true") {
            if (skipTimer) clearTimeout(skipTimer);
            skipTimer = null;
            return;
        }
        if (skipTimer) return;
        skipTimer = setTimeout(() => {
            skipTimer = null;
            const currentButton = document.querySelector(AD_SKIP_SELECTOR);
            if (currentButton && !currentButton.disabled) currentButton.click();
        }, playback.adSkipDelaySeconds * 1000);
    }

    async function initialize() {
        const saved = await chrome.storage.local.get(PLAYBACK_KEY);
        const state = saved[PLAYBACK_KEY];
        if (state && Array.isArray(state.urls)) playback = state;

        attachMediaListeners();
        if (!statusTimer) statusTimer = setInterval(() => {
            attachMediaListeners();
            if (playback.active) maybeSkipAd();
            reportStatus();
        }, STATUS_INTERVAL_MS);

        if (playback.active && currentUrl()) {
            if (location.href.split("&list=")[0] === currentUrl()) {
                void playMedia();
            }
        }
        reportStatus();
    }

    void initialize();
})();
