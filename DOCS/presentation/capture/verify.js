/**
 * Foolproof slide check: render ONE slide pinned to the top-left with the deck
 * padding/centering removed (mirrors print mode), then capture the 1280x720
 * viewport. Writes img/_verify_<idx>.png.
 */
const path = require("path");
const puppeteer = require("puppeteer-core");

const CHROME =
  process.env.CHROME_PATH ||
  "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const HTML =
  "file://" +
  path.resolve(__dirname, "..", "taqatspace-features.html").replace(/\\/g, "/");

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: "new",
    args: ["--no-sandbox", "--disable-gpu"],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 720, deviceScaleFactor: 1 });
  await page.goto(HTML, { waitUntil: "networkidle2", timeout: 120000 });
  await new Promise((r) => setTimeout(r, 1400));

  const want = [0, 1, 4, 16, 24, 29];
  for (const idx of want) {
    await page.evaluate((i) => {
      const deck = document.querySelector(".deck");
      deck.style.padding = "0";
      deck.style.gap = "0";
      const slides = [...document.querySelectorAll(".slide")];
      slides.forEach((s, j) => {
        s.style.display = j === i ? "flex" : "none";
        s.style.margin = "0";
      });
      window.scrollTo(0, 0);
    }, idx);
    await new Promise((r) => setTimeout(r, 200));
    await page.screenshot({
      path: path.resolve(__dirname, "..", "img", `_verify_${idx}.png`),
      clip: { x: 0, y: 0, width: 1280, height: 720 },
    });
  }
  await browser.close();
  console.log("isolated verify shots written for", want.join(","));
})();
