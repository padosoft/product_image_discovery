<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Actions;

use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\DTO\SearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Support\DomainNormalizer;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final class GenerateSearchQueriesAction
{
    /**
     * @param list<array<string, mixed>> $trustedSources
     * @param array<string, mixed> $options
     * @return list<SearchQueryData>
     */
    public function handle(ProductIdentityData $identity, array $trustedSources = [], array $options = []): array
    {
        $maxQueries = max(1, (int) ($options['max_queries'] ?? 10));
        $supportsSiteFilter = (bool) ($options['supports_site_filter'] ?? true);
        $allowGenericWithoutStrongIdentifier = (bool) ($options['allow_generic_without_strong_identifier'] ?? false);

        $queries = [];
        $seen = [];
        $priority = 10;

        $add = function (?string $query, string $intent, int $weight, string $type = 'image', ?string $siteDomain = null, array $metadata = []) use (&$queries, &$seen, &$priority): void {
            $query = TextNormalizer::nullableString($query);

            if ($query === null) {
                return;
            }

            $key = strtolower($query);

            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $queries[] = new SearchQueryData(
                query: $query,
                intent: $intent,
                priority: $priority++,
                weight: $weight,
                type: $type,
                siteDomain: $siteDomain,
                metadata: $metadata,
            );
        };

        $brand = $this->quote($identity->brand);

        if ($identity->ean !== null) {
            $add($brand . ' ' . $this->quote($identity->ean), 'ean', 100);
        }

        if ($identity->supplierSku !== null && $identity->colorCode !== null) {
            $add($brand . ' ' . $this->quote($identity->supplierSku) . ' ' . $this->quote($identity->colorCode), 'supplier_sku_color_code', 99);
        }

        if ($identity->supplierSku !== null && $identity->colorName !== null) {
            $add($brand . ' ' . $this->quote($identity->supplierSku) . ' ' . $this->quote($identity->colorName), 'supplier_sku_color_name', 98);
        }

        if ($identity->modelCode !== null && $identity->colorCode !== null) {
            $add($brand . ' ' . $this->quote($identity->modelCode) . ' ' . $this->quote($identity->colorCode), 'model_code_color_code', 97);
        }

        if ($identity->modelCode !== null && $identity->colorName !== null) {
            $add($brand . ' ' . $this->quote($identity->modelCode) . ' ' . $this->quote($identity->colorName), 'model_code_color_name', 96);
        }

        if ($identity->supplierSku !== null) {
            $add($brand . ' ' . $this->quote($identity->supplierSku), 'supplier_sku', 90);
        }

        if ($identity->hasStrongIdentifier() || $allowGenericWithoutStrongIdentifier) {
            if ($identity->description !== null && $identity->colorName !== null) {
                $add($brand . ' ' . $this->quote($identity->description) . ' ' . $this->quote($identity->colorName), 'description_color', 40);
            }

            if ($identity->season !== null && $identity->modelCode !== null) {
                $add($brand . ' ' . $this->quote($identity->season) . ' ' . $this->quote($identity->modelCode), 'season_model_code', 35);
            }
        }

        if ($supportsSiteFilter) {
            foreach ($trustedSources as $source) {
                if (($source['is_active'] ?? true) === false || ($source['allow_search'] ?? true) === false) {
                    continue;
                }

                $domain = DomainNormalizer::normalizeDomain($source['domain'] ?? null);

                if ($domain === null) {
                    continue;
                }

                if ($identity->ean !== null) {
                    $add('site:' . $domain . ' ' . $this->quote($identity->ean), 'site_ean', 100, 'image', $domain, ['source' => $source]);
                }

                if ($identity->supplierSku !== null && $identity->colorCode !== null) {
                    $add('site:' . $domain . ' ' . $this->quote($identity->supplierSku) . ' ' . $this->quote($identity->colorCode), 'site_supplier_sku_color_code', 99, 'image', $domain, ['source' => $source]);
                }

                if ($identity->supplierSku !== null && $identity->colorName !== null) {
                    $add('site:' . $domain . ' ' . $this->quote($identity->supplierSku) . ' ' . $this->quote($identity->colorName), 'site_supplier_sku_color_name', 98, 'image', $domain, ['source' => $source]);
                }

                if ($identity->modelCode !== null && $identity->colorCode !== null) {
                    $add('site:' . $domain . ' ' . $this->quote($identity->modelCode) . ' ' . $this->quote($identity->colorCode), 'site_model_code_color_code', 97, 'image', $domain, ['source' => $source]);
                }

                if ($identity->modelCode !== null && $identity->colorName !== null) {
                    $add('site:' . $domain . ' ' . $this->quote($identity->modelCode) . ' ' . $this->quote($identity->colorName), 'site_model_code_color_name', 96, 'image', $domain, ['source' => $source]);
                }

                if ($identity->supplierSku !== null) {
                    $add('site:' . $domain . ' ' . $this->quote($identity->supplierSku), 'site_supplier_sku', 92, 'image', $domain, ['source' => $source]);
                }
            }
        }

        usort($queries, static fn (SearchQueryData $a, SearchQueryData $b): int => $b->weight <=> $a->weight ?: $a->priority <=> $b->priority);

        return array_slice($queries, 0, $maxQueries);
    }

    private function quote(?string $value): string
    {
        $value = TextNormalizer::nullableString($value);

        if ($value === null) {
            return '';
        }

        return '"' . str_replace('"', '\"', $value) . '"';
    }
}
