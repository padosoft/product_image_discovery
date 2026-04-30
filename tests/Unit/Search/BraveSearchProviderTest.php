<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use Padosoft\ProductImageDiscovery\Services\Search\BraveSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class BraveSearchProviderTest extends TestCase
{
    public function test_it_parses_brave_image_results_through_http_fake(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.search.brave.com/res/v1/images/search*' => \Illuminate\Support\Facades\Http::response([
                'results' => [[
                    'title' => 'Brand Model',
                    'url' => 'https://shop.example.test/products/brand-model',
                    'source' => 'shop.example.test',
                    'page_fetched' => '2026-03-06T15:49:26Z',
                    'thumbnail' => ['src' => 'https://cdn.example.test/thumb.jpg'],
                    'properties' => [
                        'url' => 'https://cdn.example.test/hero.jpg',
                        'width' => 1400,
                        'height' => 1400,
                    ],
                ]],
            ], 200),
        ]);

        $provider = new BraveSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'brave',
            'name' => 'Brave',
            'driver' => 'brave',
            'api_key' => 'secret',
            'timeout_seconds' => 7,
        ]));

        $results = $provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Brand Model',
        ]));

        self::assertCount(1, $results);
        self::assertSame('https://shop.example.test/products/brand-model', $results->first()?->pageUrl);
        self::assertSame('https://cdn.example.test/hero.jpg', $results->first()?->imageUrl);
        self::assertSame('shop.example.test', $results->first()?->sourceDomain);
    }
}
