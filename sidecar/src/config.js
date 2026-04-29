"use strict";

function parsePositiveInt(value, fallback) {
  if (value === undefined || value === null || value === "") {
    return fallback;
  }

  const parsed = Number.parseInt(String(value), 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }

  return parsed;
}

function loadConfig(env = process.env) {
  const defaultTimeoutMs = parsePositiveInt(env.SIDECAR_DEFAULT_TIMEOUT_MS, 15000);
  const maxTimeoutMs = parsePositiveInt(env.SIDECAR_MAX_TIMEOUT_MS, 30000);

  return {
    host: env.SIDECAR_HOST || "127.0.0.1",
    port: parsePositiveInt(env.SIDECAR_PORT, 3100),
    sharedSecret: env.SIDECAR_SHARED_SECRET || "",
    defaultTimeoutMs: Math.min(defaultTimeoutMs, maxTimeoutMs),
    maxTimeoutMs
  };
}

module.exports = {
  loadConfig
};
