<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use Illuminate\Http\Client\RequestException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\FirecrawlSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class FirecrawlSearchProviderTest extends TestCase
{
    public function test_it_parses_firecrawl_image_payload(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.firecrawl.dev/v2/search*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'data' => [
                    'images' => [
                        [
                            'title' => 'Nike AF1',
                            'imageUrl' => 'https://cdn.nike.com/af1.jpg',
                            'imageWidth' => 1200,
                            'imageHeight' => 1200,
                            'url' => 'https://www.nike.com/t/air-force-1',
                            'position' => 1,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1',
            'limit' => 5,
        ]));

        self::assertCount(1, $results);
        $first = $results->first();
        self::assertNotNull($first);
        self::assertSame('https://cdn.nike.com/af1.jpg', $first->imageUrl);
        self::assertSame('https://www.nike.com/t/air-force-1', $first->pageUrl);
        self::assertSame('www.nike.com', $first->sourceDomain);
        self::assertSame(1200, $first->width);
        self::assertSame(1200, $first->height);
    }

    public function test_it_returns_empty_collection_when_data_images_missing(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.firecrawl.dev/v2/search*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'data' => ['web' => []],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'whatever',
        ]));

        self::assertTrue($results->isEmpty());
    }

    public function test_it_throws_on_unauthorized(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.firecrawl.dev/v2/search*' => \Illuminate\Support\Facades\Http::response([
                'success' => false,
                'error' => 'Unauthorized',
            ], 401),
        ]);

        $this->expectException(RequestException::class);

        $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]));
    }

    public function test_site_filter_is_propagated_as_include_domains_and_sources(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.firecrawl.dev/v2/search*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'data' => ['images' => []],
            ], 200),
        ]);

        $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Air Force 1',
            'site' => 'nike.com',
        ]));

        \Illuminate\Support\Facades\Http::assertSent(static function ($request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['query'] ?? null) === 'Air Force 1'
                && is_array($body['sources'] ?? null)
                && ($body['sources'][0]['type'] ?? null) === 'images'
                && is_array($body['includeDomains'] ?? null)
                && ($body['includeDomains'][0] ?? null) === 'nike.com'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_web_search_maps_data_web(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.firecrawl.dev/v2/search*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'data' => [
                    'web' => [[
                        'title' => 'Nike page',
                        'description' => 'AF1 page',
                        'url' => 'https://www.nike.com/t/air-force-1',
                    ]],
                ],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'Air Force 1',
        ]));

        self::assertCount(1, $results);
        self::assertSame('https://www.nike.com/t/air-force-1', $results->first()?->pageUrl);
        self::assertSame('AF1 page', $results->first()?->snippet);
    }

    private function makeProvider(): FirecrawlSearchProvider
    {
        return new FirecrawlSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'firecrawl',
            'name' => 'Firecrawl',
            'driver' => 'firecrawl',
            'base_url' => 'https://api.firecrawl.dev',
            'api_key' => 'test-key',
            'timeout_seconds' => 7,
        ]));
    }
}
