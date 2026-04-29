"use strict";

const http = require("node:http");

async function startServer(server) {
  await new Promise((resolve) => {
    server.listen(0, "127.0.0.1", resolve);
  });

  const address = server.address();
  return {
    server,
    baseUrl: `http://127.0.0.1:${address.port}`
  };
}

async function stopServer(server) {
  await new Promise((resolve) => server.close(() => resolve()));
}

async function startFixtureServer() {
  const fixture = http.createServer((req, res) => {
    if (req.url === "/product") {
      const html = `<!doctype html>
        <html>
          <head><title>Fixture Product</title></head>
          <body>
            <h1>Fixture Product</h1>
            <img src="/assets/hero.jpg" alt="hero image">
            <img src="https://cdn.example.test/p2.jpg" alt="gallery image">
          </body>
        </html>`;
      res.writeHead(200, { "content-type": "text/html; charset=utf-8" });
      res.end(html);
      return;
    }

    if (req.url === "/redirect") {
      res.writeHead(302, { location: "/product" });
      res.end();
      return;
    }

    if (req.url === "/slow") {
      setTimeout(() => {
        res.writeHead(200, { "content-type": "text/html; charset=utf-8" });
        res.end("<html><body><img src=\"/late.jpg\"></body></html>");
      }, 300);
      return;
    }

    res.writeHead(404, { "content-type": "text/plain" });
    res.end("not found");
  });

  return startServer(fixture);
}

module.exports = {
  startServer,
  stopServer,
  startFixtureServer
};
