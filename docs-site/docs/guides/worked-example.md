---
title: Worked Example
description: Walk through a product-color discovery request.
---

# Worked Example

Example product:

```json
{
  "client_id": 7,
  "erp_model_id": "AF1-07",
  "erp_model_color_id": "AF1-07-WHITE",
  "brand": "Nike",
  "model_code": "Air Force 1 '07",
  "color_name": "White",
  "ean": "0195866186775"
}
```

## What The Package Tries To Prove

::: steps
1. The candidate is about Nike Air Force 1, not another Nike shoe.
2. The color variant is white, not black, beige, or mixed.
3. The image is usable as a product catalog image.
4. The source is allowed by the configured provider and trusted-source policy.
5. The decision is explainable enough for review.
:::

## Candidate Evaluation

::: grids
::: grid
**Good candidate**

Brand page, matching EAN, white product photo, square image, high resolution.
:::
::: grid
**Review candidate**

Retailer page with matching text but gallery contains multiple colors.
:::
::: grid
**Rejected candidate**

Blog image with matching model words but wrong colorway or lifestyle-only photo.
:::
:::

::: card
**Review tip**

Save rejected examples. They are the fastest way to tune thresholds and trusted-source rules.
:::

## Debug Command

Use the package debug command with an example payload:

```bash
php artisan product-image-discovery:debug-flow examples/requests/nike-air-force-1-live.json
```

The output should be read as evidence, not as a production publishing decision. Keep rejected examples because they are useful for threshold tuning.
