<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use LogicException;
use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

final class BraveSearchProvider implements ProductImageSearchProviderInterface
{
    public function __construct(private readonly SearchProviderDefinition $definition)
    {
    }

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
            $imageUrl = $this->pick($hit, [
                'properties.url',
                'image.url',
                'image.src',
                'url',
            ]);

            return [
                'title' => (string) ($hit['title'] ?? $hit['source'] ?? 'Untitled result'),
                'page_url' => $this->pick($hit, ['page_fetched', 'page_url', 'source', 'url']),
                'image_url' => $imageUrl,
                'thumbnail_url' => $this->pick($hit, ['thumbnail.src', 'thumbnail.url']),
                'source_domain' => $this->extractDomain($this->pick($hit, ['source', 'page_fetched', 'page_url'])),
                'snippet' => $this->pick($hit, ['description', 'snippet']),
                'width' => $this->normalizeInt($this->pick($hit, ['properties.width', 'width'])),
                'height' => $this->normalizeInt($this->pick($hit, ['properties.height', 'height'])),
                'score' => $this->normalizeFloat($this->pick($hit, ['score'])),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'raw_id' => $hit['id'] ?? null,
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

    public function supportsImageSearch(): bool
    {
        return true;
    }

    public function supportsSiteFilter(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            throw new LogicException('Illuminate HTTP client is required to use BraveSearchProvider.');
        }

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://api.search.brave.com')
            ->acceptJson()
            ->withHeaders([
                'X-Subscription-Token' => (string) $this->definition->apiKey,
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->get($path, array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== ''));

        return $response->throw()->json();
    }

    private function applySiteFilter(ProductImageSearchQueryData $query): string
    {
        $searchString = $query->toSearchString();

        if ($query->site === null || $query->site === '') {
            return $searchString;
        }

        return trim($searchString.' site:'.$query->site);
    }

    private function pick(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->dotGet($payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function dotGet(array $payload, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $payload;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function extractDomain(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
