<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search\Data;

final class SearchProviderExecutionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $attempts
     */
    public function __construct(
        public readonly ?SearchProviderDefinition $provider,
        public readonly ProductImageSearchResultCollection $results,
        public readonly array $attempts = [],
        public readonly bool $usedFallback = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider?->toSafeArray(),
            'results' => $this->results->toArray(),
            'attempts' => $this->attempts,
            'used_fallback' => $this->usedFallback,
        ];
    }
}
