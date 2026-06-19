---
title: Observability
description: What to log and monitor.
---

# Observability

Monitor the pipeline by stage and by provider.

| Signal | Why |
| --- | --- |
| Request status counts | Detect stuck or failing workflows. |
| Candidate status counts | Track accept, reject, and review rates. |
| Provider latency and errors | Protect queue throughput. |
| Rejection reasons | Identify systematic false-positive causes. |
| Quality score distribution | Tune image thresholds. |

## Events

`ProductImageDiscoveryEvent` records package-level audit context. Keep secrets redacted and retain enough detail to reconstruct decisions.
