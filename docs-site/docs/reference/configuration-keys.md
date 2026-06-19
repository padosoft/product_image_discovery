---
title: Configuration Keys
description: Reference for important configuration areas.
---

# Configuration Keys

Review the published `config/product-image-discovery.php` for the exact key names in your installed version.

| Area | Typical contents |
| --- | --- |
| `routes` | Prefix and middleware. |
| `queue` | Queue names for pipeline jobs. |
| `storage` | Disk and path settings for downloaded candidates. |
| `quality` | Resolution, aspect, and scoring thresholds. |
| `ai` | Enablement, model provider, and verifier settings. |
| `sidecar` | Browser sidecar URL, timeout, and enablement. |
| `security` | Ability names and access policy hints. |

::: callout warning
Treat config changes as production behavior changes. Verify them with debug-flow payloads before relying on automatic acceptance.
:::
