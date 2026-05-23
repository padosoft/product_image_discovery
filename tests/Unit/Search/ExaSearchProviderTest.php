<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use Illuminate\Http\Client\RequestException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\ExaSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class ExaSearchProviderTest extends TestCase
{
    public function test_it_flattens_extras_image_links_into_one_candidate_per_image(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.exa.ai/search*' => \Illuminate\Support\Facades\Http::response([
                'requestId' => 'req-1',
                'results' => [[
                    'title' => 'Nike AF1 page',
                    'url' => 'https://www.nike.com/t/air-force-1-jBrhbr',
                    'id' => 'nike-af1',
                    'image' => 'https://cdn.nike.com/hero.jpg',
                    'text' => 'Iconic AF1.',
                    'score' => 0.81,
                    'extras' => [
                        'imageLinks' => [
                            'https://cdn.nike.com/hero.jpg',
                            'https://cdn.nike.com/side.jpg',
                            'https://cdn.nike.com/back.jpg',
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $results = $this->makeProvider()->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'Nike Air Force 1',
            'limit' => 5,
        ]));

        // 3 distinct image links (primary already in extras, deduped)
        self::assertCount(3, $results);
        self::assertSame('https://cdn.nike.com/hero.jpg', $results->first()?->imageUrl);
        self::assertSame('https://www.nike.com/t/air-force-1-jBrhbr', $results->first()?->pageUrl);
        self::assertSame('www.nike.com', $results->first()?->sourceDomain);
    }

    public function test_it_returns_empty_collection_when_no_image_links(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.exa.ai/search*' => \Illuminate\Support\Facades\Http::response([
                'results' => [[
                    'title' => 'Page without images',
                    'url' => 'https://example.test/p',
                    'id' => 'x',
                ]],
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
            'https://api.exa.ai/search*' => \Illuminate\Support\Facades\Http::response([
                'message' => 'Invalid API key',
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
            'https://api.exa.ai/search*' => \Illuminate\Support\Facades\Http::response([
                'results' => [[
                    'url' => 'https://nike.com/p',
                    'extras' => ['imageLinks' => ['https://nike.com/x.jpg']],
                ]],
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
                && is_array($body['contents']['extras'] ?? null)
                && ($body['contents']['extras']['imageLinks'] ?? null) === 5
                && is_array($body['includeDomains'] ?? null)
                && ($body['includeDomains'][0] ?? null) === 'nike.com'
                && $request->hasHeader('x-api-key', 'test-key');
        });
    }

    public function test_web_search_returns_normalized_results_without_image_links(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://api.exa.ai/search*' => \Illuminate\Support\Facades\Http::response([
                'results' => [[
                    'title' => 'Nike AF1',
                    'url' => 'https://www.nike.com/t/air-force-1',
                    'text' => 'Page content.',
                    'score' => 0.77,
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
        self::assertSame(0.77, $first->score);
    }

    private function makeProvider(): ExaSearchProvider
    {
        return new ExaSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'exa',
            'name' => 'Exa',
            'driver' => 'exa',
            'base_url' => 'https://api.exa.ai',
            'api_key' => 'test-key',
            'timeout_seconds' => 7,
            'config' => [
                'search_type' => 'auto',
                'image_links_per_result' => 5,
            ],
        ]));
    }
}
