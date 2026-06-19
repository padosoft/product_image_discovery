---
title: Teoria
description: The matching theory behind low false-positive discovery.
---

# Teoria

Product image discovery is an evidence aggregation problem. Search results are noisy, page extraction is partial, and image galleries often contain multiple variants.

## Evidence Model

Think of each candidate as a set of signals:

```text
candidate = source + text + image + metadata + history
```

A candidate should move forward only when enough independent signals agree. In simplified form:

$$ score(c) = w_s s(c) + w_i i(c) + w_q q(c) - w_r r(c) $$

Where:

| Term | Meaning |
| --- | --- |
| `s(c)` | Source trust and authorization signal. |
| `i(c)` | Identity match across model, color, brand, and EAN. |
| `q(c)` | Image quality and catalog usability. |
| `r(c)` | Risk penalty for ambiguity, broken assets, or mixed variants. |

::: callout warning
This formula is conceptual. The public contract is the request, candidate, setting, provider, source, and event data exposed by the package.
:::
