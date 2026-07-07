<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\ProductImageDiscovery\Actions\ScoreCandidateImageAction;
use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\DispatchesPipelineJobs;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\ResolvesQueueName;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Ai\ProductImageAiVerifier;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Support\TrustedSourceMatcher;

final class VerifyCandidateImageJob implements ShouldQueue
{
    use Dispatchable;
    use DispatchesPipelineJobs;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesQueueName;
    use SerializesModels;

    public function __construct(
        private readonly int|string $requestId,
        private readonly int|string $candidateId,
    ) {
        $this->onQueue($this->queueNameFor('verify'));
    }

    public function handle(
        PipelineStoreInterface $store,
        ProductImageEventLogger $logger,
        ?ScoreCandidateImageAction $scorer = null,
        ?ProductImageAiVerifier $aiVerifier = null,
    ): array
    {
        $request = $store->getRequest($this->requestId);
        $candidate = $store->getCandidate($this->candidateId);

        if ($request === null || $candidate === null) {
            return [];
        }

        if (in_array($candidate['status'] ?? null, [
            ProductImageDiscoveryCandidateStatus::VerifiedMatch->value,
            ProductImageDiscoveryCandidateStatus::WrongProduct->value,
            ProductImageDiscoveryCandidateStatus::WrongColor->value,
            ProductImageDiscoveryCandidateStatus::Rejected->value,
        ], true)) {
            return $candidate;
        }

        $store->updateRequest($this->requestId, [
            'status' => ProductImageDiscoveryRequestStatus::Verifying->value,
        ]);

        $aiAnalysis = $candidate['ai_analysis'] ?? [];

        if (! is_array($aiAnalysis)) {
            $aiAnalysis = [];
        }

        $aiVerification = null;
        $aiVerifier ??= new ProductImageAiVerifier();

        if ($aiVerifier->isEnabled()) {
            $aiVerification = $aiVerifier->verify(
                ProductIdentityData::fromArray(array_merge($request, [
                    'raw_payload' => $request['raw_payload'] ?? $request,
                ])),
                CandidateImageData::fromArray($candidate),
            );
            $aiAnalysis['verification'] = $aiVerification->toArray();

            if ($aiVerification->available) {
                $aiAnalysis['match_score'] = $aiVerification->confidence;
                $aiAnalysis['variant_safe'] = $aiVerification->variantSafe;
            }
        }

        $candidateForScoring = array_merge($candidate, [
            'ai_analysis' => $aiAnalysis,
        ]);

        $trustedSource = TrustedSourceMatcher::match(
            $store->listTrustedSources($request['client_id'] ?? null),
            $candidate['source_domain'] ?? $candidate['source_page_url'] ?? null,
        );

        $score = ($scorer ?? new ScoreCandidateImageAction())->handle(
            ProductIdentityData::fromArray(array_merge($request, [
                'raw_payload' => $request['raw_payload'] ?? $request,
            ])),
            CandidateImageData::fromArray($candidateForScoring),
            $trustedSource,
            $store->getSettings($request['client_id'] ?? null),
        );

        $candidateStatus = match ($score->status) {
            'candidate' => ProductImageDiscoveryCandidateStatus::VerifiedMatch,
            'low_score_rejected' => match ($score->rejectionReason) {
                ProductImageDiscoveryRejectionReason::WrongColor->value => ProductImageDiscoveryCandidateStatus::WrongColor,
                ProductImageDiscoveryRejectionReason::WrongProduct->value,
                ProductImageDiscoveryRejectionReason::WrongBrand->value => ProductImageDiscoveryCandidateStatus::WrongProduct,
                default => ProductImageDiscoveryCandidateStatus::LowScoreRejected,
            },
            default => ProductImageDiscoveryCandidateStatus::Rejected,
        };

        $updated = $store->updateCandidate($this->candidateId, [
            'status' => $candidateStatus->value,
            'source_trust_score' => $score->sourceTrustScore,
            'textual_match_score' => $score->textualMatchScore,
            'structured_match_score' => $score->structuredMatchScore,
            'visual_match_score' => $score->visualMatchScore,
            'quality_score' => $score->qualityScore,
            'risk_penalty' => $score->riskPenalty,
            'final_score' => $score->finalScore,
            'rejection_reason' => $score->rejectionReason,
            'evidence' => $score->evidence,
            'ai_analysis' => array_merge($aiAnalysis, [
                'issues' => $score->issues,
                'has_strong_match' => $score->hasStrongMatch,
            ]),
            'verified_at' => gmdate('c'),
        ]);

        $logger->record('pipeline.verify.completed', [
            'status' => $candidateStatus->value,
            'final_score' => $score->finalScore,
            'issues' => $score->issues,
            'rejection_reason' => $score->rejectionReason,
        ], requestId: $this->requestId, candidateId: $this->candidateId);

        $store->updateRequest($this->requestId, [
            'status' => $candidateStatus === ProductImageDiscoveryCandidateStatus::VerifiedMatch
                ? ProductImageDiscoveryRequestStatus::Matched->value
                : ProductImageDiscoveryRequestStatus::ManualReview->value,
        ]);

        if ($candidateStatus === ProductImageDiscoveryCandidateStatus::VerifiedMatch) {
            $this->dispatchIfPossible(new DownloadCandidateImageJob($this->requestId, $this->candidateId));
        }

        return $updated;
    }
}
