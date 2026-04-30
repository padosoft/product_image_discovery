<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\DTO;

final readonly class AiCandidateVerificationData
{
    public function __construct(
        public bool $enabled,
        public bool $available,
        public bool $match,
        public bool $variantSafe,
        public int $confidence,
        public bool $brandMatch,
        public bool $modelMatch,
        public bool $colorMatch,
        public bool $productTypeMatch,
        public bool $imageQualityOk,
        public ?string $rejectionReason,
        public string $notes,
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $status = null,
        public ?string $error = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function skipped(string $reason): self
    {
        return new self(
            enabled: false,
            available: false,
            match: false,
            variantSafe: false,
            confidence: 0,
            brandMatch: false,
            modelMatch: false,
            colorMatch: false,
            productTypeMatch: false,
            imageQualityOk: false,
            rejectionReason: null,
            notes: $reason,
            status: 'skipped',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload, ?string $provider = null, ?string $model = null): self
    {
        return new self(
            enabled: true,
            available: true,
            match: (bool) ($payload['match'] ?? false),
            variantSafe: (bool) ($payload['variant_safe'] ?? false),
            confidence: max(0, min(100, (int) ($payload['confidence'] ?? 0))),
            brandMatch: (bool) ($payload['brand_match'] ?? false),
            modelMatch: (bool) ($payload['model_match'] ?? false),
            colorMatch: (bool) ($payload['color_match'] ?? false),
            productTypeMatch: (bool) ($payload['product_type_match'] ?? false),
            imageQualityOk: (bool) ($payload['image_quality_ok'] ?? false),
            rejectionReason: self::nullableString($payload['rejection_reason'] ?? null),
            notes: self::nullableString($payload['notes'] ?? null) ?? '',
            provider: $provider,
            model: $model,
            status: 'completed',
        );
    }

    public static function failed(string $error, ?string $provider = null, ?string $model = null): self
    {
        return new self(
            enabled: true,
            available: false,
            match: false,
            variantSafe: false,
            confidence: 0,
            brandMatch: false,
            modelMatch: false,
            colorMatch: false,
            productTypeMatch: false,
            imageQualityOk: false,
            rejectionReason: null,
            notes: 'AI verification failed.',
            provider: $provider,
            model: $model,
            status: 'failed',
            error: $error,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'enabled' => $this->enabled,
            'available' => $this->available,
            'match' => $this->match,
            'variant_safe' => $this->variantSafe,
            'confidence' => $this->confidence,
            'brand_match' => $this->brandMatch,
            'model_match' => $this->modelMatch,
            'color_match' => $this->colorMatch,
            'product_type_match' => $this->productTypeMatch,
            'image_quality_ok' => $this->imageQualityOk,
            'rejection_reason' => $this->rejectionReason,
            'notes' => $this->notes,
            'provider' => $this->provider,
            'model' => $this->model,
            'status' => $this->status,
            'error' => $this->error,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
