<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

/**
 * WebSearchAPI.ai provider.
 *
 * The /ai-search endpoint returns Google-backed organic web results with
 * optional content extraction; the documented payload does not include a
 * dedicated image array. We expose this driver as web-search only and let
 * the downstream extraction pipeline harvest images from the returned
 * pages. `supportsImageSearch()` therefore returns false, so
 * SearchProviderManager skips this driver for image queries automatically.
 */
final class WebSearchApiSearchProvider extends AbstractHttpSearchProvider
{
    public function supportsImageSearch(): bool
    {
        return false;
    }

    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        return new ProductImageSearchResultCollection();
    }

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request([
            'query' => $query->toSearchString(),
            'maxResults' => $this->clampMaxResults($query->limit),
            'includeContent' => (bool) ($this->definition->configValue('include_content') ?? false),
            'country' => $this->definition->configValue('country'),
            'language' => $this->definition->configValue('language'),
            'includeDomains' => $this->buildIncludeDomains($query),
        ]);

        $organic = $payload['organic'] ?? [];

        if (! is_array($organic)) {
            return new ProductImageSearchResultCollection();
        }

        $organic = array_values(array_filter($organic, static fn (mixed $entry): bool => is_array($entry)));

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $pageUrl = is_string($hit['url'] ?? null) ? $hit['url'] : null;

            return [
                'title' => (string) ($hit['title'] ?? 'Untitled result'),
                'page_url' => $pageUrl,
                'image_url' => null,
                'thumbnail_url' => null,
                'source_domain' => $this->extractDomain($pageUrl),
                'snippet' => is_string($hit['description'] ?? null) ? $hit['description'] : null,
                'width' => null,
                'height' => null,
                'score' => $this->normalizeFloat($hit['score'] ?? null),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'position' => $hit['position'] ?? null,
                    'content_preview' => is_string($hit['content'] ?? null) ? $hit['content'] : null,
                ],
            ];
        }, $organic));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(array $body): array
    {
        $this->assertHttpClientAvailable();

        $body = $this->pruneEmpty($body);

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://api.websearchapi.ai')
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.((string) $this->definition->apiKey),
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->post('/ai-search', $body);

        $payload = $response->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Unexpected WebSearchAPI payload: not a JSON object.');
        }

        return $payload;
    }

    private function clampMaxResults(int $limit): int
    {
        return max(1, min(50, $limit));
    }

    /**
     * @return array<int, string>|null
     */
    private function buildIncludeDomains(ProductImageSearchQueryData $query): ?array
    {
        if ($query->site === null || $query->site === '') {
            return null;
        }

        return [$query->site];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pruneEmpty(array $payload): array
    {
        $cleaned = [];

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }
}
