<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery;

use Illuminate\Support\ServiceProvider;
use Padosoft\ProductImageDiscovery\Console\Commands\ProductImageDiscoveryDebugFlowCommand;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Ai\ProductImageAiVerifier;
use Padosoft\ProductImageDiscovery\Services\Logging\AuditEventStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\DatabaseAuditEventStore;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Search\BraveSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\CallableSearchProviderFactory;
use Padosoft\ProductImageDiscovery\Services\Search\DatabaseSearchProviderConfigRepository;
use Padosoft\ProductImageDiscovery\Services\Search\ExaSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\FakeSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderConfigRepositoryInterface;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Services\Search\TavilySearchProvider;
use Padosoft\ProductImageDiscovery\Services\Storage\EloquentPipelineStore;

final class ProductImageDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/product-image-discovery.php', 'product-image-discovery');

        $this->app->bind(PipelineStoreInterface::class, EloquentPipelineStore::class);
        $this->app->bind(SearchProviderConfigRepositoryInterface::class, DatabaseSearchProviderConfigRepository::class);
        $this->app->bind(AuditEventStoreInterface::class, DatabaseAuditEventStore::class);
        $this->app->singleton(ProductImageAiVerifier::class);

        $this->app->singleton(ProductImageEventLogger::class, function ($app): ProductImageEventLogger {
            return new ProductImageEventLogger($app->make(AuditEventStoreInterface::class));
        });

        $this->app->singleton(SearchProviderManager::class, function ($app): SearchProviderManager {
            return new SearchProviderManager(
                repository: $app->make(SearchProviderConfigRepositoryInterface::class),
                factories: [
                    'fake' => new CallableSearchProviderFactory(
                        static fn ($definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                    ),
                    'brave' => new CallableSearchProviderFactory(
                        static fn ($definition): BraveSearchProvider => new BraveSearchProvider($definition),
                    ),
                    'tavily' => new CallableSearchProviderFactory(
                        static fn ($definition): TavilySearchProvider => new TavilySearchProvider($definition),
                    ),
                    'exa' => new CallableSearchProviderFactory(
                        static fn ($definition): ExaSearchProvider => new ExaSearchProvider($definition),
                    ),
                ],
                logger: $app->make(ProductImageEventLogger::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProductImageDiscoveryDebugFlowCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../config/product-image-discovery.php' => config_path('product-image-discovery.php'),
        ], 'product-image-discovery-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'product-image-discovery-migrations');
    }
}
