---
title: Architecture Overview
description: System architecture and package boundaries.
---

# Architecture Overview

`product_image_discovery` is a Laravel package that plugs into a host app. It does not own the host catalog; it receives product identity, produces candidate image evidence, and exposes reviewable state.

```mermaid
C4Context
  title Product Image Discovery Context
  Person(catalog, "Catalog team", "Reviews candidate images")
  System(host, "Laravel host app", "ERP/PIM/API boundary")
  System_Boundary(pkg, "product_image_discovery") {
    System(api, "API layer", "Requests, providers, settings, candidates")
    System(jobs, "Pipeline jobs", "Search, extract, verify, download, quality")
    System(db, "Package tables", "Requests, candidates, events")
  }
  System_Ext(search, "Search providers", "Brave, Tavily, Exa, Firecrawl, and others")
  System_Ext(sidecar, "Playwright sidecar", "Optional browser rendering")
  System_Ext(ai, "AI verifier", "Optional vision reasoning")
  Rel(catalog, host, "Submits and reviews")
  Rel(host, api, "Calls")
  Rel(api, db, "Reads and writes")
  Rel(jobs, search, "Queries")
  Rel(jobs, sidecar, "Renders when enabled")
  Rel(jobs, ai, "Verifies when enabled")
```

## Boundaries

| Boundary | Owned by package | Owned by host app |
| --- | --- | --- |
| API resources | Yes | Auth policy and routing context |
| Queue jobs | Yes | Worker infrastructure |
| Storage | Writes via Laravel filesystem | Disk selection and retention |
| Publishing decision | Candidate state | Final catalog publication |
