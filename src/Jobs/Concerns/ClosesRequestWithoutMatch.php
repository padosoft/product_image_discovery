<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs\Concerns;

use BackedEnum;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;

trait ClosesRequestWithoutMatch
{
    use ScopesCandidatesToSearchRun;

    /**
     * Once every candidate of the current run is rejected, close the request as
     * no_candidates_found. The decision step only runs for promoted candidates, so without this
     * the request stayed in manual_review with nothing to review. While a candidate is still to
     * verify, or one was promoted (the download/quality chain then owns the status), nothing changes.
     */
    private function closeRequestWhenNothingWasPromoted(PipelineStoreInterface $store, ProductImageEventLogger $logger): void
    {
        $request = $store->getRequest($this->requestId);

        if ($request === null) {
            return;
        }

        $candidates = $this->candidatesOfCurrentRun($store, $request);
        $rejectedStatuses = [
            ProductImageDiscoveryCandidateStatus::LowScoreRejected->value,
            ProductImageDiscoveryCandidateStatus::WrongProduct->value,
            ProductImageDiscoveryCandidateStatus::WrongColor->value,
            ProductImageDiscoveryCandidateStatus::Rejected->value,
        ];

        foreach ($candidates as $candidate) {
            if (! in_array($this->enumValue($candidate['status'] ?? null), $rejectedStatuses, true)) {
                return;
            }
        }

        $reasonCounts = $this->rejectionReasonCounts($candidates);
        $reason = array_key_first($reasonCounts) ?? ProductImageDiscoveryRejectionReason::LowConfidence->value;

        // selected_candidate_id is left alone: it may hold a reviewer's choice.
        $store->updateRequest($this->requestId, [
            'status' => ProductImageDiscoveryRequestStatus::NoCandidatesFound->value,
            'rejection_reason' => $reason,
            'best_candidate_id' => null,
            'final_score' => null,
        ]);

        $logger->record('pipeline.verify.no_match', [
            'candidate_count' => count($candidates),
            'rejection_reason' => $reason,
            'rejection_reasons' => $reasonCounts,
        ], requestId: $this->requestId);
    }

    /**
     * Rejection reasons by frequency, most frequent first; ties keep the order of the candidates,
     * which the store lists best score first. SOURCE_NOT_ALLOWED only means "not a trusted source"
     * (a score penalty, not a block), so it is reported as LOW_CONFIDENCE.
     *
     * @param list<array<string, mixed>> $candidates
     * @return array<string, int>
     */
    private function rejectionReasonCounts(array $candidates): array
    {
        $counts = [];

        foreach ($candidates as $candidate) {
            $reason = ProductImageDiscoveryRejectionReason::tryFrom((string) $this->enumValue($candidate['rejection_reason'] ?? null));

            if ($reason === null || $reason === ProductImageDiscoveryRejectionReason::SourceNotAllowed) {
                $reason = ProductImageDiscoveryRejectionReason::LowConfidence;
            }

            $counts[$reason->value] = ($counts[$reason->value] ?? 0) + 1;
        }

        $order = array_flip(array_keys($counts));
        uksort($counts, static fn (string $a, string $b): int => $counts[$b] <=> $counts[$a] ?: $order[$a] <=> $order[$b]);

        return $counts;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
