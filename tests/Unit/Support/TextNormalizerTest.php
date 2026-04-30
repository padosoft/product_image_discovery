<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase
{
    public function test_it_matches_long_product_codes_embedded_as_prefixes(): void
    {
        self::assertTrue(TextNormalizer::containsCodePrefix(
            'Herno CAPE IN NYLON ULTRALIGHT PI002223D12017Z2157',
            'PI002223D',
        ));
    }

    public function test_it_does_not_prefix_match_short_codes(): void
    {
        self::assertFalse(TextNormalizer::containsCodePrefix('New Balance 550123', '550'));
    }

    public function test_it_treats_cammello_as_a_camel_beige_color_family(): void
    {
        self::assertSame('beige', TextNormalizer::canonicalColor('cammello'));
        self::assertContains('beige', TextNormalizer::mentionedColors('marrone cammello nylon ultralight'));
    }
}
