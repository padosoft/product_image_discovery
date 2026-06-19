---
title: Modello Dati
description: Data model and contract overview.
---

# Modello Dati

The central identity is `client_id + erp_model_color_id`. That pair lets the package reason about a specific product-color variant rather than a generic model.

## Core Tables

| Model | Purpose |
| --- | --- |
| `ProductImageDiscoveryRequest` | Inbound discovery request and lifecycle status. |
| `ProductImageDiscoveryCandidate` | Candidate image, source, score, status, rejection reason, and metadata. |
| `ProductImageDiscoverySourcePage` | Source page context discovered during extraction. |
| `ProductImageDiscoveryEvent` | Audit log and diagnostic events. |
| `ProductImageDiscoverySetting` | Runtime settings. |
| `ProductImageSearchProvider` | Backward-compatible provider model for the search-provider table. |
| `ProductImageTrustedSource` | Trusted domains and source rules. |

## Request Contract

```json
{
  "client_id": 1,
  "erp_model_id": "MODEL-1",
  "erp_model_color_id": "MODEL-1-BLACK",
  "brand": "Demo",
  "model_code": "MODEL-1",
  "color_name": "Black",
  "ean": "0000000000000"
}
```

::: callout note
Some fields are optional in specific workflows, but weaker identity input usually increases review or rejection rates.
:::
