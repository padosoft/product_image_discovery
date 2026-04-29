<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\ProductImageDiscovery\Actions\ResolveDecisionAction;
use Padosoft\ProductImageDiscovery\DTO\CandidateScoreData;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\ResolvesQueueName;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Quality\ImageQualityAnalyzer;

final class AssessImageQualityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesQueueName;
    use SerializesModels;

    public function __construct(
        private readonly int|string $requestId,
        private readonly int|string $candidateId,
    ) {
        $this->onQueue($this->queueNameFor('quality'));
    }

    public function handle(
        PipelineStoreInterface $store,
        ProductImageEventLogger $logger,
        ?ImageQualityAnalyzer $qualityAnalyzer = null,
        ?ResolveDecisionAction $decisionResolver = null,
    ): array
    {
        $candidate = $store->getCandidate($this->candidateId);
        $request = $store->getRequest($this->requestId);

        if ($candidate === null || $request === null) {
            return [];
        }

        if (($candidate['quality_checked_at'] ?? null) !== null) {
            return $candidate;
        }

        $store->updateRequest($this->requestId, [
            'status' => ProductImageDiscoveryRequestStatus::QualityChecking->value,
        ]);

        $analysis = ($qualityAnalyzer ?? new ImageQualityAnalyzer())->analyze([
            'path' => $candidate['local_original_path'] ?? null,
            'url' => $candidate['image_url'] ?? null,
            'width' => $candidate['width'] ?? null,
            'height' => $candidate['height'] ?? null,
            'mime_type' => $candidate['mime_type'] ?? null,
            'file_size' => $candidate['file_size'] ?? null,
        ]);
        $status = ($analysis['passed'] ?? false) === true
            ? ProductImageDiscoveryCandidateStatus::QualityPassed
            : ProductImageDiscoveryCandidateStatus::QualityFailed;

        $updated = $store->updateCandidate($this->candidateId, [
            'status' => $status->value,
            'quality_score' => (int) ($analysis['quality_score'] ?? 0),
            'quality_analysis' => $analysis,
            'quality_checked_at' => gmdate('c'),
            'width' => $analysis['width'] ?? ($candidate['width'] ?? null),
            'height' => $analysis['height'] ?? ($candidate['height'] ?? null),
            'mime_type' => $analysis['mime_type'] ?? ($candidate['mime_type'] ?? null),
            'file_size' => $analysis['file_size'] ?? ($candidate['file_size'] ?? null),
        ]);

        $candidateScores = array_map(static function (array $item): array {
            return CandidateScoreData::fromArray([
                'source_trust_score' => $item['source_trust_score'] ?? 0,
                'textual_match_score' => $item['textual_match_score'] ?? 0,
                'structured_match_score' => $item['structured_match_score'] ?? 0,
                'visual_match_score' => $item['visual_match_score'] ?? 0,
                'quality_score' => $item['quality_score'] ?? 0,
                'risk_penalty' => $item['risk_penalty'] ?? 0,
                'final_score' => $item['final_score'] ?? 0,
                'evidence' => $item['evidence'] ?? [],
                'issues' => $item['quality_analysis']['issues'] ?? [],
                'has_strong_match' => (bool) (($item['ai_analysis']['has_strong_match'] ?? false) || ! empty($item['evidence']['strong_matches'] ?? [])),
                'source_trusted' => ($item['source_trust_score'] ?? 0) > 0,
                'allow_auto_publish' => (bool) (($item['evidence']['source']['allow_auto_publish'] ?? false)),
                'allow_download' => (bool) (($item['evidence']['source']['allow_download'] ?? true)),
                'brand_matched' => (bool) (($item['evidence']['matches'] ?? null) && in_array('brand', $item['evidence']['matches'], true)),
                'brand_mismatch' => (bool) (($item['evidence']['mismatches'] ?? null) && in_array('structured_brand_mismatch', $item['evidence']['mismatches'], true)),
                'color_matched' => (bool) (($item['evidence']['matches'] ?? null) && in_array('color_name', $item['evidence']['matches'], true)),
                'color_mismatch' => (bool) (($item['evidence']['mismatches'] ?? null) && in_array('color_name_mismatch', $item['evidence']['mismatches'], true)),
                'model_matched' => (bool) (($item['evidence']['matches'] ?? null) && in_array('model_code', $item['evidence']['matches'], true)),
                'model_mismatch' => (bool) (($item['evidence']['mismatches'] ?? null) && in_array('model_code_similar_mismatch', $item['evidence']['mismatches'], true)),
                'quality_passed' => ($item['quality_analysis']['passed'] ?? false) === true,
                'robots_allowed' => $item['robots_allowed'] ?? null,
                'rejection_reason' => $item['rejection_reason'] ?? null,
                'status' => $item['status'] ?? ProductImageDiscoveryCandidateStatus::Candidate->value,
            ])->toArray();
        }, $store->listCandidates($this->requestId));

        $decision = ($decisionResolver ?? new ResolveDecisionAction())->handle($candidateScores);
        $bestCandidateScore = is_array($decision['best_candidate_score'] ?? null) ? $decision['best_candidate_score'] : null;
        $bestCandidateId = $this->findCandidateIdForScore($store->listCandidates($this->requestId), $bestCandidateScore);

        $requestStatus = $this->mapRequestStatus((string) ($decision['status'] ?? ProductImageDiscoveryRequestStatus::ManualReview->value));

        $store->updateRequest($this->requestId, [
            'status' => $requestStatus,
            'best_candidate_id' => $bestCandidateId,
            'selected_candidate_id' => $bestCandidateId,
            'final_score' => $bestCandidateScore['final_score'] ?? null,
            'rejection_reason' => $requestStatus === ProductImageDiscoveryRequestStatus::Rejected->value
                ? $this->mapRejectionReason($decision['reason'] ?? null)
                : null,
            'verified_at' => gmdate('c'),
        ]);

        $logger->record('pipeline.quality.completed', [
            'quality_score' => $analysis['quality_score'] ?? 0,
            'quality_status' => $status->value,
            'decision' => $decision,
        ], requestId: $this->requestId, candidateId: $this->candidateId);

        return $updated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<string, mixed>|null  $bestCandidateScore
     */
    private function findCandidateIdForScore(array $candidates, ?array $bestCandidateScore): int|string|null
    {
        if ($bestCandidateScore === null) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if (($candidate['final_score'] ?? null) !== ($bestCandidateScore['final_score'] ?? null)) {
                continue;
            }

            if (($candidate['source_trust_score'] ?? null) !== ($bestCandidateScore['source_trust_score'] ?? null)) {
                continue;
            }

            return $candidate['id'] ?? null;
        }

        return null;
    }

    private function mapRequestStatus(string $status): string
    {
        return match ($status) {
            ProductImageDiscoveryRequestStatus::ReadyToPublish->value => ProductImageDiscoveryRequestStatus::ReadyToPublish->value,
            ProductImageDiscoveryRequestStatus::Rejected->value => ProductImageDiscoveryRequestStatus::Rejected->value,
            default => ProductImageDiscoveryRequestStatus::ManualReview->value,
        };
    }

    private function mapRejectionReason(mixed $reason): ?string
    {
        if (! is_string($reason) || trim($reason) === '') {
            return null;
        }

        $reason = trim($reason);
        $upperReason = strtoupper($reason);

        foreach (ProductImageDiscoveryRejectionReason::cases() as $case) {
            if ($case->value === $upperReason) {
                return $case->value;
            }
        }

        return match ($reason) {
            'source_not_auto_publishable' => ProductImageDiscoveryRejectionReason::SourceNotAllowed->value,
            'quality_not_passed' => ProductImageDiscoveryRejectionReason::LowResolution->value,
            'robots_or_permission_blocked' => ProductImageDiscoveryRejectionReason::RobotsOrPermissionBlocked->value,
            default => ProductImageDiscoveryRejectionReason::LowConfidence->value,
        };
    }
}
