<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use Padosoft\ProductImageDiscovery\Ai\Agents\ProductImageCandidateVerifierAgent;
use Padosoft\ProductImageDiscovery\Jobs\IngestProductImageDiscoveryJob;
use Padosoft\ProductImageDiscovery\Jobs\VerifyCandidateImageJob;
use Padosoft\ProductImageDiscovery\Services\Ai\ProductImageAiVerifier;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Tests\Feature\Pipeline\Support\InMemoryPipelineStore;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class AiVerificationJobTest extends TestCase
{
    public function test_verify_job_merges_ai_verification_into_candidate_analysis(): void
    {
        ProductImageCandidateVerifierAgent::fake([[
            'match' => true,
            'variant_safe' => true,
            'confidence' => 87,
            'brand_match' => true,
            'model_match' => true,
            'color_match' => true,
            'product_type_match' => true,
            'image_quality_ok' => true,
            'rejection_reason' => '',
            'notes' => 'AI agrees with deterministic metadata.',
        ]])->preventStrayPrompts();

        config()->set('product-image-discovery.ai.enabled', true);
        config()->set('product-image-discovery.ai.provider', 'anthropic');
        config()->set('product-image-discovery.ai.providers.anthropic.api_key', 'fake-key');
        config()->set('product-image-discovery.ai.fail_silently', false);

        $store = new InMemoryPipelineStore();
        $logger = new ProductImageEventLogger($store);
        $request = (new IngestProductImageDiscoveryJob([
            'client_id' => 1,
            'erp_model_id' => 'NIKE-AF1-07',
            'erp_model_color_id' => 'NIKE-AF1-07-CW2288-111',
            'brand' => 'Nike',
            'model_code' => 'Air Force 1 07',
            'color_name' => 'White',
        ]))->handle($store, $logger);
        $candidate = $store->upsertCandidate($request['id'], sha1('candidate'), [
            'source_page_url' => 'https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr',
            'image_url' => 'https://static.nike.com/a/images/t_prod/w_1200/air-force-1-07.jpg',
            'source_domain' => 'nike.com',
            'title' => "Nike Air Force 1 '07 Men's Shoes White",
            'width' => 1200,
            'height' => 1200,
            'quality_score' => 90,
        ]);

        $updated = (new VerifyCandidateImageJob($request['id'], $candidate['id']))->handle(
            $store,
            $logger,
            aiVerifier: new ProductImageAiVerifier(),
        );

        self::assertSame(87, $updated['ai_analysis']['verification']['confidence']);
        self::assertTrue($updated['ai_analysis']['verification']['variant_safe']);
        self::assertSame(87, $updated['ai_analysis']['match_score']);
        self::assertGreaterThan(0, $updated['visual_match_score']);
    }
}
