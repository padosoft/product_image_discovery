---
title: Motivazione
description: Why conservative image discovery exists.
---

# Motivazione

Il problema non e trovare una qualunque immagine. Il problema e trovare l'immagine corretta per una specifica variante prodotto-colore.

In cataloghi ERP, PIM e marketplace, un falso positivo puo generare resi, ticket, perdita di fiducia e correzioni manuali. Per questo `product_image_discovery` preferisce uno stato di revisione o rifiuto quando l'evidenza non e sufficiente.

::: callout info
Il valore principale del pacchetto e ridurre il rischio operativo della pubblicazione automatica.
:::

## Design Consequence

Every candidate should answer four questions:

| Question | Required evidence |
| --- | --- |
| Is this the same product model? | Brand, model code, title, EAN, or source page context. |
| Is this the same color variant? | Color name, ERP color id, image analysis, or structured variant data. |
| Is this usable? | Resolution, aspect ratio, download success, and quality score. |
| Can we explain it? | Source, score, status, rejection reason, and audit events. |
