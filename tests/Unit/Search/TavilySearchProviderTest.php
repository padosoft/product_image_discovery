<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use Illuminate\Http\Client\RequestException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\TavilySearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class TavilySearchProviderTest extends TestCase
{
    public function test_it_parses_tavily_image_payload_with_object_images(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.tavily.com/search*' => \Illuminate\Support\Facades\Http::response([
                'query' => 'Nike Air Force 1',
                'images' => [
                    ['url' => 'https://cdn.nike.com/products/af1-1.jpg', 'description' => 'White AF1'],
                    ['url' => 'https://cdn.example.test/other.jpg'],
                ],
                'results' => [[
                    'title' => 'Nike Air Force 1 product page',
                    'url' => 'https://www.nike.com/t/air-force-1-jBrhbr',
                    'content' => 'Iconic AF1.',
                    'score' => 0.93,
                ]],
            ], 200),
        ]);

        $provider = $this->makeProvider();

        $results = $provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1',
            'limit' => 5,
        ]));

        self::assertCount(2, $results);
        $first = $results->first();
        self::assertNotNull($first);
        self::assertSame('https://cdn.nike.com/products/af1-1.jpg', $first->imageUrl);
        self::assertSame('cdn.nike.com', $first->sourceDomain);
        self::assertSame('White AF1', $first->snippet);
    }

    public function test_it_accepts_legacy_image_string_payload(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.tavily.com/search*' => \Illuminate\Support\Facades\Http::response([
                'images' => [
                    'https://cdn.example.test/a.jpg',
                    'not-a-url-skip-me',
                    'https://cdn.example.test/b.jpg',
                ],
                'results' => [],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'shoes',
        ]));

        self::assertCount(2, $results);
        self::assertSame('https://cdn.example.test/a.jpg', $results->first()?->imageUrl);
    }

    public function test_empty_images_payload_returns_empty_collection(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.tavily.com/search*' => \Illuminate\Support\Facades\Http::response([
                'query' => 'anything',
                'images' => [],
                'results' => [],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]));

        self::assertTrue($results->isEmpty());
    }

    public function test_it_throws_on_unauthorized(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.tavily.com/search*' => \Illuminate\Support\Facades\Http::response([
                'detail' => 'Invalid API key',
            ], 401),
        ]);

        $this->expectException(RequestException::class);

        $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]));
    }

    public function test_site_filter_is_propagated_as_include_domains(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.tavily.com/search*' => \Illuminate\Support\Facades\Http::response([
                'images' => [['url' => 'https://nike.com/x.jpg']],
                'results' => [],
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
                && ($body['include_images'] ?? null) === true
                && is_array($body['include_domains'] ?? null)
                && ($body['include_domains'][0] ?? null) === 'nike.com'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_web_search_returns_normalized_results(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.tavily.com/search*' => \Illuminate\Support\Facades\Http::response([
                'results' => [[
                    'title' => 'Nike product',
                    'url' => 'https://www.nike.com/t/air-force-1',
                    'content' => 'AF1 page.',
                    'score' => 0.88,
                ]],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'Air Force 1',
        ]));

        self::assertCount(1, $results);
        $first = $results->first();
        self::assertNotNull($first);
        self::assertSame('https://www.nike.com/t/air-force-1', $first->pageUrl);
        self::assertSame('www.nike.com', $first->sourceDomain);
        self::assertSame(0.88, $first->score);
        self::assertNull($first->imageUrl);
    }

    private function makeProvider(): TavilySearchProvider
    {
        return new TavilySearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'tavily',
            'name' => 'Tavily',
            'driver' => 'tavily',
            'base_url' => 'https://api.tavily.com',
            'api_key' => 'test-key',
            'timeout_seconds' => 7,
            'config' => ['search_depth' => 'basic'],
        ]));
    }
}
