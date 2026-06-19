---
title: CLI
description: Command reference.
---

# CLI

## Debug Flow

```bash
php artisan product-image-discovery:debug-flow path/to/request.json
```

Use this command to exercise the live pipeline with a specific product payload. It is especially useful before enabling new providers or AI verification in production.

## Useful Laravel Commands

```bash
php artisan vendor:publish --tag=product-image-discovery-config
php artisan vendor:publish --tag=product-image-discovery-migrations
php artisan db:seed --class="Padosoft\ProductImageDiscovery\Database\Seeders\ProductImageDiscoveryDefaultsSeeder"
php artisan migrate
```
