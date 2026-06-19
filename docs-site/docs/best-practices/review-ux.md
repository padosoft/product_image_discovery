---
title: Review UX
description: Build review tooling that exposes evidence clearly.
---

# Review UX

Manual review works best when reviewers see evidence, not just thumbnails.

## Show These Fields

::: grids
::: grid
**Identity**

Brand, model code, ERP model id, ERP color id, EAN, and requested color.
:::
::: grid
**Candidate**

Image, source URL, source domain, dimensions, score, and status.
:::
::: grid
**Reasoning**

Quality signal, rejection reason, provider metadata, and audit events.
:::
:::

::: card
**Reviewer outcome**

The review UI should help a person explain an accept or reject decision later.
:::

## Avoid

Do not hide source or score details behind a single accept button. Reviewers need context to avoid repeating the same mistake.
