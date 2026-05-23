<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

final class FirecrawlSearchProvider extends AbstractHttpSearchProvider
{
    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request([
            'query' => $query->toSearchString(),
            'sources' => [
                ['type' => 'images'],
            ],
            'limit' => $this->clampLimit($query->limit),
            'includeDomains' => $this->buildIncludeDomains($query),
        ]);

        $images = $this->extractDataArray($payload, 'images');

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $pageUrl = is_string($hit['url'] ?? null) ? $hit['url'] : null;
            $imageUrl = is_string($hit['imageUrl'] ?? null) ? $hit['imageUrl'] : null;

            return [
                'title' => (string) ($hit['title'] ?? 'Firecrawl image result'),
                'page_url' => $pageUrl,
                'image_url' => $imageUrl,
                'thumbnail_url' => null,
                'source_domain' => $this->extractDomain($pageUrl) ?? $this->extractDomain($imageUrl),
                'snippet' => null,
                'width' => $this->normalizeInt($hit['imageWidth'] ?? null),
                'height' => $this->normalizeInt($hit['imageHeight'] ?? null),
                'score' => null,
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'position' => $hit['position'] ?? null,
                ],
            ];
        }, $images));
    }

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request([
            'query' => $query->toSearchString(),
            'sources' => [
                ['type' => 'web'],
            ],
            'limit' => $this->clampLimit($query->limit),
            'includeDomains' => $this->buildIncludeDomains($query),
        ]);

        $web = $this->extractDataArray($payload, 'web');

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
                'score' => null,
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'metadata' => is_array($hit['metadata'] ?? null) ? $hit['metadata'] : null,
                ],
            ];
        }, $web));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(array $body): array
    {
        $this->assertHttpClientAvailable();

        $body = $this->pruneEmpty($body);

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://api.firecrawl.dev')
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.((string) $this->definition->apiKey),
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->post('/v2/search', $body);

        $payload = $response->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Unexpected Firecrawl payload: not a JSON object.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractDataArray(array $payload, string $key): array
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $entry): bool => is_array($entry)));
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min(100, $limit));
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
