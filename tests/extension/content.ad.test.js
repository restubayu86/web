// Tes algoritma iklan v3 (bot-extension/content.js) dengan jsdom.
// Jalankan: npm i jsdom (di folder mana pun) lalu  NODE_PATH=<node_modules> node tests/extension/content.ad.test.js
const fs = require("fs");
const path = require("path");
const { JSDOM } = require("jsdom");

const SRC = fs.readFileSync(path.join(__dirname, "../../bot-extension/content.js"), "utf8");
const T = 0.3; // batas skip (detik) di tes
const VID = (n) => `https://music.youtube.com/watch?v=${n}`;
const URLS = [VID("AAAAAAAAAAA"), VID("BBBBBBBBBBB"), VID("CCCCCCCCCCC")];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const CONSTS = {
  AD_POLL_INTERVAL_MS: 30,
  AD_ACT_OFFSET_SECONDS: 0.1,
  SKIP_VERIFY_MS: 150,
  BUTTON_DISABLED_GRACE_MS: 300,
  TRACK_VERIFY_INTERVAL_MS: 40,
  TRACK_GRACE_MS: 200,
  TRACK_MISMATCH_CONFIRM_MS: 150,
  RESUME_AFTER_AD_MS: 1500,
};

function patched() {
  let s = SRC;
  for (const [k, v] of Object.entries(CONSTS)) {
    const re = new RegExp(`const ${k} = [0-9.]+;`);
    if (!re.test(s)) throw new Error("konstanta tidak ditemukan: " + k);
    s = s.replace(re, `const ${k} = ${v};`);
  }
  return s.replace(/location\.assign\(/g, "window.__navigate(");
}
const CODE = patched();

async function makeEnv({ playbackIndex = 0, startUrl, delay = T, session } = {}) {
  const dom = new JSDOM(
    `<body><div id="movie_player"><video class="html5-main-video"></video></div></body>`,
    { url: startUrl || URLS[playbackIndex] + "&list=PL1", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const win = dom.window;
  const events = [];
  const navs = [];
  const store = {
    playbackState: { urls: URLS, index: playbackIndex, active: true, adSkipDelaySeconds: delay },
  };
  win.__navigate = (u) => navs.push(u);
  win.chrome = {
    runtime: {
      id: "test",
      sendMessage: (m) => {
        if (m && m.type === "event") events.push(m);
        return Promise.resolve();
      },
      onMessage: { addListener() {} },
    },
    storage: {
      local: {
        get: async (k) => ({ [k]: store[k] }),
        set: async (o) => Object.assign(store, o),
      },
    },
  };
  if (session) win.sessionStorage.setItem("bayzbot.adReloads", JSON.stringify(session));
  const video = win.document.querySelector("video");
  const m = { duration: 200, currentTime: 10, paused: false, ended: false, currentSrc: "song.mp4", readyState: 4 };
  for (const k of Object.keys(m)) {
    Object.defineProperty(video, k, { get: () => m[k], set: (v) => (m[k] = v), configurable: true });
  }
  video.play = () => Promise.resolve();
  video.pause = () => {};
  win.eval(CODE);
  await sleep(60);

  const doc = win.document;
  const player = doc.getElementById("movie_player");
  const env = {
    win, doc, events, navs, store, m,
    names: () => events.map((e) => e.event),
    count: (n) => events.filter((e) => e.event === n).length,
    last: (n) => [...events].reverse().find((e) => e.event === n),
    showAd(opts = {}) {
      const { duration = 180, src = "ad.mp4", button = false, disabled = false } = opts;
      m.duration = duration; m.currentSrc = src; m.currentTime = 0;
      if (!doc.querySelector(".ad-showing")) {
        const d = doc.createElement("div");
        d.className = "ad-showing";
        player.appendChild(d);
      }
      env.setButton(button, disabled);
    },
    setButton(on, disabled = false) {
      doc.querySelectorAll(".ytp-ad-skip-button").forEach((b) => b.remove());
      if (on) {
        const b = doc.createElement("button");
        b.className = "ytp-ad-skip-button";
        b.textContent = "Skip";
        if (disabled) b.setAttribute("aria-disabled", "true");
        b.__clicks = 0;
        b.addEventListener("click", () => {
          b.__clicks += 1;
          if (b.__works) env.endAdNow();
        });
        player.appendChild(b);
      }
    },
    button: () => doc.querySelector(".ytp-ad-skip-button"),
    endAdNow(restore = true) {
      doc.querySelectorAll(".ad-showing,.ytp-ad-skip-button").forEach((e) => e.remove());
      if (restore) { m.duration = 200; m.currentSrc = "song.mp4"; m.currentTime = 10; }
    },
    close() { win.close(); },
  };
  return env;
}

let pass = 0, fail = 0;
async function scenario(name, fn) {
  let env;
  try {
    env = await fn();
    console.log("  ok  ", name);
    pass++;
  } catch (e) {
    console.log("  GAGAL", name, "\n       ", e.message);
    fail++;
  }
}
function eq(a, b, msg) { if (a !== b) throw new Error(`${msg}: harapan ${b}, dapat ${a}`); }

module.exports = { makeEnv, sleep, scenario, eq, T, URLS, VID, CONSTS, report: () => ({ pass, fail }) };
