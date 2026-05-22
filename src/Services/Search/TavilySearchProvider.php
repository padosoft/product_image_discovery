<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use RuntimeException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

final class TavilySearchProvider extends AbstractHttpSearchProvider
{
    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request([
            'query' => $query->toSearchString(),
            'search_depth' => (string) ($this->definition->configValue('search_depth') ?? 'basic'),
            'include_images' => true,
            'include_image_descriptions' => true,
            'max_results' => $this->clampMaxResults($query->limit),
            'include_domains' => $this->buildIncludeDomains($query),
        ]);

        $images = $this->normalizeImagesPayload($payload['images'] ?? []);

        if ($images === []) {
            return new ProductImageSearchResultCollection();
        }

        $results = $this->normalizeResultsPayload($payload['results'] ?? []);
        $pageUrlByDomain = $this->indexResultsByDomain($results);

        return new ProductImageSearchResultCollection(array_map(function (array $image) use ($pageUrlByDomain): array {
            $imageUrl = $image['url'];
            $domain = $this->extractDomain($imageUrl);
            $associated = $domain !== null ? ($pageUrlByDomain[$domain] ?? null) : null;
            $pageUrl = $associated['url'] ?? $imageUrl;
            $title = (string) ($associated['title'] ?? $image['description'] ?? 'Tavily image result');

            return [
                'title' => $title,
                'page_url' => $pageUrl,
                'image_url' => $imageUrl,
                'thumbnail_url' => null,
                'source_domain' => $this->extractDomain($pageUrl) ?? $domain,
                'snippet' => $image['description'] ?? ($associated['content'] ?? null),
                'width' => null,
                'height' => null,
                'score' => $this->normalizeFloat($associated['score'] ?? null),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                    'image_description' => $image['description'] ?? null,
                ],
            ];
        }, $images));
    }

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection
    {
        $payload = $this->request([
            'query' => $query->toSearchString(),
            'search_depth' => (string) ($this->definition->configValue('search_depth') ?? 'basic'),
            'include_images' => false,
            'max_results' => $this->clampMaxResults($query->limit),
            'include_domains' => $this->buildIncludeDomains($query),
        ]);

        $results = $this->normalizeResultsPayload($payload['results'] ?? []);

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $pageUrl = $hit['url'] ?? null;

            return [
                'title' => (string) ($hit['title'] ?? 'Untitled result'),
                'page_url' => $pageUrl,
                'image_url' => null,
                'thumbnail_url' => null,
                'source_domain' => $this->extractDomain($pageUrl),
                'snippet' => $hit['content'] ?? null,
                'width' => null,
                'height' => null,
                'score' => $this->normalizeFloat($hit['score'] ?? null),
                'provider_metadata' => [
                    'provider' => $this->definition->code,
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

        $body = array_filter(
            $body,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://api.tavily.com')
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.((string) $this->definition->apiKey),
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->post('/search', $body);

        $payload = $response->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Unexpected Tavily payload: not a JSON object.');
        }

        return $payload;
    }

    /**
     * @param  mixed  $payload
     * @return array<int, array{url: string, description: ?string}>
     */
    private function normalizeImagesPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $normalized = [];

        foreach ($payload as $entry) {
            if (is_string($entry)) {
                $url = trim($entry);

                if ($url === '' || ! (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                    continue;
                }

                $normalized[] = ['url' => $url, 'description' => null];
                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $url = $this->pickUrl($entry, ['url', 'image_url']);

            if ($url === null) {
                continue;
            }

            $description = $entry['description'] ?? null;
            $normalized[] = [
                'url' => $url,
                'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
            ];
        }

        return $normalized;
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

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, array{url: ?string, title: ?string, content: ?string, score: mixed}>
     */
    private function indexResultsByDomain(array $results): array
    {
        $index = [];

        foreach ($results as $hit) {
            $url = is_string($hit['url'] ?? null) ? $hit['url'] : null;
            $domain = $this->extractDomain($url);

            if ($domain === null || isset($index[$domain])) {
                continue;
            }

            $index[$domain] = [
                'url' => $url,
                'title' => is_string($hit['title'] ?? null) ? $hit['title'] : null,
                'content' => is_string($hit['content'] ?? null) ? $hit['content'] : null,
                'score' => $hit['score'] ?? null,
            ];
        }

        return $index;
    }

    private function clampMaxResults(int $limit): int
    {
        return max(1, min(20, $limit));
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
}
