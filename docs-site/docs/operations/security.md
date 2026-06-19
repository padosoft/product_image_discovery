---
title: Security
description: Security and responsible-use notes.
---

# Security

## API Access

API routes are intended for authenticated host apps. Use Sanctum abilities such as:

```text
product-image-discovery:write
product-image-discovery:read
```

## Secret Handling

::: callout warning
Provider credentials must never be returned through API resources, review UIs, logs, or docs examples. Use `.env` and encrypted database fields where supported.
:::

## Responsible Use

Respect source terms, robots.txt, rate limits, and legal basis. The package helps discover and review images; it does not grant usage rights.
