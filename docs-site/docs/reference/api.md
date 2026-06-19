---
title: API
description: HTTP API reference.
---

# API

Routes are registered under the configured prefix, commonly:

```text
/api/product-image-discovery
```

## Request Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/requests` | Create a discovery request. |
| `GET` | `/requests` | Search or list requests. |
| `GET` | `/requests/{request}` | Read one request and its context. |

## Candidate Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/candidates` | List candidates. |
| `POST` | `/candidates/{candidate}/accept` | Accept a candidate. |
| `POST` | `/candidates/{candidate}/reject` | Reject a candidate with a reason. |

## Configuration Endpoints

| Resource | Purpose |
| --- | --- |
| Settings | Runtime behavior and thresholds. |
| Search providers | Provider driver, priority, timeout, active state, and config. |
| Trusted sources | Domain and source rules. |

::: callout note
Exact route names and middleware come from `routes/api.php` and the host app's published config.
:::
