<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Support;

use Padosoft\LaravelAiSearchProviders\Contracts\SearchProviderConfigRepositoryInterface;
use Padosoft\LaravelAiSearchProviders\Data\SearchProviderDefinition;

final class InMemorySearchProviderConfigRepository implements SearchProviderConfigRepositoryInterface
{
    /**
     * @param  array<int, SearchProviderDefinition>  $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    /**
     * @return array<int, SearchProviderDefinition>
     */
    public function getActiveProviders(): array
    {
        return $this->definitions;
    }
}
