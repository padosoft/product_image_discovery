"use strict";

const { BrowserUnavailableError, SidecarError } = require("./errors");
const { createPlaywrightRenderer } = require("./renderers/playwright");
const { createFallbackRenderer } = require("./renderers/fallback");

function createDefaultRenderer({ logger = console } = {}) {
  const primary = createPlaywrightRenderer();
  const fallback = createFallbackRenderer();

  return createRendererWithFallback({ primary, fallback, logger });
}

function createRendererWithFallback({ primary, fallback, logger = console }) {
  return {
    name: "playwright-with-fallback",
    async render(payload) {
      try {
        return await primary.render(payload);
      } catch (error) {
        if (!(error instanceof BrowserUnavailableError)) {
          throw error;
        }

        if (logger && typeof logger.warn === "function") {
          logger.warn(
            `[sidecar] Playwright unavailable (${error.message}). Falling back to static fetch renderer.`
          );
        }

        return fallback.render(payload);
      }
    }
  };
}

function normalizeRendererResult(result, extract) {
  if (!result || typeof result !== "object") {
    throw new SidecarError("Renderer returned an invalid payload", "INVALID_RENDERER_RESULT", 500);
  }

  return {
    finalUrl: typeof result.finalUrl === "string" && result.finalUrl !== "" ? result.finalUrl : null,
    html: extract.html ? (typeof result.html === "string" ? result.html : "") : null,
    images: extract.images && Array.isArray(result.images) ? result.images : []
  };
}

module.exports = {
  createDefaultRenderer,
  createRendererWithFallback,
  normalizeRendererResult
};
