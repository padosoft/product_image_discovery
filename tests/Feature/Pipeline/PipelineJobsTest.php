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
use Padosoft\ProductImageDiscovery\Services\Search\CallableSearchProviderFactory;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\FakeSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderManager;
use Tests\Feature\Pipeline\Support\InMemoryPipelineStore;
use Tests\Unit\Search\Support\InMemorySearchProviderConfigRepository;

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
}
