<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Padosoft\LaravelAiSearchProviders\Models\SearchProviderConfig;

/**
 * Backwards-compatible Eloquent model.
 *
 * The search layer lives in `padosoft/laravel-ai-search-providers` since
 * `product-image-discovery v1.0.0`. This thin subclass preserves the legacy
 * table name (`product_image_search_providers`) and class name so existing
 * host applications, the `padosoft/product_image_discovery_admin` sister
 * package and any custom integrations keep working unchanged.
 *
 * New code should reference `Padosoft\LaravelAiSearchProviders\Models\SearchProviderConfig`
 * directly.
 */
class ProductImageSearchProvider extends SearchProviderConfig
{
    protected $table = 'product_image_search_providers';
}
