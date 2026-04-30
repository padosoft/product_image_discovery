<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\DTO;

use JsonSerializable;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final readonly class ProductIdentityData implements JsonSerializable
{
    /**
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        public int|string|null $clientId,
        public ?string $erpModelId,
        public ?string $erpModelColorId,
        public ?string $erpModelColorSizeId = null,
        public ?string $brand = null,
        public ?string $supplier = null,
        public ?string $sku = null,
        public ?string $supplierSku = null,
        public ?string $modelCode = null,
        public ?string $colorCode = null,
        public ?string $colorName = null,
        public ?string $ean = null,
        public ?string $season = null,
        public ?string $category = null,
        public ?string $material = null,
        public ?string $description = null,
        public array $rawPayload = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        return new self(
            clientId: $data['client_id'] ?? $data['clientId'] ?? null,
            erpModelId: TextNormalizer::nullableString($data['erp_model_id'] ?? $data['erpModelId'] ?? null),
            erpModelColorId: TextNormalizer::nullableString($data['erp_model_color_id'] ?? $data['erpModelColorId'] ?? null),
            erpModelColorSizeId: TextNormalizer::nullableString($data['erp_model_color_size_id'] ?? $data['erpModelColorSizeId'] ?? null),
            brand: TextNormalizer::nullableString($data['brand'] ?? null),
            supplier: TextNormalizer::nullableString($data['supplier'] ?? null),
            sku: TextNormalizer::nullableString($data['sku'] ?? null),
            supplierSku: TextNormalizer::nullableString($data['supplier_sku'] ?? $data['supplierSku'] ?? null),
            modelCode: TextNormalizer::nullableString($data['model_code'] ?? $data['modelCode'] ?? null),
            colorCode: TextNormalizer::nullableString($data['color_code'] ?? $data['colorCode'] ?? null),
            colorName: TextNormalizer::nullableString($data['color_name'] ?? $data['colorName'] ?? null),
            ean: TextNormalizer::normalizeEan(TextNormalizer::nullableString($data['ean'] ?? $data['gtin'] ?? null)),
            season: TextNormalizer::nullableString($data['season'] ?? null),
            category: TextNormalizer::nullableString($data['category'] ?? null),
            material: TextNormalizer::nullableString($data['material'] ?? null),
            description: TextNormalizer::nullableString($data['description'] ?? $data['name'] ?? $data['title'] ?? $metadata['description'] ?? $metadata['title'] ?? null),
            rawPayload: is_array($data['raw_payload'] ?? null) ? $data['raw_payload'] : $data,
        );
    }

    public function normalizedBrand(): ?string
    {
        return TextNormalizer::normalizeText($this->brand);
    }

    public function normalizedSupplierSku(): ?string
    {
        return TextNormalizer::normalizeCode($this->supplierSku);
    }

    public function normalizedSku(): ?string
    {
        return TextNormalizer::normalizeCode($this->sku);
    }

    public function normalizedModelCode(): ?string
    {
        return TextNormalizer::normalizeCode($this->modelCode);
    }

    public function normalizedColorCode(): ?string
    {
        return TextNormalizer::normalizeCode($this->colorCode);
    }

    public function normalizedColorName(): ?string
    {
        return TextNormalizer::canonicalColor($this->colorName);
    }

    public function hasStrongIdentifier(): bool
    {
        return $this->ean !== null
            || $this->normalizedSupplierSku() !== null
            || $this->normalizedSku() !== null
            || $this->normalizedModelCode() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function strongIdentifiers(): array
    {
        return array_filter([
            'ean' => $this->ean,
            'supplier_sku' => $this->normalizedSupplierSku(),
            'sku' => $this->normalizedSku(),
            'model_code' => $this->normalizedModelCode(),
        ], static fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchIntent(): array
    {
        return array_filter([
            'client_id' => $this->clientId,
            'erp_model_id' => $this->erpModelId,
            'erp_model_color_id' => $this->erpModelColorId,
            'brand' => $this->brand,
            'supplier' => $this->supplier,
            'sku' => $this->sku,
            'supplier_sku' => $this->supplierSku,
            'model_code' => $this->modelCode,
            'color_code' => $this->colorCode,
            'color_name' => $this->colorName,
            'ean' => $this->ean,
            'season' => $this->season,
            'category' => $this->category,
            'material' => $this->material,
            'description' => $this->description,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'erp_model_id' => $this->erpModelId,
            'erp_model_color_id' => $this->erpModelColorId,
            'erp_model_color_size_id' => $this->erpModelColorSizeId,
            'brand' => $this->brand,
            'supplier' => $this->supplier,
            'sku' => $this->sku,
            'supplier_sku' => $this->supplierSku,
            'model_code' => $this->modelCode,
            'color_code' => $this->colorCode,
            'color_name' => $this->colorName,
            'ean' => $this->ean,
            'season' => $this->season,
            'category' => $this->category,
            'material' => $this->material,
            'description' => $this->description,
            'raw_payload' => $this->rawPayload,
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
