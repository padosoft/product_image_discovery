<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use PHPUnit\Framework\TestCase;
use Padosoft\ProductImageDiscovery\Jobs\AssessImageQualityJob;
use Padosoft\ProductImageDiscovery\Jobs\DownloadCandidateImageJob;
use Padosoft\ProductImageDiscovery\Jobs\ExtractCandidateSourcesJob;
use Padosoft\ProductImageDiscovery\Jobs\IngestProductImageDiscoveryJob;
use Padosoft\ProductImageDiscovery\Jobs\SearchProductImageJob;
use Padosoft\ProductImageDiscovery\Jobs\VerifyCandidateImageJob;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\LaravelAiSearchProviders\CallableSearchProviderFactory;
use Padosoft\LaravelAiSearchProviders\Data\SearchProviderDefinition;
use Padosoft\LaravelAiSearchProviders\Providers\FakeSearchProvider;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Tests\Support\InMemorySearchProviderConfigRepository;
use Tests\Feature\Pipeline\Support\InMemoryPipelineStore;

final class PipelineJobsTest extends TestCase
{
    public function test_ingest_job_is_idempotent_for_same_identity(): void
    {
        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $payload = [
            'client_id' => 7,
            'erp_model_color_id' => 'ABC-RED',
            'brand' => 'Brand',
            'model' => 'Model',
            'color' => 'Red',
        ];

        $first = (new IngestProductImageDiscoveryJob($payload))->handle($store, $logger);
        $second = (new IngestProductImageDiscoveryJob($payload))->handle($store, $logger);

        self::assertSame($first['id'], $second['id']);
        self::assertCount(1, $store->requests);
    }

    public function test_ingest_job_can_resume_existing_persisted_request(): void
    {
        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $request = $store->upsertRequest(
            ['client_id' => 7, 'erp_model_color_id' => 'ABC-RED'],
            ['client_id' => 7, 'erp_model_color_id' => 'ABC-RED', 'status' => 'failed']
        );

        $resumed = (new IngestProductImageDiscoveryJob($request['id']))->handle($store, $logger);

        self::assertSame($request['id'], $resumed['id']);
        self::assertSame('queued', $resumed['status']);
        self::assertSame('pipeline.ingest.resumed', $store->events[0]['event_type']);
    }

    public function test_extract_job_deduplicates_candidates_and_source_pages(): void
    {
        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $request = $store->upsertRequest(
            ['client_id' => 1, 'erp_model_color_id' => 'sku-1'],
            ['client_id' => 1, 'brand' => 'Brand', 'model' => 'Model', 'color' => 'Red']
        );

        $store->mergeRequestContext($request['id'], [
            'search' => [
                'execution' => [
                    'results' => [
                        [
                            'fingerprint' => sha1('same'),
                            'title' => 'Brand Model Red',
                            'page_url' => 'https://shop.example.test/p/1',
                            'image_url' => 'https://cdn.example.test/p/1.jpg',
                        ],
                        [
                            'fingerprint' => sha1('same'),
                            'title' => 'Brand Model Red',
                            'page_url' => 'https://shop.example.test/p/1',
                            'image_url' => 'https://cdn.example.test/p/1.jpg',
                        ],
                    ],
                ],
            ],
        ]);

        $job = new ExtractCandidateSourcesJob($request['id']);
        $job->handle($store, $logger);
        $job->handle($store, $logger);

        self::assertCount(1, $store->candidates);
        self::assertCount(1, $store->sourcePages);
    }

    public function test_pipeline_jobs_execute_mvp_flow_without_duplicate_side_effects(): void
    {
        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $searchManager = new SearchProviderManager(
            repository: new InMemorySearchProviderConfigRepository([
                SearchProviderDefinition::fromArray([
                    'code' => 'fake',
                    'name' => 'Fake',
                    'driver' => 'fake',
                    'priority' => 10,
                    'config' => [
                        'image_results' => [[
                            'title' => 'Brand Model Red',
                            'page_url' => 'https://shop.example.test/p/1',
                            'image_url' => 'data:image/jpeg;base64,'.base64_encode(str_repeat('a', 120000)),
                            'source_domain' => 'shop.example.test',
                            'width' => 1200,
                            'height' => 1200,
                            'provider_metadata' => [
                                'inline_image_base64' => base64_encode(str_repeat('a', 120000)),
                                'inline_extension' => 'jpg',
                            ],
                        ]],
                    ],
                ]),
            ]),
            factories: [
                'fake' => new CallableSearchProviderFactory(
                    static fn (SearchProviderDefinition $definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                ),
            ],
        );

        $request = (new IngestProductImageDiscoveryJob([
            'client_id' => 11,
            'erp_model_color_id' => 'MODEL-RED',
            'brand' => 'Brand',
            'model_code' => 'Model',
            'color_name' => 'Red',
            'model' => 'Model',
            'color' => 'Red',
        ]))->handle($store, $logger);

        (new SearchProductImageJob($request['id']))->handle($store, $searchManager, $logger);
        (new ExtractCandidateSourcesJob($request['id']))->handle($store, $logger);

        $candidate = array_values($store->candidates)[0];

        (new VerifyCandidateImageJob($request['id'], $candidate['id']))->handle($store, $logger);
        (new DownloadCandidateImageJob($request['id'], $candidate['id']))->handle($store, $logger);
        (new AssessImageQualityJob($request['id'], $candidate['id']))->handle($store, $logger);

        $candidate = $store->getCandidate($candidate['id']);

        self::assertSame('quality_passed', $candidate['status']);
        self::assertGreaterThan(0, (int) ($candidate['file_size'] ?? 0));
        self::assertArrayHasKey('quality_score', $candidate);
        self::assertNotEmpty($store->events);
    }

    public function test_search_job_generates_site_queries_for_trusted_sources(): void
    {
        $store = new InMemoryPipelineStore();
        $store->trustedSources = [[
            'client_id' => 11,
            'domain' => 'brand.example.test',
            'trust_score' => 92,
            'is_active' => true,
            'allow_search' => true,
        ]];
        $logger = new ProductImageEventLogger($store);
        $searchManager = new SearchProviderManager(
            repository: new InMemorySearchProviderConfigRepository([
                SearchProviderDefinition::fromArray([
                    'code' => 'fake',
                    'name' => 'Fake',
                    'driver' => 'fake',
                    'priority' => 10,
                    'config' => ['image_results' => []],
                ]),
            ]),
            factories: [
                'fake' => new CallableSearchProviderFactory(
                    static fn (SearchProviderDefinition $definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                ),
            ],
        );

        $request = (new IngestProductImageDiscoveryJob([
            'client_id' => 11,
            'erp_model_color_id' => 'MODEL-RED',
            'brand' => 'Brand',
            'model_code' => 'Model',
            'color_name' => 'Red',
            'ean' => '8056713248026',
        ]))->handle($store, $logger);

        (new SearchProductImageJob($request['id']))->handle($store, $searchManager, $logger);

        $queries = $store->getRequest($request['id'])['context']['search']['queries'] ?? [];
        $intents = array_column($queries, 'intent');

        self::assertContains('site_ean', $intents);
        self::assertContains('site_model_code_color_name', $intents);

        $siteQueries = array_values(array_filter($queries, static fn (array $query): bool => $query['intent'] === 'site_ean'));
        self::assertSame('brand.example.test', $siteQueries[0]['site_domain'] ?? $siteQueries[0]['siteDomain'] ?? null);
    }

    public function test_search_job_discards_ean_results_that_do_not_mention_the_ean(): void
    {
        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $searchManager = new SearchProviderManager(
            repository: new InMemorySearchProviderConfigRepository([
                SearchProviderDefinition::fromArray([
                    'code' => 'fake',
                    'name' => 'Fake',
                    'driver' => 'fake',
                    'priority' => 10,
                    'config' => [
                        'image_results' => [[
                            'title' => 'Unrelated category page',
                            'page_url' => 'https://marketplace.example.test/category/shoes',
                            'image_url' => 'https://marketplace.example.test/banner.jpg',
                            'source_domain' => 'marketplace.example.test',
                        ]],
                    ],
                ]),
            ]),
            factories: [
                'fake' => new CallableSearchProviderFactory(
                    static fn (SearchProviderDefinition $definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                ),
            ],
        );

        $request = (new IngestProductImageDiscoveryJob([
            'client_id' => 11,
            'erp_model_color_id' => 'MODEL-RED',
            'brand' => 'Brand',
            'model_code' => 'Model',
            'color_name' => 'Red',
            'ean' => '8056713248026',
        ]))->handle($store, $logger);

        (new SearchProductImageJob($request['id']))->handle($store, $searchManager, $logger);

        $search = $store->getRequest($request['id'])['context']['search'];
        $executions = $search['executions'];

        self::assertSame('ean', $executions[0]['search_query']['intent']);
        self::assertSame(1, $executions[0]['discarded_results']);
        self::assertSame([], $executions[0]['execution']['results']);
        self::assertGreaterThan(1, count($executions));
        self::assertNotSame('ean', $executions[count($executions) - 1]['search_query']['intent']);
        self::assertNotEmpty($search['execution']['results']);
        self::assertSame('candidates_found', $store->getRequest($request['id'])['status']);
    }

    public function test_search_job_keeps_ean_results_that_mention_the_ean(): void
    {
        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $searchManager = new SearchProviderManager(
            repository: new InMemorySearchProviderConfigRepository([
                SearchProviderDefinition::fromArray([
                    'code' => 'fake',
                    'name' => 'Fake',
                    'driver' => 'fake',
                    'priority' => 10,
                    'config' => [
                        'image_results' => [[
                            'title' => 'Brand Model Red EAN 8056713248026',
                            'page_url' => 'https://shop.example.test/p/8056713248026',
                            'image_url' => 'https://shop.example.test/p/1.jpg',
                            'source_domain' => 'shop.example.test',
                        ]],
                    ],
                ]),
            ]),
            factories: [
                'fake' => new CallableSearchProviderFactory(
                    static fn (SearchProviderDefinition $definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                ),
            ],
        );

        $request = (new IngestProductImageDiscoveryJob([
            'client_id' => 11,
            'erp_model_color_id' => 'MODEL-RED',
            'brand' => 'Brand',
            'model_code' => 'Model',
            'color_name' => 'Red',
            'ean' => '8056713248026',
        ]))->handle($store, $logger);

        (new SearchProductImageJob($request['id']))->handle($store, $searchManager, $logger);

        $executions = $store->getRequest($request['id'])['context']['search']['executions'];

        self::assertCount(1, $executions);
        self::assertSame('ean', $executions[0]['search_query']['intent']);
        self::assertSame(0, $executions[0]['discarded_results']);
        self::assertNotEmpty($executions[0]['execution']['results']);
    }

    public function test_verify_job_scores_candidate_from_trusted_source_without_penalty(): void
    {
        $store = new InMemoryPipelineStore();
        $store->trustedSources = [[
            'client_id' => 7,
            'domain' => 'brand.example.test',
            'trust_score' => 90,
            'is_active' => true,
            'allow_download' => true,
            'allow_auto_publish' => true,
        ]];
        $logger = new ProductImageEventLogger($store);
        $request = $store->upsertRequest(
            ['client_id' => 7, 'erp_model_color_id' => 'SKU-1'],
            ['client_id' => 7, 'brand' => 'Brand', 'model_code' => 'Model', 'color_name' => 'Red']
        );
        $candidate = $store->upsertCandidate($request['id'], 'fp-1', [
            'status' => 'pending_verification',
            'title' => 'Brand Model Red',
            'source_page_url' => 'https://shop.brand.example.test/p/1',
            'image_url' => 'https://cdn.brand.example.test/p/1.jpg',
            'source_domain' => 'shop.brand.example.test',
        ]);

        (new VerifyCandidateImageJob($request['id'], $candidate['id']))->handle($store, $logger);

        $updated = $store->getCandidate($candidate['id']);

        self::assertSame(18, (int) $updated['source_trust_score']);
        self::assertSame(0, (int) $updated['risk_penalty']);
        self::assertNotContains('SOURCE_NOT_ALLOWED', $updated['ai_analysis']['issues'] ?? []);
    }

    public function test_verify_job_applies_settings_from_store(): void
    {
        $store = new InMemoryPipelineStore();
        $store->settings = ['non_trusted_source_penalty' => 33];
        $logger = new ProductImageEventLogger($store);
        $request = $store->upsertRequest(
            ['client_id' => 7, 'erp_model_color_id' => 'SKU-2'],
            ['client_id' => 7, 'brand' => 'Brand', 'model_code' => 'Model', 'color_name' => 'Red']
        );
        $candidate = $store->upsertCandidate($request['id'], 'fp-2', [
            'status' => 'pending_verification',
            'title' => 'Brand Model Red',
            'source_page_url' => 'https://unknown.example.test/p/1',
            'image_url' => 'https://unknown.example.test/p/1.jpg',
            'source_domain' => 'unknown.example.test',
        ]);

        (new VerifyCandidateImageJob($request['id'], $candidate['id']))->handle($store, $logger);

        $updated = $store->getCandidate($candidate['id']);

        self::assertSame(33, (int) $updated['risk_penalty']);
        self::assertContains('SOURCE_NOT_ALLOWED', $updated['ai_analysis']['issues'] ?? []);
    }
}
