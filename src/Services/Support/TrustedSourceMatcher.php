<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Support;

final class TrustedSourceMatcher
{
    /**
     * Pick the trusted source matching the candidate domain (exact or subdomain match).
     * When multiple sources match, the most specific (longest) domain wins.
     *
     * @param array<int, array<string, mixed>> $trustedSources
     * @return array<string, mixed>
     */
    public static function match(array $trustedSources, ?string $domainOrUrl): array
    {
        $candidateDomain = DomainNormalizer::normalizeDomain($domainOrUrl);

        if ($candidateDomain === null) {
            return [];
        }

        $best = [];
        $bestLength = 0;

        foreach ($trustedSources as $trustedSource) {
            if (($trustedSource['is_active'] ?? true) === false) {
                continue;
            }

            $trustedDomain = DomainNormalizer::normalizeDomain($trustedSource['domain'] ?? null);

            if ($trustedDomain === null) {
                continue;
            }

            if ($candidateDomain !== $trustedDomain && ! str_ends_with($candidateDomain, '.' . $trustedDomain)) {
                continue;
            }

            $length = strlen($trustedDomain);

            if ($length > $bestLength) {
                $best = $trustedSource;
                $bestLength = $length;
            }
        }

        return $best;
    }
}
