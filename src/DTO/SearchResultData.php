<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\DTO;

use JsonSerializable;
use Padosoft\ProductImageDiscovery\Services\Support\DomainNormalizer;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final readonly class SearchResultData implements JsonSerializable
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $provider,
        public ?string $title,
        public ?string $sourcePageUrl,
        public ?string $imageUrl = null,
        public ?string $snippet = null,
        public ?int $rank = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $mimeType = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: TextNormalizer::nullableString($data['provider'] ?? null) ?? 'unknown',
            title: TextNormalizer::nullableString($data['title'] ?? null),
            sourcePageUrl: DomainNormalizer::normalizeUrl(TextNormalizer::nullableString($data['source_page_url'] ?? $data['sourcePageUrl'] ?? $data['url'] ?? null)),
            imageUrl: DomainNormalizer::normalizeUrl(TextNormalizer::nullableString($data['image_url'] ?? $data['imageUrl'] ?? null), TextNormalizer::nullableString($data['source_page_url'] ?? $data['url'] ?? null)),
            snippet: TextNormalizer::nullableString($data['snippet'] ?? $data['description'] ?? null),
            rank: isset($data['rank']) ? (int) $data['rank'] : null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            mimeType: TextNormalizer::nullableString($data['mime_type'] ?? $data['mimeType'] ?? null),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function toCandidate(string $resolverName = 'search_result'): CandidateImageData
    {
        return new CandidateImageData(
            sourcePageUrl: $this->sourcePageUrl,
            imageUrl: $this->imageUrl,
            sourceDomain: DomainNormalizer::normalizeDomain($this->sourcePageUrl),
            resolverName: $resolverName,
            searchProvider: $this->provider,
            title: $this->title,
            description: $this->snippet,
            width: $this->width,
            height: $this->height,
            mimeType: $this->mimeType,
            evidence: ['search_result' => $this->toArray()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'title' => $this->title,
            'source_page_url' => $this->sourcePageUrl,
            'image_url' => $this->imageUrl,
            'snippet' => $this->snippet,
            'rank' => $this->rank,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
            'metadata' => $this->metadata,
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
