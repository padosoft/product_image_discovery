<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search\Data;

final class ProductImageSearchQueryData
{
    public function __construct(
        public readonly int|string|null $clientId = null,
        public readonly ?string $brand = null,
        public readonly ?string $model = null,
        public readonly ?string $color = null,
        public readonly ?string $ean = null,
        public readonly ?string $supplierSku = null,
        public readonly ?string $query = null,
        public readonly ?string $site = null,
        public readonly int $limit = 10,
        public readonly array $metadata = [],
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            clientId: $attributes['client_id'] ?? $attributes['clientId'] ?? null,
            brand: self::normalizeString($attributes['brand'] ?? null),
            model: self::normalizeString($attributes['model'] ?? $attributes['model_name'] ?? null),
            color: self::normalizeString($attributes['color'] ?? $attributes['color_name'] ?? null),
            ean: self::normalizeString($attributes['ean'] ?? null),
            supplierSku: self::normalizeString($attributes['supplier_sku'] ?? $attributes['supplierSku'] ?? null),
            query: self::normalizeString($attributes['query'] ?? null),
            site: self::normalizeString($attributes['site'] ?? null),
            limit: max(1, (int) ($attributes['limit'] ?? 10)),
            metadata: is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [],
        );
    }

    public function toSearchString(): string
    {
        if ($this->query !== null && $this->query !== '') {
            return $this->query;
        }

        $parts = array_filter([
            $this->brand,
            $this->model,
            $this->color,
            $this->ean,
            $this->supplierSku,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return implode(' ', $parts);
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            clientId: $this->clientId,
            brand: $this->brand,
            model: $this->model,
            color: $this->color,
            ean: $this->ean,
            supplierSku: $this->supplierSku,
            query: $this->query,
            site: $this->site,
            limit: $this->limit,
            metadata: array_replace_recursive($this->metadata, $metadata),
        );
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'ean' => $this->ean,
            'supplier_sku' => $this->supplierSku,
            'query' => $this->query,
            'site' => $this->site,
            'limit' => $this->limit,
            'metadata' => $this->metadata,
            'search_string' => $this->toSearchString(),
        ];
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
