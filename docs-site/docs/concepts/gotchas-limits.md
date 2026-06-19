---
title: Gotchas And Limits
description: Known operational limits and false-positive traps.
---

# Gotchas And Limits

::: callout warning
The package cannot prove usage rights, license compatibility, or supplier authorization. It can only apply the source and workflow rules you configure.
:::

## Common Traps

| Trap | Consequence | Mitigation |
| --- | --- | --- |
| Mixed color galleries | Wrong variant image accepted. | Require color evidence or manual review. |
| Search snippets only | Weak identity proof. | Prefer source pages and structured metadata. |
| Marketplace duplicates | Same model, wrong seller or variant. | Use trusted source policy and review. |
| Low-resolution thumbnails | Catalog quality failure. | Enforce quality thresholds. |
| Browser-rendered galleries | Static extraction misses images. | Use sidecar selectively. |

## Rate Limits

Search providers and source pages have their own terms and rate limits. Configure timeouts and priorities conservatively, then monitor audit events for repeated provider failures.
