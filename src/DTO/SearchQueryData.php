<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\DTO;

use JsonSerializable;

final readonly class SearchQueryData implements JsonSerializable
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $query,
        public string $intent,
        public int $priority,
        public int $weight = 0,
        public string $type = 'image',
        public ?string $siteDomain = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'intent' => $this->intent,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'type' => $this->type,
            'site_domain' => $this->siteDomain,
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
