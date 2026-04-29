<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search\Data;

final class SearchProviderDefinition
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $driver,
        public readonly ?string $baseUrl = null,
        public readonly ?string $apiKey = null,
        public readonly ?string $apiSecret = null,
        public readonly array $config = [],
        public readonly int $priority = 100,
        public readonly int $timeoutSeconds = 15,
        public readonly ?int $rateLimitPerMinute = null,
        public readonly bool $isActive = true,
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            code: (string) ($attributes['code'] ?? ''),
            name: (string) ($attributes['name'] ?? $attributes['code'] ?? ''),
            driver: (string) ($attributes['driver'] ?? ''),
            baseUrl: self::normalizeString($attributes['base_url'] ?? $attributes['baseUrl'] ?? null),
            apiKey: self::normalizeString($attributes['api_key'] ?? $attributes['apiKey'] ?? null),
            apiSecret: self::normalizeString($attributes['api_secret'] ?? $attributes['apiSecret'] ?? null),
            config: is_array($attributes['config'] ?? null) ? $attributes['config'] : [],
            priority: (int) ($attributes['priority'] ?? 100),
            timeoutSeconds: max(1, (int) ($attributes['timeout_seconds'] ?? $attributes['timeoutSeconds'] ?? 15)),
            rateLimitPerMinute: isset($attributes['rate_limit_per_minute']) ? (int) $attributes['rate_limit_per_minute'] : null,
            isActive: (bool) ($attributes['is_active'] ?? $attributes['isActive'] ?? true),
        );
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function toSafeArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'driver' => $this->driver,
            'base_url' => $this->baseUrl,
            'config' => $this->config,
            'priority' => $this->priority,
            'timeout_seconds' => $this->timeoutSeconds,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
            'is_active' => $this->isActive,
            'has_api_key' => $this->apiKey !== null,
            'has_api_secret' => $this->apiSecret !== null,
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
