<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Support;

use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderConfigRepositoryInterface;

final class InMemorySearchProviderConfigRepository implements SearchProviderConfigRepositoryInterface
{
    /**
     * @param  array<int, SearchProviderDefinition>  $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function getActiveProviders(): array
    {
        return $this->definitions;
    }
}
