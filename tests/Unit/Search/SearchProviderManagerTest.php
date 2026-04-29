<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Padosoft\ProductImageDiscovery\Services\Search\CallableSearchProviderFactory;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\FakeSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderManager;
use Tests\Unit\Search\Support\InMemorySearchProviderConfigRepository;

final class SearchProviderManagerTest extends TestCase
{
    public function test_it_falls_back_to_next_active_provider_and_keeps_timeout_metadata_safe(): void
    {
        $primary = SearchProviderDefinition::fromArray([
            'code' => 'primary',
            'name' => 'Primary',
            'driver' => 'fake',
            'priority' => 10,
            'timeout_seconds' => 3,
            'api_key' => 'super-secret',
            'config' => ['throw' => true],
        ]);
        $fallback = SearchProviderDefinition::fromArray([
            'code' => 'fallback',
            'name' => 'Fallback',
            'driver' => 'fake',
            'priority' => 20,
            'timeout_seconds' => 9,
            'config' => [
                'image_results' => [[
                    'title' => 'Brand Model Red',
                    'page_url' => 'https://example.test/products/1',
                    'image_url' => 'https://cdn.example.test/products/1.jpg',
                    'source_domain' => 'example.test',
                    'score' => 0.91,
                ]],
            ],
        ]);

        $manager = new SearchProviderManager(
            repository: new InMemorySearchProviderConfigRepository([$primary, $fallback]),
            factories: [
                'fake' => new CallableSearchProviderFactory(
                    static fn (SearchProviderDefinition $definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                ),
            ],
        );

        $execution = $manager->searchImages(ProductImageSearchQueryData::fromArray([
            'brand' => 'Brand',
            'model' => 'Model',
            'color' => 'Red',
        ]));

        self::assertTrue($execution->usedFallback);
        self::assertSame('fallback', $execution->provider?->code);
        self::assertCount(2, $execution->attempts);
        self::assertSame('failed', $execution->attempts[0]['status']);
        self::assertSame(3, $execution->attempts[0]['timeout_seconds']);
        self::assertArrayNotHasKey('api_key', $execution->attempts[0]['provider']);
        self::assertSame(1, $execution->results->count());
    }
}
