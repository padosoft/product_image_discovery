<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Extraction;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\Services\Support\DomainNormalizer;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final class GenericStructuredExtractor
{
    /**
     * @return list<CandidateImageData>
     */
    public function extract(string $html, string $pageUrl): array
    {
        $candidates = array_merge(
            $this->extractJsonLd($html, $pageUrl),
            $this->extractOpenGraph($html, $pageUrl),
            $this->extractGallery($html, $pageUrl),
        );

        $seen = [];
        $deduped = [];

        foreach ($candidates as $candidate) {
            if (! $candidate->hasUsableImageUrl()) {
                continue;
            }

            $key = strtolower((string) $candidate->imageUrl);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $candidate;
        }

        return $deduped;
    }

    /**
     * @return list<CandidateImageData>
     */
    public function extractJsonLd(string $html, string $pageUrl): array
    {
        $scripts = $this->jsonLdScripts($html);
        $candidates = [];

        foreach ($scripts as $script) {
            $decoded = json_decode($script, true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->findProducts($decoded) as $product) {
                $images = $this->imageUrls($product['image'] ?? null, $pageUrl);
                $structured = $this->normalizeProductData($product);

                foreach ($images as $imageUrl) {
                    $candidates[] = new CandidateImageData(
                        sourcePageUrl: DomainNormalizer::normalizeUrl($pageUrl),
                        imageUrl: $imageUrl,
                        sourceDomain: DomainNormalizer::normalizeDomain($pageUrl),
                        resolverName: 'json_ld',
                        title: TextNormalizer::nullableString($product['name'] ?? null),
                        description: TextNormalizer::nullableString($product['description'] ?? null),
                        role: 'main_product_image',
                        structuredData: $structured,
                        evidence: ['extractor' => 'json_ld_product'],
                    );
                }
            }
        }

        return $candidates;
    }

    /**
     * @return list<CandidateImageData>
     */
    public function extractOpenGraph(string $html, string $pageUrl): array
    {
        $dom = $this->loadDom($html);
        $xpath = new DOMXPath($dom);
        $meta = $this->metaTags($xpath);
        $canonical = $this->canonicalUrl($xpath, $pageUrl);
        $images = array_merge($meta['og:image'] ?? [], $meta['twitter:image'] ?? []);
        $candidates = [];

        foreach ($images as $image) {
            $imageUrl = DomainNormalizer::normalizeUrl($image, $pageUrl);

            if ($imageUrl === null) {
                continue;
            }

            $candidates[] = new CandidateImageData(
                sourcePageUrl: $canonical ?? DomainNormalizer::normalizeUrl($pageUrl),
                imageUrl: $imageUrl,
                sourceDomain: DomainNormalizer::normalizeDomain($pageUrl),
                resolverName: 'open_graph',
                title: $meta['og:title'][0] ?? $meta['twitter:title'][0] ?? null,
                description: $meta['og:description'][0] ?? $meta['twitter:description'][0] ?? null,
                role: 'main_product_image',
                structuredData: [
                    'title' => $meta['og:title'][0] ?? null,
                    'description' => $meta['og:description'][0] ?? null,
                    'canonical_url' => $canonical,
                ],
                evidence: ['extractor' => 'open_graph'],
            );
        }

        return $candidates;
    }

    /**
     * @return list<CandidateImageData>
     */
    public function extractGallery(string $html, string $pageUrl): array
    {
        $dom = $this->loadDom($html);
        $xpath = new DOMXPath($dom);
        $candidates = [];

        foreach ($xpath->query('//img|//source') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $src = $this->bestImageSource($node);
            $imageUrl = DomainNormalizer::normalizeUrl($src, $pageUrl);

            if ($imageUrl === null || $this->looksLikeNonProductAsset($node, $imageUrl)) {
                continue;
            }

            [$width, $height] = $this->dimensions($node);

            if (($width !== null && $width < 120) || ($height !== null && $height < 120)) {
                continue;
            }

            $role = $this->role($node, $imageUrl);

            $candidates[] = new CandidateImageData(
                sourcePageUrl: DomainNormalizer::normalizeUrl($pageUrl),
                imageUrl: $imageUrl,
                sourceDomain: DomainNormalizer::normalizeDomain($pageUrl),
                resolverName: 'generic_gallery',
                altText: TextNormalizer::nullableString($node->getAttribute('alt') ?: $node->getAttribute('title')),
                role: $role,
                width: $width,
                height: $height,
                evidence: [
                    'extractor' => 'generic_gallery',
                    'node' => strtolower($node->tagName),
                    'class' => $node->getAttribute('class'),
                    'id' => $node->getAttribute('id'),
                ],
            );
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function jsonLdScripts(string $html): array
    {
        preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches);

        return array_map(
            static fn (string $script): string => html_entity_decode(trim($script), ENT_QUOTES | ENT_HTML5),
            $matches[1] ?? [],
        );
    }

    /**
     * @param array<string|int, mixed> $node
     * @return list<array<string, mixed>>
     */
    private function findProducts(array $node): array
    {
        $products = [];

        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Product', $types, true)) {
            $products[] = $node;
        }

        foreach (['@graph', 'itemListElement'] as $key) {
            if (isset($node[$key]) && is_array($node[$key])) {
                foreach ($node[$key] as $child) {
                    if (is_array($child)) {
                        $products = array_merge($products, $this->findProducts($child));
                    }
                }
            }
        }

        foreach ($node as $child) {
            if (is_array($child) && array_is_list($child)) {
                foreach ($child as $item) {
                    if (is_array($item)) {
                        $products = array_merge($products, $this->findProducts($item));
                    }
                }
            }
        }

        return $products;
    }

    /**
     * @return list<string>
     */
    private function imageUrls(mixed $images, string $pageUrl): array
    {
        if ($images === null) {
            return [];
        }

        if (is_string($images)) {
            $url = DomainNormalizer::normalizeUrl($images, $pageUrl);

            return $url === null ? [] : [$url];
        }

        if (! is_array($images)) {
            return [];
        }

        $urls = [];

        foreach ($images as $image) {
            if (is_string($image)) {
                $url = DomainNormalizer::normalizeUrl($image, $pageUrl);
            } elseif (is_array($image)) {
                $url = DomainNormalizer::normalizeUrl($image['url'] ?? $image['contentUrl'] ?? null, $pageUrl);
            } else {
                $url = null;
            }

            if ($url !== null) {
                $urls[$url] = $url;
            }
        }

        return array_values($urls);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function normalizeProductData(array $product): array
    {
        $brand = $product['brand'] ?? null;

        if (is_array($brand)) {
            $brand = $brand['name'] ?? null;
        }

        return array_filter([
            'brand' => TextNormalizer::nullableString($brand),
            'sku' => TextNormalizer::nullableString($product['sku'] ?? null),
            'mpn' => TextNormalizer::nullableString($product['mpn'] ?? null),
            'gtin' => TextNormalizer::nullableString($product['gtin'] ?? $product['gtin13'] ?? $product['gtin14'] ?? null),
            'color' => TextNormalizer::nullableString($product['color'] ?? null),
            'material' => TextNormalizer::nullableString($product['material'] ?? null),
            'description' => TextNormalizer::nullableString($product['description'] ?? null),
            'name' => TextNormalizer::nullableString($product['name'] ?? null),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return array<string, list<string>>
     */
    private function metaTags(DOMXPath $xpath): array
    {
        $meta = [];

        foreach ($xpath->query('//meta[@content]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $key = $node->getAttribute('property') ?: $node->getAttribute('name');
            $content = TextNormalizer::nullableString($node->getAttribute('content'));

            if ($key !== '' && $content !== null) {
                $meta[strtolower($key)][] = $content;
            }
        }

        return $meta;
    }

    private function canonicalUrl(DOMXPath $xpath, string $pageUrl): ?string
    {
        $nodes = $xpath->query('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]/@href');

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        return DomainNormalizer::normalizeUrl($nodes->item(0)?->nodeValue, $pageUrl);
    }

    private function bestImageSource(DOMElement $node): ?string
    {
        foreach (['data-zoom-image', 'data-large', 'data-original', 'data-src'] as $attribute) {
            $value = TextNormalizer::nullableString($node->getAttribute($attribute));

            if ($value !== null) {
                return $value;
            }
        }

        $srcset = TextNormalizer::nullableString($node->getAttribute('srcset'));

        if ($srcset !== null) {
            return $this->largestSrcsetCandidate($srcset);
        }

        return TextNormalizer::nullableString($node->getAttribute('src'));
    }

    private function largestSrcsetCandidate(string $srcset): ?string
    {
        $bestUrl = null;
        $bestWidth = -1;

        foreach (explode(',', $srcset) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate));
            $url = $parts[0] ?? null;
            $descriptor = $parts[1] ?? '';
            $width = str_ends_with($descriptor, 'w') ? (int) $descriptor : 0;

            if ($url !== null && $width >= $bestWidth) {
                $bestUrl = $url;
                $bestWidth = $width;
            }
        }

        return $bestUrl;
    }

    private function looksLikeNonProductAsset(DOMElement $node, string $imageUrl): bool
    {
        $text = strtolower($imageUrl . ' ' . $node->getAttribute('class') . ' ' . $node->getAttribute('id') . ' ' . $node->getAttribute('alt'));

        foreach (['logo', 'icon', 'favicon', 'sprite', 'placeholder', 'tracking', 'pixel'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:?int,1:?int}
     */
    private function dimensions(DOMElement $node): array
    {
        $width = $node->getAttribute('width');
        $height = $node->getAttribute('height');

        return [
            is_numeric($width) ? (int) $width : null,
            is_numeric($height) ? (int) $height : null,
        ];
    }

    private function role(DOMElement $node, string $imageUrl): string
    {
        $text = strtolower($imageUrl . ' ' . $node->getAttribute('class') . ' ' . $node->getAttribute('id'));

        if (str_contains($text, 'thumb')) {
            return 'thumbnail';
        }

        foreach (['product', 'gallery', 'main', 'pdp', 'zoom'] as $needle) {
            if (str_contains($text, $needle)) {
                return 'main_product_image';
            }
        }

        return 'unclassified';
    }
}
