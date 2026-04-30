<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Bus;
use Tests\Feature\Api\Fixtures\FakeIngestJob;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryCandidate;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryRequest;

final class RequestsApiTest extends ApiTestCase
{
    public function test_ingest_requires_authentication(): void
    {
        $this->postJson('/api/product-image-discovery/requests', $this->requestPayload())
            ->assertUnauthorized();
    }

    public function test_ingest_requires_write_ability(): void
    {
        $this->authenticate(['read']);

        $this->postJson('/api/product-image-discovery/requests', $this->requestPayload())
            ->assertForbidden();
    }

    public function test_ingest_validates_required_fields(): void
    {
        $this->authenticate(['write']);

        $this->postJson('/api/product-image-discovery/requests', [
            'client_id' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['erp_model_id', 'erp_model_color_id']);
    }

    public function test_ingest_upserts_and_dispatches_the_configured_job(): void
    {
        Bus::fake();
        $this->authenticate(['write']);

        $response = $this->postJson('/api/product-image-discovery/requests', $this->requestPayload());

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'queued');

        $record = FakeProductImageDiscoveryRequest::query()->firstOrFail();
        $this->assertSame('queued', $record->status);
        $this->assertSame('Pado', $record->brand);

        Bus::assertDispatched(FakeIngestJob::class, function (FakeIngestJob $job) use ($record): bool {
            return (string) $job->requestId === (string) $record->getKey();
        });

        $this->postJson('/api/product-image-discovery/requests', $this->requestPayload([
            'brand' => 'Updated Brand',
        ]))->assertOk();

        $this->assertSame(1, FakeProductImageDiscoveryRequest::query()->count());
        $this->assertSame('Updated Brand', FakeProductImageDiscoveryRequest::query()->firstOrFail()->brand);
    }

    public function test_ingest_accepts_barcode_alias_and_persists_it_as_ean(): void
    {
        Bus::fake();
        $this->authenticate(['write']);

        $this->postJson('/api/product-image-discovery/requests', $this->requestPayload([
            'ean' => null,
            'barcode' => '80 50000 000012',
        ]))->assertCreated();

        $record = FakeProductImageDiscoveryRequest::query()->firstOrFail();

        $this->assertSame('8050000000012', $record->ean);
        $this->assertSame('80 50000 000012', $record->raw_payload['barcode'] ?? null);
    }

    public function test_search_and_show_return_expected_data_with_read_ability(): void
    {
        $this->authenticate(['read']);

        $record = FakeProductImageDiscoveryRequest::query()->create([
            'client_id' => 10,
            'erp_model_id' => 'MODEL-001',
            'erp_model_color_id' => 'MODEL-001-BLK',
            'status' => 'manual_review',
            'brand' => 'Pado',
            'supplier' => 'Supplier X',
            'raw_payload' => ['foo' => 'bar'],
            'selected_candidate_id' => null,
        ]);

        $candidate = FakeProductImageDiscoveryCandidate::query()->create([
            'request_id' => $record->getKey(),
            'client_id' => 10,
            'source_domain' => 'supplier.example',
            'source_page_url' => 'https://supplier.example/p/model-001',
            'image_url' => 'https://supplier.example/images/model-001.jpg',
            'status' => 'candidate',
            'final_score' => 78,
        ]);

        $record->forceFill(['best_candidate_id' => $candidate->getKey()])->save();

        $this->getJson('/api/product-image-discovery/requests/search?client_id=10&manual_review_required=1&source_domain=supplier.example')
            ->assertOk()
            ->assertJsonPath('data.0.id', $record->getKey())
            ->assertJsonPath('data.0.status', 'manual_review');

        $record->forceFill(['ean' => '8050000000012'])->save();

        $this->getJson('/api/product-image-discovery/requests/search?barcode=8050000000012')
            ->assertOk()
            ->assertJsonPath('data.0.id', $record->getKey());

        $this->getJson('/api/product-image-discovery/requests/' . $record->getKey())
            ->assertOk()
            ->assertJsonPath('data.id', $record->getKey())
            ->assertJsonPath('data.best_candidate.id', $candidate->getKey());
    }

    public function test_show_requires_read_ability(): void
    {
        $record = FakeProductImageDiscoveryRequest::query()->create([
            'client_id' => 10,
            'erp_model_id' => 'MODEL-001',
            'erp_model_color_id' => 'MODEL-001-BLK',
            'status' => 'queued',
            'raw_payload' => ['foo' => 'bar'],
        ]);

        $this->authenticate(['write']);

        $this->getJson('/api/product-image-discovery/requests/' . $record->getKey())
            ->assertForbidden();
    }
}
