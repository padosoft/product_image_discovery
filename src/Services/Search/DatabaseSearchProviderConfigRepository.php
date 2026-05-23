<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

final class DatabaseSearchProviderConfigRepository implements SearchProviderConfigRepositoryInterface
{
    /**
     * @param  class-string|null  $providerModel  Override the Eloquent model used as the
     *         backing store. Useful when the search layer is extracted into
     *         padosoft/laravel-search-providers and the consumer wants to ship a
     *         generic SearchProviderConfig model instead of the domain-specific one.
     */
    public function __construct(
        private readonly ?string $providerModel = null,
    ) {
    }

    /**
     * @return array<int, SearchProviderDefinition>
     */
    public function getActiveProviders(): array
    {
        $modelClass = $this->resolveModelClass();

        if ($modelClass === null) {
            return [];
        }

        return $modelClass::query()
            ->active()
            ->ordered()
            ->get()
            ->map(static function ($provider): SearchProviderDefinition {
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

    /**
     * Resolve the model class from constructor override, host-app config or the
     * package default. Returning null disables the repository gracefully when
     * neither the override class nor the default model exists (pure unit tests).
     *
     * @return class-string|null
     */
    private function resolveModelClass(): ?string
    {
        if ($this->providerModel !== null && class_exists($this->providerModel)) {
            return $this->providerModel;
        }

        if (function_exists('config')) {
            $configured = config('product-image-discovery.models.search_provider');

            if (is_string($configured) && class_exists($configured)) {
                return $configured;
            }
        }

        return class_exists(ProductImageSearchProvider::class) ? ProductImageSearchProvider::class : null;
    }
}
