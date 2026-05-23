<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\FirecrawlSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\Concerns\ReadsLocalEnv;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveFirecrawlSearchProviderTest extends TestCase
{
    use ReadsLocalEnv;

    public function testLiveFirecrawlImageSearchReturnsProductLikeResults(): void
    {
        $apiKey = $this->envValue('FIRECRAWL_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set FIRECRAWL_API_KEY in .env to run the live Firecrawl Search test.');
        }

        $provider = new FirecrawlSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'firecrawl-live',
            'name' => 'Firecrawl Live',
            'driver' => 'firecrawl',
            'base_url' => 'https://api.firecrawl.dev',
            'api_key' => $apiKey,
            'timeout_seconds' => 60,
            'is_active' => true,
        ]));

        $results = $provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1 07 white sneaker product page',
            'limit' => 5,
        ]));

        self::assertFalse($results->isEmpty(), 'Firecrawl returned no image results for the live smoke query.');

        $first = $results->first();
        self::assertNotNull($first);
        self::assertNotSame('', trim((string) $first->imageUrl));
        self::assertStringStartsWith('http', (string) $first->imageUrl);
    }
}
