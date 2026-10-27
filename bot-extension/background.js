importScripts("chromium-config.js");

const DEFAULT_URL = "ws://localhost:8765";
const RECONNECT_ALARM = "bayzbot-reconnect";
const KEEPALIVE_ALARM = "bayzbot-keepalive";
const KEEPALIVE_MS = 20000;

let socket = null;
let reconnectDelay = 1000;
let connectInProgress = false;
let keepaliveTimer = null;

function normalizedSocketUrl(value) {
    const url = new URL(value || DEFAULT_URL);
    if (!["ws:", "wss:"].includes(url.protocol) || url.username || url.password) {
        throw new Error("WebSocket URL must use ws:// or wss:// and cannot include credentials.");
    }
    return url.toString().replace(/\/$/, "");
}

function sendSocket(message) {
    if (!socket || socket.readyState !== WebSocket.OPEN) return false;
    socket.send(JSON.stringify(message));
    return true;
}

async function connect() {
    if (connectInProgress || (socket && [WebSocket.CONNECTING, WebSocket.OPEN].includes(socket.readyState))) {
        return;
    }
    connectInProgress = true;

    try {
        const settings = await chrome.storage.local.get(["backendUrl", "profileId", "instanceId"]);
        const chromiumConfig = globalThis.BAYZBOT_CHROMIUM_CONFIG || {};
        const profileId = String(settings.profileId || chromiumConfig.profileId || "").trim();
        if (!profileId) {
            console.warn("BayzBot: set the Dolphin Browser Profile ID in extension Options.");
            return;
        }
        if (!settings.profileId && chromiumConfig.profileId) {
            await chrome.storage.local.set({ profileId });
        }

        let instanceId = settings.instanceId;
        if (!instanceId) {
            instanceId = crypto.randomUUID();
            await chrome.storage.local.set({ instanceId });
        }

        const url = normalizedSocketUrl(settings.backendUrl || DEFAULT_URL);
        const nextSocket = new WebSocket(url);
        socket = nextSocket;

        nextSocket.addEventListener("open", () => {
            reconnectDelay = 1000;
            sendSocket({
                type: "hello",
                instanceId,
                instanceLabel: chromiumConfig.profileId ? `Chromium profile ${profileId}` : `Dolphin profile ${profileId}`,
                browserProfileId: profileId,
                version: chrome.runtime.getManifest().version
            });
            keepaliveTimer = setInterval(() => {
                if (!sendSocket({ type: "ping", timestamp: Date.now() })) {
                    clearInterval(keepaliveTimer);
                    keepaliveTimer = null;
                }
            }, KEEPALIVE_MS);
        });

        nextSocket.addEventListener("message", event => {
            let message;
            try {
                message = JSON.parse(event.data);
            } catch (error) {
                console.error("BayzBot: received invalid backend message.", error);
                return;
            }
            if (message?.type === "command") {
                void handleCommand(message);
            }
        });

        nextSocket.addEventListener("close", scheduleReconnect, { once: true });
        nextSocket.addEventListener("error", () => nextSocket.close(), { once: true });
    } catch (error) {
        console.error("BayzBot: WebSocket connection failed.", error);
        scheduleReconnect();
    } finally {
        connectInProgress = false;
    }
}

if (chrome.webRequest?.onAuthRequired && globalThis.BAYZBOT_CHROMIUM_CONFIG?.proxyAuth) {
    const proxyAuth = globalThis.BAYZBOT_CHROMIUM_CONFIG.proxyAuth;
    chrome.webRequest.onAuthRequired.addListener(
        (details, callback) => {
            if (details.isProxy) {
                callback({ authCredentials: proxyAuth });
                return;
            }
            callback({});
        },
        { urls: ["<all_urls>"] },
        ["asyncBlocking"]
    );
}

function scheduleReconnect() {
    if (keepaliveTimer) {
        clearInterval(keepaliveTimer);
        keepaliveTimer = null;
    }
    if (socket && socket.readyState !== WebSocket.CLOSED) {
        socket.close();
    }
    socket = null;
    const delay = reconnectDelay;
    reconnectDelay = Math.min(reconnectDelay * 2, 30000);
    setTimeout(() => void connect(), delay);
}

function isPlayerUrl(value) {
    try {
        const url = new URL(value);
        return ["music.youtube.com", "www.youtube.com"].includes(url.hostname);
    } catch {
        return false;
    }
}

// Perintah ke tab pemutar: sabar sampai DELIVERY_TIMEOUT_MS (backend menunggu 50 detik) dan memulihkan
// penyebab umum "Receiving end does not exist": halaman masih memuat (proxy lambat, profil baru), tab
// lama yang dibuka sebelum ekstensi dimuat, tab ter-discard, atau tab berada di halaman bukan YouTube Music
// (persetujuan/login/error jaringan). Tiap pemulihan dicoba sekali.
const DELIVERY_TIMEOUT_MS = 40000;
const DELIVERY_POLL_MS = 500;
const LOADING_PATIENCE_MS = 20000; // tab 'loading' selama ini masih dianggap wajar
const RECOVERY_GRACE_MS = 3000; // tab harus 'complete' selama ini tanpa respons sebelum disuntik/diarahkan ulang
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function findOrOpenPlayerTab(url) {
    const tabs = await chrome.tabs.query({ url: ["https://music.youtube.com/*", "https://www.youtube.com/*"] });
    if (tabs.length > 0) {
        // utamakan tab yang hidup (tidak ter-discard) dan paling baru dipakai
        tabs.sort((a, b) => Number(Boolean(a.discarded)) - Number(Boolean(b.discarded)) || (b.lastAccessed || 0) - (a.lastAccessed || 0));
        return tabs[0];
    }
    if (!isPlayerUrl(url)) {
        throw new Error("Playlist URL is not a supported YouTube Music page.");
    }
    return chrome.tabs.create({ url, active: true });
}

function describeTab(tab) {
    let where = "alamat tidak diketahui";
    try {
        const url = new URL(tab.url);
        where = `${url.hostname}${url.pathname}`;
    } catch {
        // tab.url bisa kosong untuk halaman internal
    }
    return `status tab: ${tab.status || "?"}, halaman: ${where}`;
}

async function sendCommandToTab(initialTab, command, firstUrl) {
    const startedAt = Date.now();
    let tab = initialTab;
    let lastError = null;
    const tried = { reload: false, navigate: false, inject: false };
    let completeSince = null;

    while (Date.now() - startedAt < DELIVERY_TIMEOUT_MS) {
        try {
            tab = await chrome.tabs.get(tab.id);
        } catch {
            // tab ditutup saat menunggu: buka lagi
            tab = await chrome.tabs.create({ url: firstUrl, active: true });
            await sleep(DELIVERY_POLL_MS);
            continue;
        }

        try {
            return await chrome.tabs.sendMessage(tab.id, command);
        } catch (error) {
            lastError = error;
        }

        const elapsed = Date.now() - startedAt;
        if (tab.status === "complete") {
            completeSince ??= Date.now();
        } else {
            completeSince = null;
        }
        const settled = completeSince !== null && Date.now() - completeSince >= RECOVERY_GRACE_MS;
        if (tab.discarded && !tried.reload) {
            tried.reload = true;
            await chrome.tabs.reload(tab.id).catch(() => {});
        } else if (tab.status === "loading" && elapsed < LOADING_PATIENCE_MS) {
            // masih memuat: tunggu saja (bukan kegagalan)
        } else if (tab.status === "complete" && !settled) {
            // baru selesai dimuat: beri waktu content script mendaftar sebelum menyuntik ulang
        } else if (!isPlayerUrl(tab.url) && !tried.navigate && isPlayerUrl(firstUrl)) {
            tried.navigate = true;
            await chrome.tabs.update(tab.id, { url: firstUrl, active: true }).catch(() => {});
        } else if (isPlayerUrl(tab.url) && !tried.inject) {
            // tab dibuka sebelum ekstensi dimuat: suntikkan content script secara manual
            tried.inject = true;
            try {
                await chrome.scripting.executeScript({ target: { tabId: tab.id }, files: ["content.js"] });
            } catch (injectError) {
                lastError = injectError;
            }
        }
        await sleep(DELIVERY_POLL_MS);
    }

    let hint = "Content script pemutar tidak aktif; muat ulang ekstensi dan tab YouTube Music.";
    if (!isPlayerUrl(tab.url)) {
        hint = "Tab tidak berada di YouTube Music (mungkin halaman persetujuan/login atau gagal memuat lewat proxy); buka music.youtube.com di profil ini.";
    } else if (tab.status === "loading") {
        hint = "Halaman YouTube Music tidak selesai dimuat (koneksi atau proxy terlalu lambat).";
    }
    throw new Error(
        `Tab YouTube Music tidak merespons setelah ${Math.round(DELIVERY_TIMEOUT_MS / 1000)} detik ` +
        `(${describeTab(tab)}). ${hint} [${lastError?.message || "tanpa detail"}]`
    );
}

async function handleCommand(command) {
    let result;
    try {
        const firstUrl = Array.isArray(command.urls) ? command.urls[0] : "";
        const tab = await findOrOpenPlayerTab(firstUrl);
        result = await sendCommandToTab(tab, command, firstUrl);
    } catch (error) {
        result = { ok: false, error: error.message || "Could not deliver command to the player." };
    }
    if (command.requestId) {
        sendSocket({ type: "commandResult", requestId: command.requestId, result });
    }
}

chrome.runtime.onMessage.addListener(message => {
    if (message?.type === "status" || message?.type === "event") {
        sendSocket(message);
    }
});

chrome.runtime.onInstalled.addListener(() => {
    chrome.alarms.create(RECONNECT_ALARM, { periodInMinutes: 0.5 });
    chrome.alarms.create(KEEPALIVE_ALARM, { periodInMinutes: 0.5 });
    void connect();
});

chrome.runtime.onStartup.addListener(() => void connect());

chrome.alarms.onAlarm.addListener(alarm => {
    if (alarm.name === RECONNECT_ALARM && (!socket || socket.readyState === WebSocket.CLOSED)) {
        void connect();
    }
    if (alarm.name === KEEPALIVE_ALARM && socket?.readyState === WebSocket.OPEN) {
        sendSocket({ type: "ping", timestamp: Date.now() });
    }
});

chrome.storage.onChanged.addListener((changes, area) => {
    if (area === "local" && (changes.backendUrl || changes.profileId)) {
        if (socket) socket.close();
        socket = null;
        reconnectDelay = 1000;
        void connect();
    }
});

void connect();
