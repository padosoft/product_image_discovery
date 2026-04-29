"use strict";

const { BrowserUnavailableError, SidecarError } = require("../errors");
const { normalizeImageUrl } = require("../extract-images");

let cachedPlaywrightModule = null;

async function loadPlaywrightModule() {
  if (cachedPlaywrightModule !== null) {
    return cachedPlaywrightModule;
  }

  try {
    cachedPlaywrightModule = await import("playwright");
    return cachedPlaywrightModule;
  } catch {
    throw new BrowserUnavailableError(
      "Playwright package is not installed. Install it to enable browser rendering."
    );
  }
}

function normalizeImages(rawImages, finalUrl) {
  const unique = [];
  const seen = new Set();

  for (const item of rawImages || []) {
    const normalizedUrl = normalizeImageUrl(item.url, finalUrl);
    if (!normalizedUrl || seen.has(normalizedUrl)) {
      continue;
    }

    seen.add(normalizedUrl);
    unique.push({
      url: normalizedUrl,
      width: Number.isFinite(item.width) ? item.width : null,
      height: Number.isFinite(item.height) ? item.height : null,
      alt: typeof item.alt === "string" && item.alt !== "" ? item.alt : null
    });
  }

  return unique;
}

function createPlaywrightRenderer() {
  return {
    name: "playwright",
    async render({ url, timeoutMs, waitUntil, extract }) {
      const playwright = await loadPlaywrightModule();
      const chromium = playwright.chromium;

      if (!chromium || typeof chromium.launch !== "function") {
        throw new BrowserUnavailableError("Playwright chromium launcher is not available");
      }

      let browser = null;
      let context = null;
      let page = null;

      try {
        browser = await chromium.launch({ headless: true });
        context = await browser.newContext();
        page = await context.newPage();
        await page.goto(url, { waitUntil, timeout: timeoutMs });

        const finalUrl = page.url();
        const result = {
          finalUrl,
          html: null,
          images: []
        };

        if (extract.html) {
          result.html = await page.content();
        }

        if (extract.images) {
          const images = await page.evaluate(() =>
            Array.from(document.querySelectorAll("img")).map((img) => ({
              url: img.currentSrc || img.src || "",
              width: Number(img.naturalWidth || img.width || 0),
              height: Number(img.naturalHeight || img.height || 0),
              alt: img.getAttribute("alt") || ""
            }))
          );
          result.images = normalizeImages(images, finalUrl);
        }

        return result;
      } catch (error) {
        const message = String(error && error.message ? error.message : error || "");
        if (message.toLowerCase().includes("executable doesn't exist")) {
          throw new BrowserUnavailableError("Playwright browser binaries are not installed");
        }

        throw new SidecarError(
          `Playwright render failed: ${message || "unknown error"}`,
          "PLAYWRIGHT_RENDER_FAILED",
          502
        );
      } finally {
        if (page) {
          await page.close().catch(() => {});
        }
        if (context) {
          await context.close().catch(() => {});
        }
        if (browser) {
          await browser.close().catch(() => {});
        }
      }
    }
  };
}

module.exports = {
  createPlaywrightRenderer
};
