<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery;

use Illuminate\Support\ServiceProvider;
use Padosoft\LaravelAiSearchProviders\Contracts\SearchEventLoggerInterface;
use Padosoft\ProductImageDiscovery\Console\Commands\ProductImageDiscoveryDebugFlowCommand;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Ai\ProductImageAiVerifier;
use Padosoft\ProductImageDiscovery\Services\Logging\AuditEventStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\DatabaseAuditEventStore;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Storage\EloquentPipelineStore;

final class ProductImageDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/product-image-discovery.php', 'product-image-discovery');

        // Forward the package config so the AI search-providers package keeps
        // using the legacy table + a domain-specific Eloquent model.
        $this->app->resolving('config', static function ($config): void {
            $config->set('ai-search-providers.table', 'product_image_search_providers');
            $config->set('ai-search-providers.model', ProductImageSearchProvider::class);
        });
        $this->app['config']->set('ai-search-providers.table', 'product_image_search_providers');
        $this->app['config']->set('ai-search-providers.model', ProductImageSearchProvider::class);

        $this->app->bind(PipelineStoreInterface::class, EloquentPipelineStore::class);
        $this->app->bind(AuditEventStoreInterface::class, DatabaseAuditEventStore::class);
        $this->app->singleton(ProductImageAiVerifier::class);

        $this->app->singleton(ProductImageEventLogger::class, function ($app): ProductImageEventLogger {
            return new ProductImageEventLogger($app->make(AuditEventStoreInterface::class));
        });

        // Plug the domain-specific audit logger into the package's
        // SearchProviderManager so every provider attempt lands in the
        // product_image_discovery_events audit trail.
        $this->app->bind(SearchEventLoggerInterface::class, ProductImageEventLogger::class);
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
