<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search\Contracts;

/**
 * Generic search-event logging contract used by SearchProviderManager.
 *
 * Decoupled from the domain-specific ProductImageEventLogger so the search
 * layer can be extracted into a standalone package (padosoft/laravel-search-providers)
 * without a hard dependency on the product-image-discovery audit model.
 *
 * Any logger that exposes record(string $eventType, array $context = [], string $level = 'info')
 * satisfies this contract. Return type is intentionally mixed to allow
 * existing loggers to return a structured event for downstream usage
 * (e.g. database audit storage) without forcing a void return.
 */
interface SearchEventLoggerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $eventType, array $context = [], string $level = 'info'): mixed;
}
