/**
 * Headless capture of the static TaqatSpace design prototype (DOCS/Desing) into
 * real screenshots for the features deck. The prototype is a Babel-in-browser
 * React SPA whose active screen is read from localStorage `tq_route` (+ `tq_lang`
 * / `tq_theme`), so each capture sets those keys, loads index.html, waits for the
 * React mount + boot splash to clear, and snapshots a fixed desktop viewport.
 *
 * Uses puppeteer-core driving the system Chrome (no Chromium download). The
 * prototype is served over HTTP (Babel fetches the .jsx files via XHR, which
 * file:// blocks). Run via `node capture.js` with the server already up.
 */
const fs = require("fs");
const path = require("path");
const puppeteer = require("puppeteer-core");

const BASE = process.env.PROTO_URL || "http://localhost:8099/index.html";
const CHROME =
  process.env.CHROME_PATH ||
  "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const OUT = path.resolve(__dirname, "..", "img");

const VIEWPORT = { width: 1440, height: 900, deviceScaleFactor: 2 };

// [route, lang, theme, slug]. Arabic light is the primary set; a few extras show
// the English locale and dark mode.
const SHOTS = [
  // Public site (Arabic)
  ["home", "ar", "light", "01-public-home"],
  ["explore", "ar", "light", "02-public-explore"],
  ["detail", "ar", "light", "03-public-detail"],
  ["reg-free", "ar", "light", "04-register-freelancer"],
  ["reg-space", "ar", "light", "05-register-workspace"],
  ["login", "ar", "light", "06-login"],
  // Owner dashboard
  ["owner:dashboard", "ar", "light", "10-owner-dashboard"],
  ["owner:members", "ar", "light", "11-owner-members"],
  ["owner:seats", "ar", "light", "12-owner-seats"],
  ["owner:requests", "ar", "light", "13-owner-requests"],
  ["owner:invoices", "ar", "light", "14-owner-invoices"],
  ["owner:packages", "ar", "light", "15-owner-packages"],
  ["owner:reports", "ar", "light", "16-owner-reports"],
  // Freelancer dashboard
  ["member:home", "ar", "light", "20-freelancer-home"],
  ["member:subscription", "ar", "light", "21-freelancer-subscription"],
  ["member:invoices", "ar", "light", "22-freelancer-invoices"],
  ["member:notifications", "ar", "light", "23-freelancer-notifications"],
  ["member:profile", "ar", "light", "24-freelancer-profile"],
  // Super admin
  ["admin:analytics", "ar", "light", "30-admin-analytics"],
  ["admin:workspaces", "ar", "light", "31-admin-workspaces"],
  ["admin:users", "ar", "light", "32-admin-users"],
  ["admin:finance", "ar", "light", "33-admin-finance"],
  // Design system
  ["foundations", "ar", "light", "40-foundations"],
  // Extras: English locale + dark mode showcase
  ["home", "en", "light", "50-public-home-en"],
  ["admin:analytics", "en", "light", "51-admin-analytics-en"],
  ["owner:invoices", "ar", "dark", "52-owner-invoices-dark"],
  ["member:home", "en", "light", "53-freelancer-home-en"],
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function capture(browser, [route, lang, theme, slug]) {
  const page = await browser.newPage();
  await page.setViewport(VIEWPORT);

  // Seed the SPA's persisted state before any of its scripts run.
  await page.evaluateOnNewDocument(
    (r, l, t) => {
      try {
        localStorage.setItem("tq_route", r);
        localStorage.setItem("tq_lang", l);
        localStorage.setItem("tq_theme", t);
      } catch (e) {
        /* ignore */
      }
    },
    route,
    lang,
    theme,
  );

  await page.goto(BASE, { waitUntil: "networkidle2", timeout: 90000 });

  // Wait for the React mount + boot splash to clear (Babel compiles in-browser).
  await page.waitForFunction(
    () => {
      const root = document.getElementById("root");
      const boot = document.getElementById("boot");
      return (
        root &&
        root.children.length > 0 &&
        (!boot || boot.classList.contains("hide"))
      );
    },
    { timeout: 90000 },
  );

  // Let fonts + layout settle, then close the floating launcher/tweaks chrome
  // so they never sit over the captured screen.
  await sleep(1400);
  await page.addStyleTag({
    content:
      ".launcher-fab,.launcher-panel,.tweaks-fab,.tweaks-panel,#boot{display:none !important;}",
  });
  await sleep(250);

  const file = path.join(OUT, `${slug}.png`);
  await page.screenshot({ path: file, fullPage: false });
  await page.close();
  return file;
}

(async () => {
  if (!fs.existsSync(CHROME)) {
    console.error("Chrome not found at:", CHROME);
    process.exit(1);
  }
  fs.mkdirSync(OUT, { recursive: true });

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: "new",
    args: ["--no-sandbox", "--disable-gpu", "--hide-scrollbars"],
  });

  let ok = 0;
  for (const shot of SHOTS) {
    try {
      const file = await capture(browser, shot);
      ok++;
      console.log(`OK   ${shot[3]} -> ${path.basename(file)}`);
    } catch (err) {
      console.error(`FAIL ${shot[3]}: ${err.message}`);
    }
  }

  await browser.close();
  console.log(`\nDone: ${ok}/${SHOTS.length} screens captured into ${OUT}`);
})();
