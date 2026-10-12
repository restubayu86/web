const form = document.getElementById("options");
const backendUrl = document.getElementById("backendUrl");
const profileId = document.getElementById("profileId");
const status = document.getElementById("status");

void chrome.storage.local.get(["backendUrl", "profileId"]).then(settings => {
    backendUrl.value = settings.backendUrl || "ws://localhost:8765";
    profileId.value = settings.profileId || "";
});

form.addEventListener("submit", async event => {
    event.preventDefault();
    try {
        const url = new URL(backendUrl.value.trim());
        if (!["ws:", "wss:"].includes(url.protocol) || url.username || url.password) {
            throw new Error("Use a ws:// or wss:// URL without credentials.");
        }
        const id = profileId.value.trim();
        if (!/^[0-9]+$/.test(id) || Number(id) < 1) {
            throw new Error("Dolphin Browser Profile ID must be a positive number.");
        }
        await chrome.storage.local.set({
            backendUrl: url.toString().replace(/\/$/, ""),
            profileId: id
        });
        status.textContent = "Settings saved. The extension will reconnect automatically.";
    } catch (error) {
        status.textContent = error.message || "Could not save settings.";
    }
});
