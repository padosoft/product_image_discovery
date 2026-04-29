"use strict";

const { SidecarError } = require("./errors");

async function withHardTimeout(promiseFactory, timeoutMs) {
  let timer = null;

  const timeoutPromise = new Promise((_, reject) => {
    timer = setTimeout(() => {
      reject(new SidecarError(`Render timeout after ${timeoutMs} ms`, "TIMEOUT", 504));
    }, timeoutMs);
  });

  try {
    return await Promise.race([promiseFactory(), timeoutPromise]);
  } finally {
    if (timer !== null) {
      clearTimeout(timer);
    }
  }
}

module.exports = {
  withHardTimeout
};
