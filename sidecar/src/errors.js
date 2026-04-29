"use strict";

class SidecarError extends Error {
  constructor(message, code, statusCode = 500) {
    super(message);
    this.name = "SidecarError";
    this.code = code;
    this.statusCode = statusCode;
  }
}

class BrowserUnavailableError extends SidecarError {
  constructor(message = "Playwright browser is not available") {
    super(message, "BROWSER_UNAVAILABLE", 503);
    this.name = "BrowserUnavailableError";
  }
}

module.exports = {
  SidecarError,
  BrowserUnavailableError
};
