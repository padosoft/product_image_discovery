<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

final class BraveSearchProvider extends AbstractHttpSearchProvider
{
    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request('/res/v1/images/search', [
            'q' => $this->applySiteFilter($query),
            'count' => $query->limit,
        ]);

        $results = $payload['results'] ?? [];

        if (! is_array($results)) {
            throw new RuntimeException('Unexpected Brave images payload.');
        }

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $imageUrl = $this->pickUrl($hit, [
                'properties.url',
                'image.url',
                'image.src',
            ]);
            $pageUrl = $this->pickUrl($hit, [
                'page_url',
                'url',
                'source',
            ]);
            $sourceDomain = $this->extractDomain($pageUrl)
                ?? $this->normalizeDomain($this->pick($hit, ['meta_url.hostname', 'meta_url.netloc', 'source']));

            return [
                'title' => (string) ($hit['title'] ?? $hit['source'] ?? 'Untitled result'),
                'page_url' => $pageUrl,
                'image_url' => $imageUrl,
                'thumbnail_url' => $this->pickUrl($hit, ['thumbnail.src', 'thumbnail.url']),
                'source_domain' => $sourceDomain,
                'snippet' => $this->pick($hit, ['description', 'snippet']),
                'width' => $this->normalizeInt($this->pick($hit, ['properties.width', 'width'])),
                'height' => $this->normalizeInt($this->pick($hit, ['properties.height', 'height'])),
                'score' => $this->normalizeFloat($this->pick($hit, ['score'])),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'raw_id' => $hit['id'] ?? null,
                    'page_fetched' => $hit['page_fetched'] ?? null,
                    'confidence' => $hit['confidence'] ?? null,
                ],
            ];
        }, array_values(array_filter($results, static fn (mixed $hit): bool => is_array($hit)))));
    }

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request('/res/v1/web/search', [
            'q' => $this->applySiteFilter($query),
            'count' => $query->limit,
        ]);

        $results = $payload['web']['results'] ?? [];

        if (! is_array($results)) {
            throw new RuntimeException('Unexpected Brave web payload.');
        }

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $pageUrl = $this->pick($hit, ['url']);

            return [
                'title' => (string) ($hit['title'] ?? 'Untitled result'),
                'page_url' => $pageUrl,
                'image_url' => $this->pick($hit, ['thumbnail.src', 'thumbnail.url', 'profile.img']),
                'thumbnail_url' => $this->pick($hit, ['thumbnail.src', 'thumbnail.url']),
                'source_domain' => $this->extractDomain($pageUrl),
                'snippet' => $this->pick($hit, ['description']),
                'score' => $this->normalizeFloat($this->pick($hit, ['score'])),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'family_friendly' => $hit['family_friendly'] ?? null,
                ],
            ];
        }, array_values(array_filter($results, static fn (mixed $hit): bool => is_array($hit)))));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        $this->assertHttpClientAvailable();

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://api.search.brave.com')
            ->acceptJson()
            ->withHeaders([
                'X-Subscription-Token' => (string) $this->definition->apiKey,
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->get($path, array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== ''));

        return $response->throw()->json();
    }
}
