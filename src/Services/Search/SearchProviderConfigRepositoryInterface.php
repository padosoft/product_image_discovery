<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

interface SearchProviderConfigRepositoryInterface
{
    /**
     * @return array<int, SearchProviderDefinition>
     */
    public function getActiveProviders(): array;
}
