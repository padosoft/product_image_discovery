<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\DuckDuckGoSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveDuckDuckGoSearchProviderTest extends TestCase
{
    public function testLiveDuckDuckGoReturnsWebResults(): void
    {
        // DuckDuckGo HTML lite is unauthenticated but applies aggressive
        // anti-bot rate limiting from shared CI runner IPs. Skip in CI to
        // avoid hammering the endpoint; run locally on demand.
        if (getenv('CI') === 'true' || getenv('CI') === '1') {
            self::markTestSkipped('DuckDuckGo live test is skipped in CI to avoid rate-limiting from shared runner IPs.');
        }

        $provider = new DuckDuckGoSearchProvider(SearchProviderDefinition::fromArray([
            'code' => 'duckduckgo-live',
            'name' => 'DuckDuckGo Live',
            'driver' => 'duckduckgo',
            'base_url' => 'https://html.duckduckgo.com',
            'timeout_seconds' => 20,
            'is_active' => true,
        ]));

        try {
            $results = $provider->searchWeb(ProductImageSearchQueryData::fromArray([
                'query' => 'nike air force 1 07 white',
                'site' => 'nike.com',
                'limit' => 5,
            ]));
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            $status = $exception->response->status();

            if (in_array($status, [403, 429, 503], true)) {
                self::markTestSkipped(sprintf(
                    'DuckDuckGo HTML lite returned HTTP %d (anti-bot block). This is expected from shared/datacenter IPs and not a regression.',
                    $status,
                ));
            }

            throw $exception;
        }

        self::assertFalse($results->isEmpty(), 'DuckDuckGo HTML lite returned zero web results for the live query.');

        $first = $results->first();
        self::assertNotNull($first);
        self::assertNotSame('', trim((string) $first->title));
        self::assertStringStartsWith('http', (string) $first->pageUrl);
    }
}
