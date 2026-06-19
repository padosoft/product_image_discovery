---
title: Installation
description: Install package assets into a Laravel host app.
---

# Installation

Install the package in the Laravel host application that owns product ingestion and catalog review.

```bash
composer require padosoft/product-image-discovery
```

The package auto-registers `Padosoft\ProductImageDiscovery\ProductImageDiscoveryServiceProvider` through Composer Laravel discovery.

## Publish Assets

```bash
php artisan vendor:publish --tag=product-image-discovery-config
php artisan vendor:publish --tag=product-image-discovery-migrations
```

Run migrations after reviewing the table names and index strategy.

```bash
php artisan migrate
```

Seed default settings and provider templates:

```bash
php artisan db:seed --class="Padosoft\ProductImageDiscovery\Database\Seeders\ProductImageDiscoveryDefaultsSeeder"
```

## Host App Requirements

| Requirement | Why it matters |
| --- | --- |
| PHP 8.3+ | Package source and tests target modern PHP types. |
| Laravel 13 components | Composer constraints use Illuminate `^13.0`. |
| Queue worker | Production pipelines should run asynchronously. |
| Sanctum-compatible auth | API middleware checks product-image-discovery abilities. |
| Writable storage disk | Downloaded candidates need durable storage. |

::: callout warning
Do not wire candidates straight to public catalog publishing until your trusted sources, thresholds, and manual review policy are tested with real false-positive examples.
:::

## Sidecar Optionality

The package can operate without Node or Playwright when search provider results already include usable image URLs. Enable the sidecar only for sources that require browser rendering or structured page extraction.
