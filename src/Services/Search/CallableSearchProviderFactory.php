<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use Closure;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

final class CallableSearchProviderFactory implements SearchProviderFactoryInterface
{
    /**
     * @param  Closure(SearchProviderDefinition): ProductImageSearchProviderInterface  $factory
     */
    public function __construct(private readonly Closure $factory)
    {
    }

    public function make(SearchProviderDefinition $definition): ProductImageSearchProviderInterface
    {
        return ($this->factory)($definition);
    }
}
