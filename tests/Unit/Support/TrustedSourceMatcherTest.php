<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Padosoft\ProductImageDiscovery\Services\Support\TrustedSourceMatcher;
use PHPUnit\Framework\TestCase;

final class TrustedSourceMatcherTest extends TestCase
{
    public function test_it_matches_exact_domain(): void
    {
        $source = ['domain' => 'brand.example.test', 'trust_score' => 92];

        self::assertSame($source, TrustedSourceMatcher::match([$source], 'brand.example.test'));
    }

    public function test_it_matches_subdomain_with_suffix(): void
    {
        $source = ['domain' => 'brand.example.test', 'trust_score' => 92];

        self::assertSame($source, TrustedSourceMatcher::match([$source], 'https://static.brand.example.test/p/1.jpg'));
    }

    public function test_it_prefers_most_specific_domain(): void
    {
        $generic = ['domain' => 'example.test', 'trust_score' => 50];
        $specific = ['domain' => 'brand.example.test', 'trust_score' => 92];

        self::assertSame($specific, TrustedSourceMatcher::match([$generic, $specific], 'shop.brand.example.test'));
    }

    public function test_it_ignores_inactive_sources(): void
    {
        $source = ['domain' => 'brand.example.test', 'trust_score' => 92, 'is_active' => false];

        self::assertSame([], TrustedSourceMatcher::match([$source], 'brand.example.test'));
    }

    public function test_it_does_not_match_unrelated_or_lookalike_domains(): void
    {
        $source = ['domain' => 'brand.example.test', 'trust_score' => 92];

        self::assertSame([], TrustedSourceMatcher::match([$source], 'other.example.test'));
        self::assertSame([], TrustedSourceMatcher::match([$source], 'evilbrand.example.test.attacker.io'));
    }

    public function test_it_returns_empty_for_missing_domain(): void
    {
        self::assertSame([], TrustedSourceMatcher::match([['domain' => 'brand.example.test']], null));
        self::assertSame([], TrustedSourceMatcher::match([], 'brand.example.test'));
    }
}
