<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

interface SearchProviderFactoryInterface
{
    public function make(SearchProviderDefinition $definition): ProductImageSearchProviderInterface;
}
