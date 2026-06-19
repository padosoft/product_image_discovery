---
title: Quickstart
description: Run product_image_discovery locally with a deterministic provider.
---

# Quickstart

This path runs the complete ingest, search, extract, verify, download, and quality pipeline without paid API keys.

::: callout note
Prerequisites: PHP 8.3+, Composer, a Laravel 13 host app, SQLite, and a user model that can issue Sanctum tokens.
:::

::: steps
1. Require the package.

```bash
composer require padosoft/product-image-discovery
```

2. Publish config and migrations.

```bash
php artisan vendor:publish --tag=product-image-discovery-config
php artisan vendor:publish --tag=product-image-discovery-migrations
```

3. Create the SQLite database and migrate.

```bash
touch database/database.sqlite
php artisan migrate
```

PowerShell:

```powershell
New-Item -ItemType File database/database.sqlite -Force
php artisan migrate
```

4. Seed defaults and provider templates.

```bash
php artisan db:seed --class="Padosoft\ProductImageDiscovery\Database\Seeders\ProductImageDiscoveryDefaultsSeeder"
```

5. Force a synchronous local smoke test in `.env`.

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
QUEUE_CONNECTION=sync
PRODUCT_IMAGE_DISCOVERY_ROUTE_PREFIX=api/product-image-discovery
```

6. Create a fake provider row and token in Tinker.

```bash
php artisan tinker
```

```php
\Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider::updateOrCreate(
    ['code' => 'fake-smoke'],
    [
        'name' => 'Fake Smoke Provider',
        'driver' => 'fake',
        'config' => [
            'supports_image_search' => true,
            'image_results' => [[
                'title' => 'Demo result',
                'page_url' => 'https://example.test/p/demo',
                'image_url' => 'data:image/jpeg;base64,'.base64_encode(str_repeat('a', 120000)),
                'source_domain' => 'example.test',
                'width' => 1200,
                'height' => 1200,
                'provider_metadata' => [
                    'inline_image_base64' => base64_encode(str_repeat('a', 120000)),
                    'inline_extension' => 'jpg',
                ],
            ]],
        ],
        'priority' => 1,
        'timeout_seconds' => 10,
        'is_active' => true,
    ],
);

$user = \App\Models\User::factory()->create(['email' => 'pid-quickstart@example.test']);
echo $user->createToken('pid-quickstart', [
    'product-image-discovery:write',
    'product-image-discovery:read',
])->plainTextToken.PHP_EOL;
```

7. Start Laravel and submit a request.

```bash
php artisan serve
```

```bash
curl -X POST "http://127.0.0.1:8000/api/product-image-discovery/requests" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "erp_model_id": "DEMO-1",
    "erp_model_color_id": "DEMO-1-BLACK",
    "brand": "Demo",
    "model_code": "DEMO-1",
    "color_name": "Black"
  }'
```
:::

Expected response:

```json
{
  "ok": true,
  "request_id": 1,
  "status": "queued"
}
```

Next, inspect the request and candidates through the API, database tables, or your admin UI.
