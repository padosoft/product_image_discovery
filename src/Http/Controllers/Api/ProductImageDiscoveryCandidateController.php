<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Padosoft\ProductImageDiscovery\Http\Concerns\ResolvesProductImageDiscovery;
use Padosoft\ProductImageDiscovery\Http\Requests\RejectProductImageDiscoveryCandidateRequest;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageDiscoveryCandidateResource;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageDiscoveryRequestResource;

final class ProductImageDiscoveryCandidateController extends Controller
{
    use ResolvesProductImageDiscovery;

    public function index(int|string $request)
    {
        $record = $this->newQuery('request')->findOrFail($request);
        $candidates = method_exists($record, 'candidates')
            ? $record->candidates()->orderByDesc('final_score')->paginate(25)
            : $this->newQuery('candidate')->where('request_id', $record->getKey())->orderByDesc('final_score')->paginate(25);

        return ProductImageDiscoveryCandidateResource::collection($candidates);
    }

    public function approve(Request $httpRequest, int|string $request, int|string $candidate): JsonResponse
    {
        [$record, $candidateRecord] = $this->resolveRequestAndCandidate($request, $candidate);

        $candidateRecord->fill([
            'status' => 'selected',
            'rejection_reason' => null,
        ]);
        $candidateRecord->save();

        $record->fill([
            'selected_candidate_id' => $candidateRecord->getKey(),
            'best_candidate_id' => $record->getAttribute('best_candidate_id') ?? $candidateRecord->getKey(),
            'final_score' => $candidateRecord->getAttribute('final_score'),
            'rejection_reason' => null,
            'status' => 'ready_to_publish',
            'verified_at' => now(),
        ]);
        $record->save();

        $this->recordAuditEvent($record, 'candidate_approved', [
            'approved_by' => $httpRequest->user()?->getAuthIdentifier(),
        ], $candidateRecord);

        $this->loadAvailableRelations($record, ['bestCandidate', 'selectedCandidate']);

        return response()->json([
            'ok' => true,
            'request' => (new ProductImageDiscoveryRequestResource($record))->resolve(),
            'candidate' => (new ProductImageDiscoveryCandidateResource($candidateRecord))->resolve(),
        ]);
    }

    public function reject(RejectProductImageDiscoveryCandidateRequest $httpRequest, int|string $request, int|string $candidate): JsonResponse
    {
        [$record, $candidateRecord] = $this->resolveRequestAndCandidate($request, $candidate);
        $payload = $httpRequest->validated();

        $candidateRecord->fill([
            'status' => 'rejected',
            'rejection_reason' => $payload['reason'],
        ]);
        $candidateRecord->save();

        $otherCandidatesQuery = $this->newQuery('candidate')
            ->where('request_id', $record->getKey())
            ->whereKeyNot($candidateRecord->getKey())
            ->where('status', '!=', 'rejected');

        $hasRemainingCandidates = $otherCandidatesQuery->exists();
        $selectedCandidateId = $record->getAttribute('selected_candidate_id');

        $record->fill([
            'status' => $hasRemainingCandidates ? 'manual_review' : 'rejected',
            'rejection_reason' => $hasRemainingCandidates ? null : $payload['reason'],
            'selected_candidate_id' => $selectedCandidateId !== null && (string) $selectedCandidateId === (string) $candidateRecord->getKey()
                ? null
                : $selectedCandidateId,
        ]);
        $record->save();

        $this->recordAuditEvent($record, 'candidate_rejected', [
            'rejected_by' => $httpRequest->user()?->getAuthIdentifier(),
            'reason' => $payload['reason'],
            'notes' => $payload['notes'] ?? null,
        ], $candidateRecord);

        $this->loadAvailableRelations($record, ['bestCandidate', 'selectedCandidate']);

        return response()->json([
            'ok' => true,
            'request' => (new ProductImageDiscoveryRequestResource($record))->resolve(),
            'candidate' => (new ProductImageDiscoveryCandidateResource($candidateRecord))->resolve(),
        ]);
    }

    /**
     * @return array{0: Model, 1: Model}
     */
    private function resolveRequestAndCandidate(int|string $requestId, int|string $candidateId): array
    {
        /** @var Model $record */
        $record = $this->newQuery('request')->findOrFail($requestId);
        /** @var Model $candidate */
        $candidate = $this->newQuery('candidate')
            ->where('request_id', $record->getKey())
            ->findOrFail($candidateId);

        return [$record, $candidate];
    }
}
