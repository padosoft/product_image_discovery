<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

final class FakeSearchProvider implements ProductImageSearchProviderInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $imageResults
     * @param  array<int, array<string, mixed>>  $webResults
     */
    public function __construct(
        private readonly SearchProviderDefinition $definition,
        private readonly array $imageResults = [],
        private readonly array $webResults = [],
    ) {
    }

    public static function fromDefinition(SearchProviderDefinition $definition): self
    {
        return new self(
            definition: $definition,
            imageResults: is_array($definition->configValue('image_results', [])) ? $definition->configValue('image_results', []) : [],
            webResults: is_array($definition->configValue('web_results', [])) ? $definition->configValue('web_results', []) : [],
        );
    }

    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $this->guardAgainstConfiguredFailure('images');

        return new ProductImageSearchResultCollection($this->scopedResults($this->imageResults, $query));
    }

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $this->guardAgainstConfiguredFailure('web');

        return new ProductImageSearchResultCollection($this->scopedResults($this->webResults, $query));
    }

    public function supportsImageSearch(): bool
    {
        return (bool) $this->definition->configValue('supports_image_search', true);
    }

    public function supportsSiteFilter(): bool
    {
        return (bool) $this->definition->configValue('supports_site_filter', true);
    }

    private function guardAgainstConfiguredFailure(string $mode): void
    {
        $failModes = $this->definition->configValue('throw_for', []);
        $failModes = is_array($failModes) ? $failModes : [$failModes];

        if ((bool) $this->definition->configValue('throw', false) || in_array($mode, $failModes, true)) {
            throw new RuntimeException(sprintf('Fake provider [%s] forced failure for %s search.', $this->definition->code, $mode));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function scopedResults(array $results, ProductImageSearchQueryData $query): array
    {
        $filtered = $results;

        if ($query->site !== null && $query->site !== '') {
            $filtered = array_values(array_filter(
                $filtered,
                static function (array $result) use ($query): bool {
                    $pageUrl = (string) ($result['page_url'] ?? $result['pageUrl'] ?? '');

                    return $pageUrl === '' || str_contains($pageUrl, $query->site);
                },
            ));
        }

        return array_slice($filtered, 0, $query->limit);
    }
}
