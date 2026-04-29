"use strict";

const http = require("node:http");
const { loadConfig } = require("./config");
const { SidecarError } = require("./errors");
const { createDefaultRenderer, normalizeRendererResult } = require("./renderer");
const { withHardTimeout } = require("./timeout");
const { validateAndNormalizeUrl } = require("./url");

function jsonResponse(res, statusCode, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(statusCode, {
    "content-type": "application/json; charset=utf-8",
    "content-length": Buffer.byteLength(body).toString()
  });
  res.end(body);
}

function renderErrorResponse(res, statusCode, code, message) {
  return jsonResponse(res, statusCode, {
    ok: false,
    final_url: null,
    html: null,
    images: [],
    error: {
      code,
      message
    }
  });
}

function readJsonBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;

    req.on("data", (chunk) => {
      size += chunk.length;
      if (size > 1024 * 1024) {
        reject(new SidecarError("Request payload is too large", "PAYLOAD_TOO_LARGE", 413));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });

    req.on("end", () => {
      if (chunks.length === 0) {
        resolve({});
        return;
      }

      try {
        resolve(JSON.parse(Buffer.concat(chunks).toString("utf8")));
      } catch {
        reject(new SidecarError("Invalid JSON body", "INVALID_JSON", 400));
      }
    });

    req.on("error", (error) => {
      reject(
        new SidecarError(
          `Failed to read request body: ${error && error.message ? error.message : "unknown error"}`,
          "REQUEST_READ_ERROR",
          400
        )
      );
    });
  });
}

function parseExtract(rawExtract) {
  if (rawExtract === undefined || rawExtract === null) {
    return { html: true, images: true };
  }

  if (typeof rawExtract !== "object" || Array.isArray(rawExtract)) {
    throw new SidecarError("Field 'extract' must be an object", "INVALID_EXTRACT", 422);
  }

  const extract = {
    html: rawExtract.html !== false,
    images: rawExtract.images !== false
  };

  if (typeof rawExtract.html !== "undefined" && typeof rawExtract.html !== "boolean") {
    throw new SidecarError("Field 'extract.html' must be a boolean", "INVALID_EXTRACT", 422);
  }
  if (typeof rawExtract.images !== "undefined" && typeof rawExtract.images !== "boolean") {
    throw new SidecarError("Field 'extract.images' must be a boolean", "INVALID_EXTRACT", 422);
  }

  return extract;
}

function parseTimeoutMs(rawTimeoutMs, config) {
  if (typeof rawTimeoutMs === "undefined" || rawTimeoutMs === null) {
    return config.defaultTimeoutMs;
  }

  const parsed = Number.parseInt(String(rawTimeoutMs), 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    throw new SidecarError("Field 'timeout_ms' must be a positive integer", "INVALID_TIMEOUT", 422);
  }

  return Math.min(parsed, config.maxTimeoutMs);
}

function parseWaitUntil(rawWaitUntil) {
  const allowed = new Set(["load", "domcontentloaded", "networkidle", "commit"]);
  if (typeof rawWaitUntil === "undefined" || rawWaitUntil === null) {
    return "networkidle";
  }
  if (typeof rawWaitUntil !== "string" || !allowed.has(rawWaitUntil)) {
    throw new SidecarError(
      "Field 'wait_until' must be one of: load, domcontentloaded, networkidle, commit",
      "INVALID_WAIT_UNTIL",
      422
    );
  }

  return rawWaitUntil;
}

function isAuthorized(req, sharedSecret) {
  if (!sharedSecret) {
    return true;
  }

  const headerSecret = req.headers["x-sidecar-secret"];
  if (typeof headerSecret === "string" && headerSecret === sharedSecret) {
    return true;
  }

  const authHeader = req.headers.authorization;
  if (typeof authHeader === "string" && authHeader === `Bearer ${sharedSecret}`) {
    return true;
  }

  return false;
}

function createApp(options = {}) {
  const config = options.config || loadConfig(options.env);
  const renderer = options.renderer || createDefaultRenderer({ logger: options.logger || console });
  const logger = options.logger || console;

  async function handleRender(req, res) {
    if (!isAuthorized(req, config.sharedSecret)) {
      return renderErrorResponse(res, 401, "UNAUTHORIZED", "Missing or invalid sidecar secret");
    }

    try {
      const body = await readJsonBody(req);
      const normalizedUrl = validateAndNormalizeUrl(body.url);
      const timeoutMs = parseTimeoutMs(body.timeout_ms, config);
      const waitUntil = parseWaitUntil(body.wait_until);
      const extract = parseExtract(body.extract);

      const renderResult = await withHardTimeout(
        () =>
          renderer.render({
            url: normalizedUrl,
            timeoutMs,
            waitUntil,
            extract
          }),
        timeoutMs
      );

      const normalizedResult = normalizeRendererResult(renderResult, extract);

      return jsonResponse(res, 200, {
        ok: true,
        final_url: normalizedResult.finalUrl || normalizedUrl,
        html: normalizedResult.html,
        images: normalizedResult.images,
        error: null
      });
    } catch (error) {
      const sidecarError =
        error instanceof SidecarError
          ? error
          : new SidecarError(
              `Unexpected render error: ${error && error.message ? error.message : "unknown error"}`,
              "INTERNAL_ERROR",
              500
            );

      if (logger && typeof logger.error === "function" && sidecarError.statusCode >= 500) {
        logger.error(`[sidecar] ${sidecarError.code}: ${sidecarError.message}`);
      }

      return renderErrorResponse(
        res,
        sidecarError.statusCode || 500,
        sidecarError.code || "INTERNAL_ERROR",
        sidecarError.message || "Internal error"
      );
    }
  }

  async function handler(req, res) {
    if (req.method === "GET" && req.url === "/health") {
      return jsonResponse(res, 200, {
        ok: true,
        service: "playwright-sidecar"
      });
    }

    if (req.method === "POST" && req.url === "/render") {
      return handleRender(req, res);
    }

    return renderErrorResponse(res, 404, "NOT_FOUND", "Endpoint not found");
  }

  return {
    config,
    handler
  };
}

function createHttpServer(options = {}) {
  const app = createApp(options);
  const server = http.createServer((req, res) => {
    void app.handler(req, res);
  });

  return {
    app,
    server
  };
}

module.exports = {
  createApp,
  createHttpServer
};
