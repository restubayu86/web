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

async function findOrOpenPlayerTab(url) {
    const tabs = await chrome.tabs.query({ url: ["https://music.youtube.com/*", "https://www.youtube.com/*"] });
    if (tabs.length > 0) return tabs[0];
    if (!isPlayerUrl(url)) {
        throw new Error("Playlist URL is not a supported YouTube Music page.");
    }
    return chrome.tabs.create({ url, active: true });
}

async function sendCommandToTab(tab, command) {
    for (let attempt = 0; attempt < 20; attempt++) {
        try {
            return await chrome.tabs.sendMessage(tab.id, command);
        } catch (error) {
            if (attempt === 19) throw error;
            await new Promise(resolve => setTimeout(resolve, 500));
            const current = await chrome.tabs.get(tab.id);
            if (current.status === "complete") continue;
        }
    }
    throw new Error("BayzBot player content script did not respond.");
}

async function handleCommand(command) {
    let result;
    try {
        const firstUrl = Array.isArray(command.urls) ? command.urls[0] : "";
        const tab = await findOrOpenPlayerTab(firstUrl);
        result = await sendCommandToTab(tab, command);
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
