/**
 * Render the generated HTML deck to a print-ready PDF (one 16:9 slide per page)
 * using the system Chrome via puppeteer-core. Run after build_deck.py.
 */
const path = require("path");
const puppeteer = require("puppeteer-core");

const CHROME =
  process.env.CHROME_PATH ||
  "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const HTML =
  "file://" +
  path.resolve(__dirname, "..", "taqatspace-features.html").replace(/\\/g, "/");
const OUT = path.resolve(__dirname, "..", "taqatspace-features.pdf");

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: "new",
    args: ["--no-sandbox", "--disable-gpu"],
  });
  const page = await browser.newPage();
  await page.goto(HTML, { waitUntil: "networkidle2", timeout: 120000 });
  // Let remote fonts + local images settle before printing.
  await new Promise((r) => setTimeout(r, 1800));
  await page.pdf({
    path: OUT,
    printBackground: true,
    preferCSSPageSize: true,
  });
  await browser.close();
  console.log("PDF ->", OUT);
})();
