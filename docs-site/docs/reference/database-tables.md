---
title: Database Tables
description: Database table reference.
---

# Database Tables

| Table purpose | Model |
| --- | --- |
| Discovery requests | `ProductImageDiscoveryRequest` |
| Candidate images | `ProductImageDiscoveryCandidate` |
| Source pages | `ProductImageDiscoverySourcePage` |
| Events | `ProductImageDiscoveryEvent` |
| Settings | `ProductImageDiscoverySetting` |
| Search providers | `ProductImageSearchProvider` |
| Trusted sources | `ProductImageTrustedSource` |

## Operational Notes

::: steps
1. Index request status and client identity fields for review dashboards.
2. Keep event retention long enough to investigate bad publishing decisions.
3. Redact provider secrets before storing metadata.
4. Back up candidate metadata with the host catalog database.
:::
