# BayzBot Player extension

This is the local Manifest V3 extension used to connect one Dolphin browser
profile to the BayzBot backend. Each Dolphin profile stores its own extension
settings, including its Dolphin Browser Profile ID.

## Install into Dolphin{anty}

1. Zip the contents of this folder (`manifest.json`, `background.js`,
   `content.js`, `options.html`, and `options.js`); do not zip the parent folder.
2. In Dolphin{anty}, open **Extensions**, choose **+** → **Upload file**, and
   upload the ZIP. Dolphin documents extension archives as a paid-plan feature.
3. Enable BayzBot Player for the profile type or profiles that should use it.
4. Open the profile and open the extension's **Options** page. Set the
   WebSocket URL to `ws://localhost:8765` and enter that profile's numeric
   Dolphin Browser Profile ID.
5. Save the settings, then reload the profile or open YouTube Music.
6. In the web dashboard, assign a playlist to the profile and press **Start**.

The extension reconnects to the local backend automatically and reports its
profile ID during the WebSocket handshake. Keep the BayzBot backend running.

The extension controls playback in YouTube Music. Browser autoplay policy,
account access, and YouTube page changes can still affect playback.
