<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Support;

final class DomainNormalizer
{
    public static function normalizeDomain(?string $urlOrDomain, bool $stripWww = true): ?string
    {
        $urlOrDomain = TextNormalizer::nullableString($urlOrDomain);

        if ($urlOrDomain === null || preg_match('/\s/', $urlOrDomain) === 1) {
            return null;
        }

        $candidate = $urlOrDomain;

        if (! preg_match('#^[a-z][a-z0-9+\-.]*://#i', $candidate)) {
            $candidate = 'https://' . ltrim($candidate, '/');
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        if ($stripWww && str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host === '' ? null : $host;
    }

    public static function rootDomain(?string $urlOrDomain): ?string
    {
        $domain = self::normalizeDomain($urlOrDomain);

        if ($domain === null) {
            return null;
        }

        $parts = explode('.', $domain);
        $count = count($parts);

        if ($count <= 2) {
            return $domain;
        }

        $compoundSuffixes = ['co.uk', 'com.au', 'com.br', 'com.tr', 'co.jp'];
        $lastTwo = implode('.', array_slice($parts, -2));
        $lastThree = implode('.', array_slice($parts, -3));

        foreach ($compoundSuffixes as $suffix) {
            if (str_ends_with($lastThree, $suffix)) {
                return $lastThree;
            }
        }

        return $lastTwo;
    }

    public static function normalizeUrl(?string $url, ?string $baseUrl = null): ?string
    {
        $url = TextNormalizer::nullableString($url);

        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, 'data:') || str_starts_with($url, 'javascript:')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl ?? 'https://example.com', PHP_URL_SCHEME) ?: 'https';
            $url = $scheme . ':' . $url;
        } elseif (! preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
            if ($baseUrl === null) {
                return null;
            }

            $url = self::resolveRelativeUrl($url, $baseUrl);
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = self::removeDotSegments($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    public static function isValidUrl(?string $url): bool
    {
        return self::normalizeUrl($url) !== null;
    }

    private static function resolveRelativeUrl(string $relative, string $baseUrl): string
    {
        $base = parse_url($baseUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return $relative;
        }

        $schemeHost = strtolower((string) $base['scheme']) . '://' . strtolower((string) $base['host']);

        if (str_starts_with($relative, '/')) {
            return $schemeHost . $relative;
        }

        $basePath = $base['path'] ?? '/';
        $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?? '/';

        return $schemeHost . $directory . $relative;
    }

    private static function removeDotSegments(string $path): string
    {
        $input = explode('/', $path);
        $output = [];

        foreach ($input as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($output);
                continue;
            }

            $output[] = $segment;
        }

        return '/' . implode('/', $output);
    }
}
