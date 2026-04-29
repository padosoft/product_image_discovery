<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Throwable;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderExecutionResult;

final class SearchProviderManager
{
    /**
     * @var array<string, SearchProviderFactoryInterface>
     */
    private array $factories = [];

    /**
     * @param  array<string, SearchProviderFactoryInterface>  $factories
     * @param  array<int, SearchProviderDefinition>  $fallbackProviders
     */
    public function __construct(
        private readonly SearchProviderConfigRepositoryInterface $repository,
        array $factories = [],
        private readonly array $fallbackProviders = [],
        private readonly ?ProductImageEventLogger $logger = null,
    ) {
        foreach ($factories as $driver => $factory) {
            $this->registerFactory($driver, $factory);
        }
    }

    public function registerFactory(string $driver, SearchProviderFactoryInterface $factory): self
    {
        $this->factories[$driver] = $factory;

        return $this;
    }

    public function searchImages(ProductImageSearchQueryData $query): SearchProviderExecutionResult
    {
        return $this->execute('searchImages', $query);
    }

    public function searchWeb(ProductImageSearchQueryData $query): SearchProviderExecutionResult
    {
        return $this->execute('searchWeb', $query);
    }

    private function execute(string $method, ProductImageSearchQueryData $query): SearchProviderExecutionResult
    {
        $definitions = $this->resolveDefinitions();
        $attempts = [];
        $fallbackUsed = false;
        $emptyResult = new ProductImageSearchResultCollection();

        foreach ($definitions as $index => $definition) {
            $attempt = [
                'provider' => $definition->toSafeArray(),
                'method' => $method,
                'timeout_seconds' => $definition->timeoutSeconds,
                'sequence' => $index + 1,
            ];

            try {
                $provider = $this->resolveProvider($definition);

                if ($method === 'searchImages' && ! $provider->supportsImageSearch()) {
                    $attempts[] = $attempt + ['status' => 'skipped', 'reason' => 'image_search_not_supported'];
                    continue;
                }

                if ($query->site !== null && $query->site !== '' && ! $provider->supportsSiteFilter()) {
                    $attempts[] = $attempt + ['status' => 'skipped', 'reason' => 'site_filter_not_supported'];
                    continue;
                }

                $results = $provider->{$method}($query);

                if ($results->isEmpty()) {
                    $attempts[] = $attempt + ['status' => 'empty'];
                    $fallbackUsed = $fallbackUsed || $index > 0;
                    continue;
                }

                $attempts[] = $attempt + ['status' => 'success', 'results_count' => $results->count()];

                $this->logger?->record('search.provider.success', [
                    'provider' => $definition->toSafeArray(),
                    'method' => $method,
                    'results_count' => $results->count(),
                    'used_fallback' => $index > 0,
                ]);

                return new SearchProviderExecutionResult(
                    provider: $definition,
                    results: $results,
                    attempts: $attempts,
                    usedFallback: $index > 0,
                );
            } catch (Throwable $exception) {
                $fallbackUsed = $fallbackUsed || $index > 0;
                $attempts[] = $attempt + [
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];

                $this->logger?->record('search.provider.failed', [
                    'provider' => $definition->toSafeArray(),
                    'method' => $method,
                    'error' => $exception->getMessage(),
                ], 'warning');
            }
        }

        return new SearchProviderExecutionResult(
            provider: null,
            results: $emptyResult,
            attempts: $attempts,
            usedFallback: $fallbackUsed,
        );
    }

    /**
     * @return array<int, SearchProviderDefinition>
     */
    private function resolveDefinitions(): array
    {
        $definitions = array_values(array_filter(
            $this->repository->getActiveProviders(),
            static fn (SearchProviderDefinition $definition): bool => $definition->isActive,
        ));

        if ($definitions === []) {
            $definitions = array_values(array_filter(
                $this->fallbackProviders,
                static fn (SearchProviderDefinition $definition): bool => $definition->isActive,
            ));
        }

        usort($definitions, static function (SearchProviderDefinition $left, SearchProviderDefinition $right): int {
            return $left->priority <=> $right->priority;
        });

        return $definitions;
    }

    private function resolveProvider(SearchProviderDefinition $definition): ProductImageSearchProviderInterface
    {
        $factory = $this->factories[$definition->driver] ?? null;

        if ($factory === null) {
            throw new RuntimeException(sprintf('Search provider driver [%s] is not registered.', $definition->driver));
        }

        return $factory->make($definition);
    }
}
