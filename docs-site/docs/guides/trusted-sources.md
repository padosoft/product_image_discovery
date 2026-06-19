---
title: Trusted Sources
description: Configure source policy for safer discovery.
---

# Trusted Sources

Trusted sources constrain the search space to places you are allowed to query and review. They also provide a positive signal during scoring.

## Source Types

| Type | Example | Notes |
| --- | --- | --- |
| Brand | `brand.example` | Strong identity signal when product pages are structured. |
| Supplier | `supplier.example` | Often best for B2B catalog feeds. |
| Marketplace | `marketplace.example` | Useful but can contain duplicate or mixed listings. |
| Search provider | Brave, Tavily, Exa, Firecrawl | Should be rate limited and audited. |

::: callout warning
Trusted does not mean automatically accepted. Product-color identity still needs evidence.
:::

## Recommended Policy

::: steps
1. Prefer official brand and supplier URLs.
2. Add exact URL patterns before broad domain rules.
3. Store source decisions in the database, not only environment variables.
4. Revisit source quality after catalog teams reject candidates.
:::
