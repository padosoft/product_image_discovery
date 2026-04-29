<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

final class DatabaseSearchProviderConfigRepository implements SearchProviderConfigRepositoryInterface
{
    /**
     * @return array<int, SearchProviderDefinition>
     */
    public function getActiveProviders(): array
    {
        if (! class_exists(ProductImageSearchProvider::class)) {
            return [];
        }

        return ProductImageSearchProvider::query()
            ->active()
            ->ordered()
            ->get()
            ->map(static function (ProductImageSearchProvider $provider): SearchProviderDefinition {
                return SearchProviderDefinition::fromArray([
                    'code' => $provider->code,
                    'name' => $provider->name,
                    'driver' => $provider->driver,
                    'base_url' => $provider->base_url,
                    'api_key' => $provider->api_key_encrypted,
                    'api_secret' => $provider->api_secret_encrypted,
                    'config' => $provider->config ?? [],
                    'priority' => $provider->priority,
                    'timeout_seconds' => $provider->timeout_seconds,
                    'rate_limit_per_minute' => $provider->rate_limit_per_minute,
                    'is_active' => $provider->is_active,
                ]);
            })
            ->all();
    }
}
