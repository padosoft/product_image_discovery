<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use Illuminate\Http\Client\RequestException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\WebSearchApiSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class WebSearchApiSearchProviderTest extends TestCase
{
    public function test_search_images_returns_empty_collection(): void
    {
        $provider = $this->makeProvider();

        self::assertFalse($provider->supportsImageSearch());
        self::assertTrue($provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]))->isEmpty());
    }

    public function test_web_search_parses_organic_results(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.websearchapi.ai/ai-search*' => \Illuminate\Support\Facades\Http::response([
                'searchParameters' => ['query' => 'Air Force 1'],
                'organic' => [
                    [
                        'title' => 'Nike AF1 page',
                        'url' => 'https://www.nike.com/t/air-force-1',
                        'description' => 'Iconic AF1 sneaker.',
                        'content' => 'Long content excerpt',
                        'position' => 1,
                        'score' => 0.91,
                    ],
                    [
                        'title' => 'Retailer',
                        'url' => 'https://www.example.test/af1',
                        'description' => 'AF1 also sold here.',
                        'position' => 2,
                        'score' => 0.81,
                    ],
                ],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'Air Force 1',
            'limit' => 5,
        ]));

        self::assertCount(2, $results);
        $first = $results->first();
        self::assertNotNull($first);
        self::assertSame('https://www.nike.com/t/air-force-1', $first->pageUrl);
        self::assertSame('www.nike.com', $first->sourceDomain);
        self::assertSame('Iconic AF1 sneaker.', $first->snippet);
        self::assertSame(0.91, $first->score);
    }

    public function test_web_search_returns_empty_when_organic_missing(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.websearchapi.ai/ai-search*' => \Illuminate\Support\Facades\Http::response([
                'answer' => 'No matches.',
            ], 200),
        ]);

        $results = $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
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
            'https://api.websearchapi.ai/ai-search*' => \Illuminate\Support\Facades\Http::response([
                'error' => 'Invalid API key',
            ], 401),
        ]);

        $this->expectException(RequestException::class);

        $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]));
    }

    public function test_site_filter_is_propagated_as_include_domains(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.websearchapi.ai/ai-search*' => \Illuminate\Support\Facades\Http::response([
                'organic' => [],
            ], 200),
        ]);

        $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'Air Force 1',
            'site' => 'nike.com',
            'limit' => 7,
        ]));

        \Illuminate\Support\Facades\Http::assertSent(static function ($request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['query'] ?? null) === 'Air Force 1'
                && ($body['maxResults'] ?? null) === 7
                && is_array($body['includeDomains'] ?? null)
                && ($body['includeDomains'][0] ?? null) === 'nike.com'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    private function makeProvider(): WebSearchApiSearchProvider
    {
        return new WebSearchApiSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'websearchapi',
            'name' => 'WebSearchAPI',
            'driver' => 'websearchapi',
            'base_url' => 'https://api.websearchapi.ai',
            'api_key' => 'test-key',
            'timeout_seconds' => 7,
        ]));
    }
}
