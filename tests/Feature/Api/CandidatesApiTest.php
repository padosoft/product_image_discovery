<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryCandidate;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryEvent;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryRequest;

final class CandidatesApiTest extends ApiTestCase
{
    public function test_candidates_index_requires_read_ability(): void
    {
        $record = FakeProductImageDiscoveryRequest::query()->create([
            'client_id' => 10,
            'erp_model_id' => 'MODEL-001',
            'erp_model_color_id' => 'MODEL-001-BLK',
            'status' => 'manual_review',
            'raw_payload' => ['foo' => 'bar'],
        ]);

        $this->authenticate(['write']);

        $this->getJson('/api/product-image-discovery/requests/' . $record->getKey() . '/candidates')
            ->assertForbidden();
    }

    public function test_reject_requires_reason(): void
    {
        [$record, $candidate] = $this->seedRequestWithCandidate();
        $this->authenticate(['review']);

        $this->postJson('/api/product-image-discovery/requests/' . $record->getKey() . '/candidates/' . $candidate->getKey() . '/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_review_endpoints_require_review_or_write_ability(): void
    {
        [$record, $candidate] = $this->seedRequestWithCandidate();
        $this->authenticate(['read']);

        $this->postJson('/api/product-image-discovery/requests/' . $record->getKey() . '/candidates/' . $candidate->getKey() . '/approve')
            ->assertForbidden();
    }

    public function test_approve_reject_and_retry_follow_the_happy_path(): void
    {
        [$record, $candidate] = $this->seedRequestWithCandidate();
        $this->authenticate(['review']);

        $this->postJson('/api/product-image-discovery/requests/' . $record->getKey() . '/candidates/' . $candidate->getKey() . '/approve')
            ->assertOk()
            ->assertJsonPath('request.status', 'ready_to_publish')
            ->assertJsonPath('request.selected_candidate.id', $candidate->getKey())
            ->assertJsonPath('candidate.status', 'selected');

        $record->refresh();
        $candidate->refresh();

        $this->assertSame('ready_to_publish', $record->status);
        $this->assertSame('selected', $candidate->status);
        $this->assertNotNull($record->selected_candidate_id);

        $this->postJson('/api/product-image-discovery/requests/' . $record->getKey() . '/candidates/' . $candidate->getKey() . '/reject', [
            'reason' => 'wrong_color',
        ])->assertOk()
            ->assertJsonPath('request.status', 'rejected')
            ->assertJsonPath('candidate.rejection_reason', 'wrong_color');

        $record->refresh();
        $candidate->refresh();

        $this->assertSame('rejected', $record->status);
        $this->assertSame('rejected', $candidate->status);

        $this->postJson('/api/product-image-discovery/requests/' . $record->getKey() . '/retry')
            ->assertOk()
            ->assertJsonPath('request.status', 'queued');

        $record->refresh();
        $this->assertSame('queued', $record->status);
        $this->assertSame(1, $record->attempts);

        $this->assertSame(3, FakeProductImageDiscoveryEvent::query()->count());
    }

    private function seedRequestWithCandidate(): array
    {
        $record = FakeProductImageDiscoveryRequest::query()->create([
            'client_id' => 10,
            'erp_model_id' => 'MODEL-001',
            'erp_model_color_id' => 'MODEL-001-BLK',
            'status' => 'manual_review',
            'raw_payload' => ['foo' => 'bar'],
        ]);

        $candidate = FakeProductImageDiscoveryCandidate::query()->create([
            'request_id' => $record->getKey(),
            'client_id' => 10,
            'source_domain' => 'supplier.example',
            'source_page_url' => 'https://supplier.example/p/model-001',
            'image_url' => 'https://supplier.example/images/model-001.jpg',
            'status' => 'candidate',
            'final_score' => 88,
        ]);

        return [$record, $candidate];
    }
}
