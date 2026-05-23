<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\LaravelAiSearchProviders\Data\SearchQueryData;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

/**
 * Backward-compatibility integration test.
 *
 * Exercises the wiring between `product-image-discovery` and the
 * `padosoft/laravel-ai-search-providers` package: a provider row is
 * created via the local `ProductImageSearchProvider` subclass (which
 * uses the legacy `product_image_search_providers` table), and the
 * package's `SearchProviderManager` is expected to resolve it through
 * the configured table/model overrides.
 */
final class LiveBraveSearchProviderTest extends TestCase
{
    public function testLiveBraveProviderCanBeResolvedFromDatabaseConfiguration(): void
    {
        $apiKey = $this->envValue('BRAVE_SEARCH_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set BRAVE_SEARCH_API_KEY in .env to run the live Brave Search manager test.');
        }

        $this->loadMigrationsFrom(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
        $this->artisan('migrate')->run();

        ProductImageSearchProvider::query()->create([
            'code' => 'brave-live',
            'name' => 'Brave Live',
            'driver' => 'brave',
            'base_url' => 'https://api.search.brave.com',
            'api_key_encrypted' => $apiKey,
            'config' => [
                'supports_image_search' => true,
                'supports_site_filter' => true,
            ],
            'priority' => 1,
            'timeout_seconds' => 20,
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        /** @var SearchProviderManager $manager */
        $manager = $this->app->make(SearchProviderManager::class);

        $execution = $manager->searchImages(SearchQueryData::fromArray([
            'query' => 'Nike Air Force 1 07 white product image',
            'site' => 'nike.com',
            'limit' => 3,
        ]));

        self::assertSame('brave-live', $execution->provider?->code);
        self::assertFalse($execution->results->isEmpty(), 'Brave returned no image results through SearchProviderManager.');
        self::assertSame('success', $execution->attempts[0]['status'] ?? null);
    }

    private function envValue(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

        if (! is_file($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);

            if (trim($name) !== $key) {
                continue;
            }

            $rawValue = trim($rawValue);

            if (
                strlen($rawValue) >= 2
                && (($rawValue[0] === '"' && $rawValue[strlen($rawValue) - 1] === '"')
                    || ($rawValue[0] === "'" && $rawValue[strlen($rawValue) - 1] === "'"))
            ) {
                $rawValue = substr($rawValue, 1, -1);
            }

            return trim($rawValue);
        }

        return null;
    }
}
