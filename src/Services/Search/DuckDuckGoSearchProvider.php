<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

/**
 * DuckDuckGo HTML lite scraper.
 *
 * Uses the no-API-key HTML endpoint at html.duckduckgo.com/html. Web search
 * only — DuckDuckGo image search is not exposed via a stable, documented
 * HTML endpoint, so `supportsImageSearch()` returns false and
 * SearchProviderManager skips this driver for image queries. The downstream
 * extraction pipeline can still harvest images from the returned page URLs.
 */
final class DuckDuckGoSearchProvider extends AbstractHttpSearchProvider
{
    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

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
        $html = $this->fetchHtml($this->buildQueryString($query));

        if ($html === '') {
            return new ProductImageSearchResultCollection();
        }

        $hits = $this->parseHtml($html);

        $limit = max(1, $query->limit);
        $hits = array_slice($hits, 0, $limit);

        return new ProductImageSearchResultCollection(array_map(function (array $hit): array {
            $pageUrl = $hit['url'];

            return [
                'title' => (string) $hit['title'],
                'page_url' => $pageUrl,
                'image_url' => null,
                'thumbnail_url' => null,
                'source_domain' => $this->extractDomain($pageUrl) ?? $hit['source_domain'],
                'snippet' => $hit['snippet'],
                'width' => null,
                'height' => null,
                'score' => null,
                'provider_metadata' => [
                    'provider' => $this->definition->code,
                ],
            ];
        }, $hits));
    }

    private function fetchHtml(string $query): string
    {
        $this->assertHttpClientAvailable();

        $response = \Illuminate\Support\Facades\Http::baseUrl($this->definition->baseUrl ?? 'https://html.duckduckgo.com')
            ->withHeaders([
                'User-Agent' => (string) ($this->definition->configValue('user_agent') ?? self::DEFAULT_USER_AGENT),
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => (string) ($this->definition->configValue('accept_language') ?? 'en-US,en;q=0.9'),
            ])
            ->timeout($this->definition->timeoutSeconds)
            ->asForm()
            ->post('/html/', ['q' => $query]);

        $response->throw();

        $body = (string) $response->body();

        return trim($body);
    }

    private function buildQueryString(ProductImageSearchQueryData $query): string
    {
        $base = $query->toSearchString();

        if ($query->site !== null && $query->site !== '') {
            $base = trim($base.' site:'.$query->site);
        }

        return $base;
    }

    /**
     * @return array<int, array{title: string, url: string, snippet: ?string, source_domain: ?string}>
     */
    private function parseHtml(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        $resultNodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " result ")]');

        if ($resultNodes === false || $resultNodes->length === 0) {
            return [];
        }

        $hits = [];

        foreach ($resultNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $linkNode = $this->firstChildWithClass($xpath, $node, 'result__a');

            if (! $linkNode instanceof DOMElement) {
                continue;
            }

            $title = trim($linkNode->textContent);
            $rawHref = trim((string) $linkNode->getAttribute('href'));
            $url = $this->normalizeResultUrl($rawHref);

            if ($title === '' || $url === null) {
                continue;
            }

            $snippetNode = $this->firstChildWithClass($xpath, $node, 'result__snippet');
            $snippet = $snippetNode instanceof DOMElement ? trim($snippetNode->textContent) : null;
            if ($snippet === '') {
                $snippet = null;
            }

            $sourceNode = $this->firstChildWithClass($xpath, $node, 'result__url');
            $sourceText = $sourceNode instanceof DOMElement ? trim($sourceNode->textContent) : '';
            $sourceDomain = $this->normalizeDomain($sourceText);

            $hits[] = [
                'title' => $title,
                'url' => $url,
                'snippet' => $snippet,
                'source_domain' => $sourceDomain,
            ];
        }

        return $hits;
    }

    private function firstChildWithClass(DOMXPath $xpath, DOMElement $node, string $className): ?DOMElement
    {
        $matches = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " '.$className.' ")]', $node);

        if ($matches === false || $matches->length === 0) {
            return null;
        }

        $first = $matches->item(0);

        return $first instanceof DOMElement ? $first : null;
    }

    private function normalizeResultUrl(string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        $parsed = parse_url($href);

        if (! is_array($parsed)) {
            return null;
        }

        $query = $parsed['query'] ?? null;

        if (is_string($query) && $query !== '') {
            parse_str($query, $params);

            foreach (['uddg', 'u', 'url'] as $key) {
                $candidate = $params[$key] ?? null;

                if (is_string($candidate) && $candidate !== '') {
                    $decoded = urldecode($candidate);

                    if (str_starts_with($decoded, 'http://') || str_starts_with($decoded, 'https://')) {
                        return $decoded;
                    }
                }
            }
        }

        if (isset($parsed['scheme'], $parsed['host']) && in_array($parsed['scheme'], ['http', 'https'], true)) {
            return $href;
        }

        return null;
    }
}
