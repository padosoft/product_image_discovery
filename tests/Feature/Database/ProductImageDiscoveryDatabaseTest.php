<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Padosoft\ProductImageDiscovery\Database\Seeders\ProductImageDiscoveryDefaultsSeeder;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\VerifyCandidateImageJob;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryCandidate;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryEvent;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoverySetting;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Models\ProductImageTrustedSource;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Storage\EloquentPipelineStore;

class ProductImageDiscoveryDatabaseTest extends DatabaseTestCase
{
    public function test_request_unique_identity_is_enforced(): void
    {
        ProductImageDiscoveryRequest::query()->create($this->requestPayload());

        $this->expectException(QueryException::class);

        ProductImageDiscoveryRequest::query()->create($this->requestPayload());
    }

    public function test_trusted_source_is_unique_per_client_but_not_across_clients(): void
    {
        ProductImageTrustedSource::query()->create([
            'client_id' => 100,
            'domain' => 'supplier.example',
            'trust_score' => 80,
            'allow_search' => true,
            'allow_scraping' => true,
            'allow_download' => true,
            'allow_auto_publish' => false,
            'allow_description_import' => false,
            'requires_manual_review' => true,
            'is_active' => true,
        ]);

        ProductImageTrustedSource::query()->create([
            'client_id' => 200,
            'domain' => 'supplier.example',
            'trust_score' => 70,
            'allow_search' => true,
            'allow_scraping' => true,
            'allow_download' => true,
            'allow_auto_publish' => false,
            'allow_description_import' => false,
            'requires_manual_review' => true,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        ProductImageTrustedSource::query()->create([
            'client_id' => 100,
            'domain' => 'supplier.example',
            'trust_score' => 60,
            'allow_search' => true,
            'allow_scraping' => true,
            'allow_download' => true,
            'allow_auto_publish' => false,
            'allow_description_import' => false,
            'requires_manual_review' => true,
            'is_active' => true,
        ]);
    }

    public function test_deleting_a_request_cascades_candidates_and_events(): void
    {
        $request = ProductImageDiscoveryRequest::query()->create($this->requestPayload());

        $candidate = ProductImageDiscoveryCandidate::query()->create([
            'request_id' => $request->id,
            'client_id' => $request->client_id,
            'source_domain' => 'supplier.example',
            'source_page_url' => 'https://supplier.example/product/sku-001',
            'image_url' => 'https://supplier.example/product/sku-001/image.jpg',
            'status' => ProductImageDiscoveryCandidateStatus::Candidate,
            'evidence' => ['query' => 'brand model'],
            'structured_data' => ['sku' => 'SKU-001'],
            'ai_analysis' => ['match' => 0.82],
            'quality_analysis' => ['watermark' => false],
        ]);

        ProductImageDiscoveryEvent::query()->create([
            'request_id' => $request->id,
            'candidate_id' => $candidate->id,
            'event_type' => 'candidate.created',
            'level' => 'info',
            'message' => 'Candidate persisted.',
            'context' => ['candidate_id' => $candidate->id],
        ]);

        $request->delete();

        $this->assertDatabaseCount('product_image_discovery_candidates', 0);
        $this->assertDatabaseCount('product_image_discovery_events', 0);
    }

    public function test_model_casts_and_encrypted_provider_credentials_work(): void
    {
        $request = ProductImageDiscoveryRequest::query()->create([
            ...$this->requestPayload(),
            'status' => ProductImageDiscoveryRequestStatus::Searching,
            'rejection_reason' => ProductImageDiscoveryRejectionReason::LowConfidence,
            'raw_payload' => ['nested' => ['sku' => 'SKU-001']],
        ])->fresh();

        self::assertInstanceOf(ProductImageDiscoveryRequestStatus::class, $request->status);
        self::assertSame(ProductImageDiscoveryRequestStatus::Searching, $request->status);
        self::assertSame(['nested' => ['sku' => 'SKU-001']], $request->raw_payload);
        self::assertSame(ProductImageDiscoveryRejectionReason::LowConfidence, $request->rejection_reason);

        $provider = ProductImageSearchProvider::query()->create([
            'code' => 'test-provider',
            'name' => 'Test Provider',
            'driver' => 'test',
            'base_url' => 'https://example.test',
            'api_key_encrypted' => 'plain-api-key',
            'api_secret_encrypted' => 'plain-secret',
            'config' => ['supports_image_search' => true],
            'priority' => 5,
            'timeout_seconds' => 12,
            'rate_limit_per_minute' => 15,
            'is_active' => true,
        ])->fresh();

        self::assertSame('plain-api-key', $provider->api_key_encrypted);
        self::assertSame('plain-secret', $provider->api_secret_encrypted);
        self::assertNotSame(
            'plain-api-key',
            DB::table('product_image_search_providers')->where('id', $provider->id)->value('api_key_encrypted'),
        );
    }

    public function test_settings_resolve_with_client_override_and_seeder_is_idempotent(): void
    {
        $this->seed(ProductImageDiscoveryDefaultsSeeder::class);
        $countAfterFirstSeed = ProductImageDiscoverySetting::query()->whereNull('client_id')->count();
        $this->seed(ProductImageDiscoveryDefaultsSeeder::class);

        self::assertSame($countAfterFirstSeed, ProductImageDiscoverySetting::query()->whereNull('client_id')->count());

        ProductImageDiscoverySetting::query()->create([
            'client_id' => 400,
            'setting_key' => 'quality.min_width',
            'setting_value' => 1200,
            'value_type' => 'integer',
            'is_active' => true,
        ]);

        self::assertSame(1200, ProductImageDiscoverySetting::resolveValue('quality.min_width', 400));
        self::assertSame(800, ProductImageDiscoverySetting::resolveValue('quality.min_width', 999));
    }

    /**
     * @return array<string, mixed>
     */
    public function test_verify_closes_an_all_rejected_run_through_the_eloquent_store(): void
    {
        $store = new EloquentPipelineStore();
        $logger = new ProductImageEventLogger();
        $request = $store->upsertRequest(
            ['client_id' => 100, 'erp_model_color_id' => 'MODEL-001-BLACK'],
            array_merge($this->requestPayload(), [
                'ean' => null,
                'status' => ProductImageDiscoveryRequestStatus::CandidatesFound->value,
                'raw_payload' => ['context' => ['search' => ['run' => 2]]],
            ]),
        );
        // A promoted leftover of run 1 must neither keep run 2 open nor show up in its listing.
        $store->upsertCandidate($request['id'], 'old', [
            'search_run' => 1,
            'source_page_url' => 'https://shop.example/p/old',
            'image_url' => 'https://shop.example/p/old.jpg',
            'status' => ProductImageDiscoveryCandidateStatus::VerifiedMatch->value,
        ]);
        $wrongColor = $store->upsertCandidate($request['id'], 'blue', [
            'search_run' => 2,
            'source_page_url' => 'https://shop.example/p/blue',
            'image_url' => 'https://shop.example/p/blue.jpg',
            'status' => ProductImageDiscoveryCandidateStatus::Candidate->value,
            'evidence' => ['search_result' => ['title' => 'Acme MODEL-001 blue']],
        ]);

        (new VerifyCandidateImageJob($request['id'], $wrongColor['id']))->handle($store, $logger);

        $closed = ProductImageDiscoveryRequest::query()->findOrFail($request['id']);

        $this->assertSame(ProductImageDiscoveryRequestStatus::NoCandidatesFound, $closed->status);
        $this->assertSame(ProductImageDiscoveryRejectionReason::WrongColor, $closed->rejection_reason);
        $this->assertSame(2, ProductImageDiscoveryCandidate::query()->findOrFail($wrongColor['id'])->search_run);
        $this->assertSame(
            ProductImageDiscoveryCandidateStatus::WrongColor,
            ProductImageDiscoveryCandidate::query()->findOrFail($wrongColor['id'])->status,
        );
    }

    protected function requestPayload(): array
    {
        return [
            'client_id' => 100,
            'erp_model_id' => 'MODEL-001',
            'erp_model_color_id' => 'MODEL-001-BLACK',
            'erp_model_color_size_id' => 'MODEL-001-BLACK-42',
            'identity_hash' => str_repeat('a', 64),
            'brand' => 'Acme',
            'supplier' => 'Supplier',
            'sku' => 'SKU-001',
            'supplier_sku' => 'SUP-001',
            'model_code' => 'MODEL-001',
            'color_code' => 'BLACK',
            'color_name' => 'Black',
            'ean' => '1234567890123',
            'season' => 'FW26',
            'category' => 'Shoes',
            'material' => 'Leather',
            'price' => 199.99,
            'currency' => 'EUR',
            'raw_payload' => ['supplier' => 'Supplier', 'sku' => 'SKU-001'],
            'status' => ProductImageDiscoveryRequestStatus::Pending,
            'attempts' => 0,
        ];
    }
}
