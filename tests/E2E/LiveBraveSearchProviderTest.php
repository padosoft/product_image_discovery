<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Search\BraveSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveBraveSearchProviderTest extends TestCase
{
    public function testLiveBraveImageSearchReturnsProductLikeResults(): void
    {
        $apiKey = $this->envValue('BRAVE_SEARCH_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set BRAVE_SEARCH_API_KEY in .env to run the live Brave Search test.');
        }

        $provider = new BraveSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'brave-live',
            'name' => 'Brave Live',
            'driver' => 'brave',
            'base_url' => 'https://api.search.brave.com',
            'api_key' => $apiKey,
            'timeout_seconds' => 20,
            'is_active' => true,
        ]));

        $results = $provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1 07 white product image',
            'site' => 'nike.com',
            'limit' => 5,
        ]));

        self::assertFalse($results->isEmpty(), 'Brave returned no image results for the live smoke query.');

        $first = $results->first();
        self::assertNotNull($first);
        self::assertNotSame('', trim((string) $first->title));
        self::assertNotSame('', trim((string) $first->imageUrl));
        self::assertStringStartsWith('http', (string) $first->imageUrl);
    }

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

        $execution = $manager->searchImages(ProductImageSearchQueryData::fromArray([
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
