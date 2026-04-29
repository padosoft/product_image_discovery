<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\DTO;

use JsonSerializable;

final readonly class CandidateScoreData implements JsonSerializable
{
    /**
     * @param array<string, mixed> $evidence
     * @param list<string> $issues
     */
    public function __construct(
        public int $sourceTrustScore = 0,
        public int $textualMatchScore = 0,
        public int $structuredMatchScore = 0,
        public int $visualMatchScore = 0,
        public int $qualityScore = 0,
        public int $riskPenalty = 0,
        public int $finalScore = 0,
        public array $evidence = [],
        public array $issues = [],
        public bool $hasStrongMatch = false,
        public bool $sourceTrusted = false,
        public bool $allowAutoPublish = false,
        public bool $allowDownload = true,
        public bool $brandMatched = false,
        public bool $brandMismatch = false,
        public bool $colorMatched = false,
        public bool $colorMismatch = false,
        public bool $modelMatched = false,
        public bool $modelMismatch = false,
        public bool $qualityPassed = false,
        public ?bool $robotsAllowed = null,
        public ?string $rejectionReason = null,
        public string $status = 'candidate',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceTrustScore: max(0, min(100, (int) ($data['source_trust_score'] ?? $data['sourceTrustScore'] ?? 0))),
            textualMatchScore: max(0, min(100, (int) ($data['textual_match_score'] ?? $data['textualMatchScore'] ?? 0))),
            structuredMatchScore: max(0, min(100, (int) ($data['structured_match_score'] ?? $data['structuredMatchScore'] ?? 0))),
            visualMatchScore: max(0, min(100, (int) ($data['visual_match_score'] ?? $data['visualMatchScore'] ?? 0))),
            qualityScore: max(0, min(100, (int) ($data['quality_score'] ?? $data['qualityScore'] ?? 0))),
            riskPenalty: max(0, (int) ($data['risk_penalty'] ?? $data['riskPenalty'] ?? 0)),
            finalScore: max(0, min(100, (int) ($data['final_score'] ?? $data['finalScore'] ?? 0))),
            evidence: is_array($data['evidence'] ?? null) ? $data['evidence'] : [],
            issues: array_values(array_map('strval', is_array($data['issues'] ?? null) ? $data['issues'] : [])),
            hasStrongMatch: (bool) ($data['has_strong_match'] ?? $data['hasStrongMatch'] ?? false),
            sourceTrusted: (bool) ($data['source_trusted'] ?? $data['sourceTrusted'] ?? false),
            allowAutoPublish: (bool) ($data['allow_auto_publish'] ?? $data['allowAutoPublish'] ?? false),
            allowDownload: (bool) ($data['allow_download'] ?? $data['allowDownload'] ?? true),
            brandMatched: (bool) ($data['brand_matched'] ?? $data['brandMatched'] ?? false),
            brandMismatch: (bool) ($data['brand_mismatch'] ?? $data['brandMismatch'] ?? false),
            colorMatched: (bool) ($data['color_matched'] ?? $data['colorMatched'] ?? false),
            colorMismatch: (bool) ($data['color_mismatch'] ?? $data['colorMismatch'] ?? false),
            modelMatched: (bool) ($data['model_matched'] ?? $data['modelMatched'] ?? false),
            modelMismatch: (bool) ($data['model_mismatch'] ?? $data['modelMismatch'] ?? false),
            qualityPassed: (bool) ($data['quality_passed'] ?? $data['qualityPassed'] ?? false),
            robotsAllowed: array_key_exists('robots_allowed', $data) ? (bool) $data['robots_allowed'] : (array_key_exists('robotsAllowed', $data) ? (bool) $data['robotsAllowed'] : null),
            rejectionReason: isset($data['rejection_reason']) ? (string) $data['rejection_reason'] : (isset($data['rejectionReason']) ? (string) $data['rejectionReason'] : null),
            status: (string) ($data['status'] ?? 'candidate'),
        );
    }

    public function canAutoPublish(int $threshold = 85, int $minQualityScore = 70): bool
    {
        return $this->finalScore >= $threshold
            && $this->sourceTrusted
            && $this->allowAutoPublish
            && $this->hasStrongMatch
            && ! $this->brandMismatch
            && ! $this->colorMismatch
            && ! $this->modelMismatch
            && $this->qualityPassed
            && $this->qualityScore >= $minQualityScore
            && $this->robotsAllowed !== false
            && ! in_array('WATERMARK_DETECTED', $this->issues, true)
            && ! in_array('TEXT_OVERLAY_DETECTED', $this->issues, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_trust_score' => $this->sourceTrustScore,
            'textual_match_score' => $this->textualMatchScore,
            'structured_match_score' => $this->structuredMatchScore,
            'visual_match_score' => $this->visualMatchScore,
            'quality_score' => $this->qualityScore,
            'risk_penalty' => $this->riskPenalty,
            'final_score' => $this->finalScore,
            'evidence' => $this->evidence,
            'issues' => $this->issues,
            'has_strong_match' => $this->hasStrongMatch,
            'source_trusted' => $this->sourceTrusted,
            'allow_auto_publish' => $this->allowAutoPublish,
            'allow_download' => $this->allowDownload,
            'brand_matched' => $this->brandMatched,
            'brand_mismatch' => $this->brandMismatch,
            'color_matched' => $this->colorMatched,
            'color_mismatch' => $this->colorMismatch,
            'model_matched' => $this->modelMatched,
            'model_mismatch' => $this->modelMismatch,
            'quality_passed' => $this->qualityPassed,
            'robots_allowed' => $this->robotsAllowed,
            'rejection_reason' => $this->rejectionReason,
            'status' => $this->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
