---
title: Design
description: Key design choices and data flow.
---

# Design

The package separates ingestion, provider querying, candidate extraction, verification, download, quality analysis, and review.

```mermaid
flowchart TB
  subgraph Host[Laravel host app]
    API[API controllers]
    Config[Config and settings]
    Store[(Database)]
    Queue[Queue workers]
  end
  subgraph Package[product_image_discovery]
    Actions[Actions]
    Jobs[Jobs]
    Models[Models]
    Resources[Resources]
  end
  subgraph External[External systems]
    Providers[Search providers]
    Sidecar[Optional Playwright sidecar]
    AI[Optional AI verifier]
  end
  API --> Store
  API --> Queue
  Queue --> Jobs
  Jobs --> Actions
  Actions --> Providers
  Actions --> Sidecar
  Actions --> AI
  Jobs --> Models
  Models --> Store
  Resources --> API
```

## Principles

::: steps
1. Keep provider configuration data-backed.
2. Keep browser rendering outside PHP.
3. Keep AI optional and explainable.
4. Keep every decision auditable.
5. Prefer manual review over unsafe automatic acceptance.
:::
