<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\TavilySearchProvider;
use Padosoft\ProductImageDiscovery\Tests\Concerns\ReadsLocalEnv;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveTavilySearchProviderTest extends TestCase
{
    use ReadsLocalEnv;

    public function testLiveTavilyImageSearchReturnsProductLikeResults(): void
    {
        $apiKey = $this->envValue('TAVILY_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set TAVILY_API_KEY in .env to run the live Tavily Search test.');
        }

        $provider = new TavilySearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'tavily-live',
            'name' => 'Tavily Live',
            'driver' => 'tavily',
            'base_url' => 'https://api.tavily.com',
            'api_key' => $apiKey,
            'timeout_seconds' => 30,
            'config' => ['search_depth' => 'basic'],
            'is_active' => true,
        ]));

        $results = $provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1 07 white product image',
            'limit' => 5,
        ]));

        self::assertFalse($results->isEmpty(), 'Tavily returned no image results for the live smoke query.');

        $first = $results->first();
        self::assertNotNull($first);
        self::assertNotSame('', trim((string) $first->title));
        self::assertNotSame('', trim((string) $first->imageUrl));
        self::assertStringStartsWith('http', (string) $first->imageUrl);
    }
}
