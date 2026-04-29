"use strict";

const { extractImagesFromHtml } = require("../extract-images");
const { SidecarError } = require("../errors");

function createFallbackRenderer() {
  return {
    name: "fallback-fetch",
    async render({ url, timeoutMs, extract }) {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), timeoutMs);

      try {
        const response = await fetch(url, {
          method: "GET",
          redirect: "follow",
          signal: controller.signal,
          headers: {
            "user-agent": "product-image-discovery-sidecar/fallback"
          }
        });

        const html = extract.html || extract.images ? await response.text() : null;

        return {
          finalUrl: response.url || url,
          html: extract.html ? html : null,
          images: extract.images ? extractImagesFromHtml(html, response.url || url) : []
        };
      } catch (error) {
        if (error && error.name === "AbortError") {
          throw new SidecarError("Fallback renderer timeout", "TIMEOUT", 504);
        }
        throw error;
      } finally {
        clearTimeout(timeout);
      }
    }
  };
}

module.exports = {
  createFallbackRenderer
};
