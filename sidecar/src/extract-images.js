"use strict";

function parseAttributes(tag) {
  const attributes = {};
  const attributeRegex = /([^\s=]+)\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+))/gi;

  let match = attributeRegex.exec(tag);
  while (match) {
    const key = String(match[1] || "").toLowerCase();
    const value = match[3] ?? match[4] ?? match[5] ?? "";
    attributes[key] = value;
    match = attributeRegex.exec(tag);
  }

  return attributes;
}

function normalizeImageUrl(rawUrl, baseUrl) {
  if (!rawUrl || typeof rawUrl !== "string") {
    return null;
  }

  try {
    const resolved = new URL(rawUrl, baseUrl).toString();
    if (resolved.startsWith("http://") || resolved.startsWith("https://")) {
      return resolved;
    }
  } catch {
    return null;
  }

  return null;
}

function dedupeImages(images) {
  const unique = [];
  const seen = new Set();

  for (const image of images) {
    if (!image || !image.url || seen.has(image.url)) {
      continue;
    }
    seen.add(image.url);
    unique.push(image);
  }

  return unique;
}

function extractImagesFromHtml(html, baseUrl) {
  if (!html) {
    return [];
  }

  const tags = String(html).match(/<img\b[^>]*>/gi) || [];
  const images = [];

  for (const tag of tags) {
    const attrs = parseAttributes(tag);
    const srcCandidate = attrs.src || attrs["data-src"] || attrs["data-original"];
    const normalizedUrl = normalizeImageUrl(srcCandidate, baseUrl);

    if (!normalizedUrl) {
      continue;
    }

    images.push({
      url: normalizedUrl,
      width: null,
      height: null,
      alt: attrs.alt || null
    });
  }

  return dedupeImages(images);
}

module.exports = {
  extractImagesFromHtml,
  normalizeImageUrl
};
