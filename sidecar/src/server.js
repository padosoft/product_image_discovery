"use strict";

const { createHttpServer } = require("./app");

function start() {
  const { app, server } = createHttpServer();
  const { host, port } = app.config;

  server.listen(port, host, () => {
    // eslint-disable-next-line no-console
    console.log(`[sidecar] listening on http://${host}:${port}`);
  });

  server.on("error", (error) => {
    // eslint-disable-next-line no-console
    console.error(`[sidecar] server error: ${error && error.message ? error.message : error}`);
    process.exitCode = 1;
  });
}

if (require.main === module) {
  start();
}

module.exports = {
  start
};
