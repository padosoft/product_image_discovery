"use strict";

const { SidecarError } = require("./errors");

function validateAndNormalizeUrl(rawUrl) {
  if (typeof rawUrl !== "string" || rawUrl.trim() === "") {
    throw new SidecarError("Field 'url' must be a non-empty string", "INVALID_URL", 422);
  }

  let parsed;
  try {
    parsed = new URL(rawUrl);
  } catch {
    throw new SidecarError("Field 'url' is not a valid URL", "INVALID_URL", 422);
  }

  if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
    throw new SidecarError("Only http/https URLs are supported", "INVALID_URL_SCHEME", 422);
  }

  if (!parsed.hostname) {
    throw new SidecarError("URL hostname is required", "INVALID_URL", 422);
  }

  if (parsed.username || parsed.password) {
    throw new SidecarError("Credentials in URL are not allowed", "INVALID_URL_CREDENTIALS", 422);
  }

  return parsed.toString();
}

module.exports = {
  validateAndNormalizeUrl
};
