<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\AssessImageQualityJob;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryCandidate;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Storage\EloquentPipelineStore;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class EloquentQualityDecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->artisan('migrate')->run();
    }

    public function test_manual_review_decision_does_not_write_non_enum_rejection_reason(): void
    {
        $request = ProductImageDiscoveryRequest::query()->create([
            'client_id' => 1,
            'erp_model_id' => 'NIKE-AF1-07',
            'erp_model_color_id' => 'NIKE-AF1-07-CW2288-111',
            'brand' => 'Nike',
            'supplier' => 'Nike',
            'supplier_sku' => 'CW2288-111',
            'model_code' => 'Air Force 1 07',
            'color_name' => 'White',
            'category' => 'Sneakers',
            'material' => 'Leather',
            'raw_payload' => ['source' => 'unit-test'],
            'status' => ProductImageDiscoveryRequestStatus::Downloaded,
        ]);

        $candidate = ProductImageDiscoveryCandidate::query()->create([
            'request_id' => $request->id,
            'client_id' => $request->client_id,
            'fingerprint' => sha1('nike-af1-white'),
            'source_domain' => 'nike.com',
            'source_page_url' => 'https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr',
            'image_url' => 'https://static.nike.com/a/images/t_prod/w_1200/air-force-1-07.jpg',
            'status' => ProductImageDiscoveryCandidateStatus::Downloaded,
            'source_trust_score' => 0,
            'textual_match_score' => 50,
            'structured_match_score' => 0,
            'visual_match_score' => 0,
            'quality_score' => 0,
            'risk_penalty' => 15,
            'final_score' => 60,
            'width' => 1200,
            'height' => 1200,
            'mime_type' => 'image/jpeg',
            'file_size' => 120000,
            'evidence' => [
                'matches' => ['brand', 'model_phrase', 'color_name'],
                'strong_matches' => ['model_phrase'],
                'source' => ['allow_auto_publish' => false, 'allow_download' => true],
            ],
            'ai_analysis' => ['has_strong_match' => true],
            'quality_analysis' => [],
        ]);

        $result = (new AssessImageQualityJob($request->id, $candidate->id))->handle(
            new EloquentPipelineStore(),
            app(ProductImageEventLogger::class),
        );

        self::assertSame(ProductImageDiscoveryCandidateStatus::QualityPassed->value, $result['status']);

        $request->refresh();
        self::assertSame(ProductImageDiscoveryRequestStatus::ManualReview, $request->status);
        self::assertNull($request->rejection_reason);
        self::assertSame($candidate->id, $request->best_candidate_id);
    }
}
