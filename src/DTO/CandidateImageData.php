<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\DTO;

use JsonSerializable;
use Padosoft\ProductImageDiscovery\Services\Support\DomainNormalizer;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final readonly class CandidateImageData implements JsonSerializable
{
    /**
     * @param array<string, mixed> $structuredData
     * @param array<string, mixed> $evidence
     * @param array<string, mixed> $aiAnalysis
     * @param array<string, mixed> $qualityAnalysis
     */
    public function __construct(
        public ?string $sourcePageUrl,
        public ?string $imageUrl,
        public ?string $sourceDomain = null,
        public ?string $resolverName = null,
        public ?string $searchProvider = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $altText = null,
        public ?string $role = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $mimeType = null,
        public ?int $fileSize = null,
        public array $structuredData = [],
        public array $evidence = [],
        public bool $sourceTrusted = false,
        public int $sourceTrustScore = 0,
        public bool $allowAutoPublish = false,
        public bool $allowDownload = true,
        public ?bool $robotsAllowed = null,
        public int $visualMatchScore = 0,
        public int $qualityScore = 0,
        public array $aiAnalysis = [],
        public array $qualityAnalysis = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sourcePageUrl = DomainNormalizer::normalizeUrl(TextNormalizer::nullableString($data['source_page_url'] ?? $data['sourcePageUrl'] ?? null));
        $imageUrl = DomainNormalizer::normalizeUrl(TextNormalizer::nullableString($data['image_url'] ?? $data['imageUrl'] ?? null), $sourcePageUrl);

        return new self(
            sourcePageUrl: $sourcePageUrl,
            imageUrl: $imageUrl,
            sourceDomain: DomainNormalizer::normalizeDomain(TextNormalizer::nullableString($data['source_domain'] ?? $data['sourceDomain'] ?? null) ?? $sourcePageUrl),
            resolverName: TextNormalizer::nullableString($data['resolver_name'] ?? $data['source_resolver'] ?? $data['resolverName'] ?? null),
            searchProvider: TextNormalizer::nullableString($data['search_provider'] ?? $data['searchProvider'] ?? null),
            title: TextNormalizer::nullableString($data['title'] ?? null),
            description: TextNormalizer::nullableString($data['description'] ?? null),
            altText: TextNormalizer::nullableString($data['alt_text'] ?? $data['altText'] ?? null),
            role: TextNormalizer::nullableString($data['role'] ?? null),
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            mimeType: TextNormalizer::nullableString($data['mime_type'] ?? $data['mimeType'] ?? null),
            fileSize: isset($data['file_size']) ? (int) $data['file_size'] : (isset($data['fileSize']) ? (int) $data['fileSize'] : null),
            structuredData: is_array($data['structured_data'] ?? null) ? $data['structured_data'] : (is_array($data['structuredData'] ?? null) ? $data['structuredData'] : []),
            evidence: is_array($data['evidence'] ?? null) ? $data['evidence'] : [],
            sourceTrusted: (bool) ($data['source_trusted'] ?? $data['sourceTrusted'] ?? false),
            sourceTrustScore: max(0, min(100, (int) ($data['source_trust_score'] ?? $data['sourceTrustScore'] ?? 0))),
            allowAutoPublish: (bool) ($data['allow_auto_publish'] ?? $data['allowAutoPublish'] ?? false),
            allowDownload: (bool) ($data['allow_download'] ?? $data['allowDownload'] ?? true),
            robotsAllowed: array_key_exists('robots_allowed', $data) ? (bool) $data['robots_allowed'] : (array_key_exists('robotsAllowed', $data) ? (bool) $data['robotsAllowed'] : null),
            visualMatchScore: max(0, min(100, (int) ($data['visual_match_score'] ?? $data['visualMatchScore'] ?? 0))),
            qualityScore: max(0, min(100, (int) ($data['quality_score'] ?? $data['qualityScore'] ?? 0))),
            aiAnalysis: is_array($data['ai_analysis'] ?? null) ? $data['ai_analysis'] : (is_array($data['aiAnalysis'] ?? null) ? $data['aiAnalysis'] : []),
            qualityAnalysis: is_array($data['quality_analysis'] ?? null) ? $data['quality_analysis'] : (is_array($data['qualityAnalysis'] ?? null) ? $data['qualityAnalysis'] : []),
        );
    }

    public function textCorpus(): string
    {
        return implode(' ', array_filter([
            $this->title,
            $this->description,
            $this->altText,
            $this->sourcePageUrl,
            $this->imageUrl,
            TextNormalizer::flattenStrings($this->structuredData),
            TextNormalizer::flattenStrings($this->evidence),
        ]));
    }

    public function hasUsableImageUrl(): bool
    {
        return $this->imageUrl !== null && DomainNormalizer::normalizeUrl($this->imageUrl, $this->sourcePageUrl) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_page_url' => $this->sourcePageUrl,
            'image_url' => $this->imageUrl,
            'source_domain' => $this->sourceDomain ?? DomainNormalizer::normalizeDomain($this->sourcePageUrl),
            'source_resolver' => $this->resolverName,
            'search_provider' => $this->searchProvider,
            'title' => $this->title,
            'description' => $this->description,
            'alt_text' => $this->altText,
            'role' => $this->role,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'structured_data' => $this->structuredData,
            'evidence' => $this->evidence,
            'source_trusted' => $this->sourceTrusted,
            'source_trust_score' => $this->sourceTrustScore,
            'allow_auto_publish' => $this->allowAutoPublish,
            'allow_download' => $this->allowDownload,
            'robots_allowed' => $this->robotsAllowed,
            'visual_match_score' => $this->visualMatchScore,
            'quality_score' => $this->qualityScore,
            'ai_analysis' => $this->aiAnalysis,
            'quality_analysis' => $this->qualityAnalysis,
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
