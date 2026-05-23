<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\ExaSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\Concerns\ReadsLocalEnv;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveExaSearchProviderTest extends TestCase
{
    use ReadsLocalEnv;

    public function testLiveExaImageSearchReturnsProductLikeResults(): void
    {
        $apiKey = $this->envValue('EXA_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set EXA_API_KEY in .env to run the live Exa Search test.');
        }

        $provider = new ExaSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'exa-live',
            'name' => 'Exa Live',
            'driver' => 'exa',
            'base_url' => 'https://api.exa.ai',
            'api_key' => $apiKey,
            'timeout_seconds' => 30,
            'config' => [
                'search_type' => 'auto',
                'image_links_per_result' => 3,
            ],
            'is_active' => true,
        ]));

        $results = $provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1 07 white sneaker product page',
            'limit' => 5,
        ]));

        self::assertFalse($results->isEmpty(), 'Exa returned no image results for the live smoke query.');

        $first = $results->first();
        self::assertNotNull($first);
        self::assertNotSame('', trim((string) $first->imageUrl));
        self::assertStringStartsWith('http', (string) $first->imageUrl);
    }
}
