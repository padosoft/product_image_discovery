"use strict";

const test = require("node:test");
const assert = require("node:assert/strict");
const { createHttpServer } = require("../src/app");
const { BrowserUnavailableError, SidecarError } = require("../src/errors");
const { createFallbackRenderer } = require("../src/renderers/fallback");
const { createRendererWithFallback } = require("../src/renderer");
const { startFixtureServer, startServer, stopServer } = require("./helpers");

async function jsonPost(url, payload, headers = {}) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      ...headers
    },
    body: JSON.stringify(payload)
  });
  const json = await response.json();
  return { response, json };
}

test("GET /health returns ok payload", async (t) => {
  const { server } = createHttpServer();
  const running = await startServer(server);
  t.after(async () => stopServer(running.server));

  const response = await fetch(`${running.baseUrl}/health`);
  const payload = await response.json();

  assert.equal(response.status, 200);
  assert.deepEqual(payload, {
    ok: true,
    service: "playwright-sidecar"
  });
});

test("POST /render returns normalized success shape", async (t) => {
  const mockRenderer = {
    async render() {
      return {
        finalUrl: "https://example.test/product/1",
        html: "<html><body>ok</body></html>",
        images: [{ url: "https://example.test/a.jpg", width: 1200, height: 1600, alt: "a" }]
      };
    }
  };

  const { server } = createHttpServer({ renderer: mockRenderer });
  const running = await startServer(server);
  t.after(async () => stopServer(running.server));

  const { response, json } = await jsonPost(`${running.baseUrl}/render`, {
    url: "https://example.test/product/1",
    timeout_ms: 2000
  });

  assert.equal(response.status, 200);
  assert.equal(json.ok, true);
  assert.equal(json.final_url, "https://example.test/product/1");
  assert.equal(typeof json.html, "string");
  assert.ok(Array.isArray(json.images));
  assert.equal(json.error, null);
});

test("POST /render rejects invalid URL", async (t) => {
  const { server } = createHttpServer();
  const running = await startServer(server);
  t.after(async () => stopServer(running.server));

  const { response, json } = await jsonPost(`${running.baseUrl}/render`, {
    url: "file:///etc/passwd"
  });

  assert.equal(response.status, 422);
  assert.equal(json.ok, false);
  assert.equal(json.final_url, null);
  assert.equal(json.html, null);
  assert.ok(Array.isArray(json.images));
  assert.equal(json.error.code, "INVALID_URL_SCHEME");
});

test("POST /render enforces optional shared secret", async (t) => {
  const { server } = createHttpServer({
    config: {
      host: "127.0.0.1",
      port: 3100,
      sharedSecret: "secret-123",
      defaultTimeoutMs: 15000,
      maxTimeoutMs: 30000
    },
    renderer: {
      async render() {
        return { finalUrl: "https://example.test", html: "<html></html>", images: [] };
      }
    }
  });

  const running = await startServer(server);
  t.after(async () => stopServer(running.server));

  const denied = await jsonPost(`${running.baseUrl}/render`, { url: "https://example.test" });
  assert.equal(denied.response.status, 401);
  assert.equal(denied.json.error.code, "UNAUTHORIZED");

  const allowed = await jsonPost(
    `${running.baseUrl}/render`,
    { url: "https://example.test" },
    { "x-sidecar-secret": "secret-123" }
  );
  assert.equal(allowed.response.status, 200);
  assert.equal(allowed.json.ok, true);
});

test("POST /render returns timeout error with hard timeout", async (t) => {
  const renderer = {
    async render() {
      await new Promise((resolve) => setTimeout(resolve, 200));
      return { finalUrl: "https://example.test", html: "<html></html>", images: [] };
    }
  };

  const { server } = createHttpServer({
    config: {
      host: "127.0.0.1",
      port: 3100,
      sharedSecret: "",
      defaultTimeoutMs: 50,
      maxTimeoutMs: 50
    },
    renderer
  });
  const running = await startServer(server);
  t.after(async () => stopServer(running.server));

  const { response, json } = await jsonPost(`${running.baseUrl}/render`, {
    url: "https://example.test",
    timeout_ms: 50
  });

  assert.equal(response.status, 504);
  assert.equal(json.ok, false);
  assert.equal(json.error.code, "TIMEOUT");
});

test("renderer fallback is used when browser is unavailable", async (t) => {
  const fixture = await startFixtureServer();
  t.after(async () => stopServer(fixture.server));

  const primary = {
    async render() {
      throw new BrowserUnavailableError("browser missing");
    }
  };
  const fallback = createFallbackRenderer();
  const renderer = createRendererWithFallback({ primary, fallback, logger: { warn() {} } });

  const result = await renderer.render({
    url: `${fixture.baseUrl}/redirect`,
    timeoutMs: 1000,
    waitUntil: "networkidle",
    extract: { html: true, images: true }
  });

  assert.equal(result.finalUrl, `${fixture.baseUrl}/product`);
  assert.equal(typeof result.html, "string");
  assert.ok(Array.isArray(result.images));
  assert.ok(result.images.length >= 2);
  assert.equal(result.images[0].url.startsWith("http://") || result.images[0].url.startsWith("https://"), true);
});

test("SidecarError renderer maps to error response shape", async (t) => {
  const renderer = {
    async render() {
      throw new SidecarError("upstream failed", "UPSTREAM_FAILURE", 502);
    }
  };
  const { server } = createHttpServer({ renderer });
  const running = await startServer(server);
  t.after(async () => stopServer(running.server));

  const { response, json } = await jsonPost(`${running.baseUrl}/render`, {
    url: "https://example.test"
  });

  assert.equal(response.status, 502);
  assert.equal(json.ok, false);
  assert.equal(json.final_url, null);
  assert.equal(json.html, null);
  assert.ok(Array.isArray(json.images));
  assert.deepEqual(Object.keys(json.error), ["code", "message"]);
});
