<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Extraction;

use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\Services\Support\DomainNormalizer;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final class PatternSourceResolver
{
    /**
     * @param list<array<string, mixed>> $trustedSources
     * @return list<CandidateImageData>
     */
    public function resolve(ProductIdentityData $identity, array $trustedSources): array
    {
        $candidates = [];
        $seen = [];

        foreach ($trustedSources as $source) {
            if (($source['is_active'] ?? true) === false || ($source['allow_search'] ?? true) === false) {
                continue;
            }

            $domain = DomainNormalizer::normalizeDomain($source['domain'] ?? null);

            if ($domain === null) {
                continue;
            }

            foreach ($this->patterns($source['url_patterns'] ?? []) as $pattern) {
                $url = $this->applyPattern($pattern['pattern'], $identity, $domain);

                if ($url === null) {
                    continue;
                }

                $type = $pattern['type'];
                $imageUrl = $type === 'image' ? $url : null;
                $sourcePageUrl = $type === 'image' ? ($pattern['source_page_url'] ?? $url) : $url;
                $key = strtolower(($imageUrl ?? '') . '|' . $sourcePageUrl);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $candidates[] = new CandidateImageData(
                    sourcePageUrl: $sourcePageUrl,
                    imageUrl: $imageUrl,
                    sourceDomain: $domain,
                    resolverName: 'pattern:' . $type,
                    role: $type === 'image' ? 'direct_image' : 'source_page',
                    structuredData: [],
                    evidence: [
                        'pattern' => $pattern['pattern'],
                        'pattern_type' => $type,
                    ],
                    sourceTrusted: true,
                    sourceTrustScore: max(0, min(100, (int) ($source['trust_score'] ?? 50))),
                    allowAutoPublish: (bool) ($source['allow_auto_publish'] ?? false),
                    allowDownload: (bool) ($source['allow_download'] ?? true),
                );
            }
        }

        return $candidates;
    }

    /**
     * @return list<array{type:string,pattern:string,source_page_url?:string}>
     */
    private function patterns(mixed $patterns): array
    {
        if (is_string($patterns)) {
            $decoded = json_decode($patterns, true);
            $patterns = is_array($decoded) ? $decoded : [$patterns];
        }

        if (! is_array($patterns)) {
            return [];
        }

        $normalized = [];

        foreach ($patterns as $key => $pattern) {
            if (is_string($pattern)) {
                $normalized[] = ['type' => is_string($key) && str_contains($key, 'image') ? 'image' : 'page', 'pattern' => $pattern];
                continue;
            }

            if (! is_array($pattern)) {
                continue;
            }

            $urlPattern = TextNormalizer::nullableString($pattern['pattern'] ?? $pattern['url'] ?? null);

            if ($urlPattern === null) {
                continue;
            }

            $normalized[] = [
                'type' => TextNormalizer::nullableString($pattern['type'] ?? null) === 'image' ? 'image' : 'page',
                'pattern' => $urlPattern,
                'source_page_url' => TextNormalizer::nullableString($pattern['source_page_url'] ?? null),
            ];
        }

        return $normalized;
    }

    private function applyPattern(string $pattern, ProductIdentityData $identity, string $domain): ?string
    {
        $values = [
            'ean' => $identity->ean,
            'sku' => $identity->sku,
            'supplier_sku' => $identity->supplierSku,
            'model_code' => $identity->modelCode,
            'color_code' => $identity->colorCode,
            'color_name' => $identity->colorName,
            'brand' => $identity->brand,
        ];

        preg_match_all('/\{([a-z_]+)\}/', $pattern, $matches);

        foreach ($matches[1] ?? [] as $placeholder) {
            if (empty($values[$placeholder])) {
                return null;
            }

            $pattern = str_replace('{' . $placeholder . '}', rawurlencode((string) $values[$placeholder]), $pattern);
        }

        if (str_starts_with($pattern, '/')) {
            $pattern = 'https://' . $domain . $pattern;
        } elseif (! preg_match('#^https?://#i', $pattern)) {
            $pattern = 'https://' . $domain . '/' . ltrim($pattern, '/');
        }

        return DomainNormalizer::normalizeUrl($pattern);
    }
}
