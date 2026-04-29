<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Actions;

use Padosoft\ProductImageDiscovery\DTO\CandidateScoreData;

final class ResolveDecisionAction
{
    /**
     * @param list<CandidateScoreData|array<string, mixed>> $candidateScores
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function handle(array $candidateScores, array $settings = []): array
    {
        $scores = array_map(
            static fn (CandidateScoreData|array $score): CandidateScoreData => is_array($score) ? CandidateScoreData::fromArray($score) : $score,
            $candidateScores,
        );

        $scores = array_values(array_filter($scores, static function (CandidateScoreData $score): bool {
            return ! in_array($score->status, ['rejected', 'low_score_rejected', 'quality_failed'], true);
        }));

        if ($scores === []) {
            return [
                'status' => 'rejected',
                'reason' => 'no_candidates_found',
                'best_candidate_score' => null,
                'evidence' => ['candidate_count' => 0],
            ];
        }

        usort($scores, static fn (CandidateScoreData $a, CandidateScoreData $b): int => $b->finalScore <=> $a->finalScore);

        $best = $scores[0];
        $autoPublishThreshold = (int) ($settings['auto_publish_threshold'] ?? 85);
        $manualReviewThreshold = (int) ($settings['manual_review_threshold'] ?? 55);
        $minQualityScore = (int) ($settings['min_quality_score'] ?? 70);

        if ($best->canAutoPublish($autoPublishThreshold, $minQualityScore)) {
            return [
                'status' => 'ready_to_publish',
                'reason' => null,
                'best_candidate_score' => $best->toArray(),
                'evidence' => ['decision' => 'strong_trusted_match'],
            ];
        }

        $reason = $this->manualReason($best, $autoPublishThreshold, $minQualityScore);

        if ($best->finalScore >= $manualReviewThreshold || $best->hasStrongMatch) {
            return [
                'status' => 'manual_review',
                'reason' => $reason,
                'best_candidate_score' => $best->toArray(),
                'evidence' => ['decision' => 'conservative_review', 'blocked_by' => $reason],
            ];
        }

        return [
            'status' => 'rejected',
            'reason' => $best->rejectionReason ?? 'LOW_CONFIDENCE',
            'best_candidate_score' => $best->toArray(),
            'evidence' => ['decision' => 'below_review_threshold'],
        ];
    }

    private function manualReason(CandidateScoreData $score, int $autoPublishThreshold, int $minQualityScore): string
    {
        if (! $score->sourceTrusted || ! $score->allowAutoPublish) {
            return 'source_not_auto_publishable';
        }

        if (! $score->hasStrongMatch) {
            return 'missing_strong_identifier_match';
        }

        if ($score->brandMismatch) {
            return 'brand_mismatch';
        }

        if ($score->colorMismatch) {
            return 'color_mismatch';
        }

        if ($score->modelMismatch) {
            return 'model_mismatch';
        }

        if (! $score->qualityPassed || $score->qualityScore < $minQualityScore) {
            return 'quality_not_passed';
        }

        if ($score->robotsAllowed === false) {
            return 'robots_or_permission_blocked';
        }

        if ($score->finalScore < $autoPublishThreshold) {
            return 'below_auto_publish_threshold';
        }

        return 'manual_review_required';
    }
}
