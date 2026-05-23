<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\WebSearchApiSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\Concerns\ReadsLocalEnv;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveWebSearchApiSearchProviderTest extends TestCase
{
    use ReadsLocalEnv;

    public function testLiveWebSearchApiReturnsOrganicWebResults(): void
    {
        $apiKey = $this->envValue('WEBSEARCHAPI_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set WEBSEARCHAPI_API_KEY in .env to run the live WebSearchAPI test.');
        }

        $provider = new WebSearchApiSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'websearchapi-live',
            'name' => 'WebSearchAPI Live',
            'driver' => 'websearchapi',
            'base_url' => 'https://api.websearchapi.ai',
            'api_key' => $apiKey,
            'timeout_seconds' => 30,
            'is_active' => true,
        ]));

        $results = $provider->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1 07 white sneaker',
            'limit' => 5,
        ]));

        self::assertFalse($results->isEmpty(), 'WebSearchAPI returned no organic results for the live smoke query.');

        $first = $results->first();
        self::assertNotNull($first);
        self::assertNotSame('', trim((string) $first->title));
        self::assertNotSame('', trim((string) $first->pageUrl));
        self::assertStringStartsWith('http', (string) $first->pageUrl);
    }
}
