---
title: AI And Vision
description: Use AI verification as evidence, not as the only control.
---

# AI And Vision

AI verification can compare visual and textual candidate evidence against product identity. It should support, not replace, deterministic checks.

## Appropriate Uses

::: grids
::: grid
**Variant disambiguation**

Check whether visual color and visible labels match the requested model-color.
:::
::: grid
**Reason generation**

Explain why a candidate moved to manual review.
:::
::: grid
**Tie breaking**

Help rank candidates after trusted-source and quality evidence already pass.
:::
:::

::: card
**Decision rule**

AI evidence can support a candidate, but it should not override source policy or weak identity input.
:::

## Guardrails

::: callout warning
Never treat a model response as proof of usage rights, source authorization, or catalog correctness. Those remain policy and data responsibilities.
:::

The useful scoring intuition is:

$$ confidence = P(match \mid text, image, source, history) $$

The production decision still needs thresholds, source rules, and review states.
