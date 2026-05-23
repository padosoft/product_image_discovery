<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use LogicException;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\SearchProviderDefinition;

abstract class AbstractHttpSearchProvider implements ProductImageSearchProviderInterface
{
    public function __construct(protected readonly SearchProviderDefinition $definition)
    {
    }

    public function supportsImageSearch(): bool
    {
        return true;
    }

    public function supportsSiteFilter(): bool
    {
        return true;
    }

    protected function applySiteFilter(ProductImageSearchQueryData $query): string
    {
        $searchString = $query->toSearchString();

        if ($query->site === null || $query->site === '') {
            return $searchString;
        }

        return trim($searchString.' site:'.$query->site);
    }

    protected function assertHttpClientAvailable(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            throw new LogicException(sprintf(
                'Illuminate HTTP client is required to use %s.',
                static::class,
            ));
        }
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @param  array<int, string>  $paths
     */
    protected function pick(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->dotGet($payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @param  array<int, string>  $paths
     */
    protected function pickUrl(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $this->pick($payload, [$path]);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $value = trim($value);

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    protected function dotGet(array $payload, string $path): mixed
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

    protected function extractDomain(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    protected function normalizeDomain(mixed $domain): ?string
    {
        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        $domain = trim($domain);

        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $this->extractDomain($domain);
        }

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain) === 1 ? strtolower($domain) : null;
    }

    protected function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function normalizeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
