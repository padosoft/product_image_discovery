<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use Illuminate\Http\Client\RequestException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\DuckDuckGoSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class DuckDuckGoSearchProviderTest extends TestCase
{
    public function test_image_search_is_disabled(): void
    {
        $provider = $this->makeProvider();

        self::assertFalse($provider->supportsImageSearch());
        self::assertTrue($provider->searchImages(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]))->isEmpty());
    }

    public function test_it_parses_html_results_and_decodes_uddg_redirects(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://html.duckduckgo.com/html/*' => \Illuminate\Support\Facades\Http::response(
                $this->fixture('duckduckgo-html-lite.html'),
                200,
            ),
        ]);

        $results = $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'nike air force 1',
            'limit' => 5,
        ]));

        self::assertCount(2, $results);

        $first = $results->first();
        self::assertNotNull($first);
        self::assertSame("Nike Air Force 1 '07 Men's Shoes", $first->title);
        self::assertSame('https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr', $first->pageUrl);
        self::assertSame('www.nike.com', $first->sourceDomain);
        self::assertNotNull($first->snippet);
        self::assertStringContainsString('AF1', $first->snippet);
    }

    public function test_it_returns_empty_collection_when_html_has_no_results(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://html.duckduckgo.com/html/*' => \Illuminate\Support\Facades\Http::response(
                '<html><body><p>No results.</p></body></html>',
                200,
            ),
        ]);

        $results = $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'whatever',
        ]));

        self::assertTrue($results->isEmpty());
    }

    public function test_it_appends_site_operator_to_query(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://html.duckduckgo.com/html/*' => \Illuminate\Support\Facades\Http::response(
                '<html><body><div class="result"></div></body></html>',
                200,
            ),
        ]);

        $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'Air Force 1',
            'site' => 'nike.com',
        ]));

        \Illuminate\Support\Facades\Http::assertSent(static function ($request): bool {
            parse_str((string) $request->body(), $form);

            return ($form['q'] ?? null) === 'Air Force 1 site:nike.com'
                && $request->method() === 'POST';
        });
    }

    public function test_it_propagates_http_errors(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            $this->markTestSkipped('Illuminate HTTP client is not available.');
        }

        \Illuminate\Support\Facades\Http::fake([
            'https://html.duckduckgo.com/html/*' => \Illuminate\Support\Facades\Http::response('rate limited', 429),
        ]);

        $this->expectException(RequestException::class);

        $this->makeProvider()->searchWeb(ProductImageSearchQueryData::fromArray([
            'query' => 'anything',
        ]));
    }

    private function makeProvider(): DuckDuckGoSearchProvider
    {
        return new DuckDuckGoSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'duckduckgo',
            'name' => 'DuckDuckGo',
            'driver' => 'duckduckgo',
            'base_url' => 'https://html.duckduckgo.com',
            'timeout_seconds' => 10,
        ]));
    }

    private function fixture(string $name): string
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . $name;

        $content = file_get_contents($path);

        if ($content === false) {
            self::fail('Fixture not found: ' . $path);
        }

        return $content;
    }
}
