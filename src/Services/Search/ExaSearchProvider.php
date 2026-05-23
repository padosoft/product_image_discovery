<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

final class ExaSearchProvider extends AbstractHttpSearchProvider
{
    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $imageLinks = max(1, min(20, (int) ($this->definition->configValue('image_links_per_result') ?? 5)));

        $payload = $this->request([
            'query' => $query->toSearchString(),
            'type' => (string) ($this->definition->configValue('search_type') ?? 'auto'),
            'numResults' => $this->clampNumResults($query->limit),
            'includeDomains' => $this->buildIncludeDomains($query),
            'contents' => [
                'extras' => [
                    'imageLinks' => $imageLinks,
                ],
            ],
        ]);

        $results = $this->normalizeResultsPayload($payload['results'] ?? []);

        if ($results === []) {
            return new ProductImageSearchResultCollection();
        }

        $emitted = [];

        foreach ($results as $hit) {
            $pageUrl = is_string($hit['url'] ?? null) ? $hit['url'] : null;
            $title = (string) ($hit['title'] ?? 'Exa image result');
            $sourceDomain = $this->extractDomain($pageUrl);
            $score = $this->normalizeFloat($hit['score'] ?? null);

            $imageUrls = $this->collectImageLinks($hit);

            foreach ($imageUrls as $imageUrl) {
                $emitted[] = [
                    'title' => $title,
                    'page_url' => $pageUrl ?? $imageUrl,
                    'image_url' => $imageUrl,
                    'thumbnail_url' => null,
                    'source_domain' => $sourceDomain ?? $this->extractDomain($imageUrl),
                    'snippet' => is_string($hit['text'] ?? null) ? $hit['text'] : null,
                    'width' => null,
                    'height' => null,
                    'score' => $score,
                    'provider_metadata' => [
                        'provider' => $this->definition->code,
                        'exa_id' => $hit['id'] ?? null,
                        'published_date' => $hit['publishedDate'] ?? null,
                    ],
                ];
            }
        }

        return new ProductImageSearchResultCollection($emitted);
    }

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request([
            'query' => $query->toSearchString(),
            'type' => (string) ($this->definition->configValue('search_type') ?? 'auto'),
            'numResults' => $this->clampNumResults($query->limit),
            'includeDomains' => $this->buildIncludeDomains($query),
        ]);

        $results = $this->normalizeResultsPayload($payload['results'] ?? []);

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $pageUrl = is_string($hit['url'] ?? null) ? $hit['url'] : null;

            return [
                'title' => (string) ($hit['title'] ?? 'Untitled result'),
                'page_url' => $pageUrl,
                'image_url' => is_string($hit['image'] ?? null) ? $hit['image'] : null,
                'thumbnail_url' => null,
                'source_domain' => $this->extractDomain($pageUrl),
                'snippet' => is_string($hit['text'] ?? null) ? $hit['text'] : null,
                'width' => null,
                'height' => null,
                'score' => $this->normalizeFloat($hit['score'] ?? null),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'exa_id' => $hit['id'] ?? null,
                    'published_date' => $hit['publishedDate'] ?? null,
                ],
            ];
        }, $results));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(array $body): array
    {
        $this->assertHttpClientAvailable();

        $body = $this->pruneEmpty($body);

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://api.exa.ai')
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => (string) $this->definition->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->post('/search', $body);

        $payload = $response->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Unexpected Exa payload: not a JSON object.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array<int, string>
     */
    private function collectImageLinks(array $hit): array
    {
        $candidates = [];

        $extras = $hit['extras'] ?? null;

        if (is_array($extras)) {
            $imageLinks = $extras['imageLinks'] ?? null;

            if (is_array($imageLinks)) {
                foreach ($imageLinks as $link) {
                    if (is_string($link) && (str_starts_with($link, 'http://') || str_starts_with($link, 'https://'))) {
                        $candidates[] = $link;
                    }
                }
            }
        }

        $primaryImage = $hit['image'] ?? null;

        if (is_string($primaryImage) && (str_starts_with($primaryImage, 'http://') || str_starts_with($primaryImage, 'https://'))) {
            array_unshift($candidates, $primaryImage);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  mixed  $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResultsPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return array_values(array_filter($payload, static fn (mixed $entry): bool => is_array($entry)));
    }

    private function clampNumResults(int $limit): int
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

            $cleaned[$key] = is_array($value) ? $this->pruneEmpty($value) : $value;

            if ($cleaned[$key] === []) {
                unset($cleaned[$key]);
            }
        }

        return $cleaned;
    }
}
