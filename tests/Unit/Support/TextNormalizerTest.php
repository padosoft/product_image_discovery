<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param list<string> $expected
     */
    #[DataProvider('colorCodesAfterCodeCases')]
    public function test_it_reads_color_codes_written_right_after_a_product_code(string $haystack, ?string $code, ?string $colorCode, array $expected): void
    {
        self::assertSame($expected, TextNormalizer::colorCodesAfterCode($haystack, $code, $colorCode));
    }

    /**
     * @return array<string, array{0:string, 1:?string, 2:?string, 3:list<string>}>
     */
    public static function colorCodesAfterCodeCases(): array
    {
        return [
            'dash' => ['Chloé shorts 26SSH01164-002', '26SSH01164', '001', ['002']],
            'space' => ['Chloé 26SSH01164 001 shorts', '26SSH01164', '001', ['001']],
            'attached' => ['Chloé shorts 26SSH01164001', '26SSH01164', '001', ['001']],
            'slash' => ['Chloé shorts 26SSH01164 / 001', '26SSH01164', '001', []],
            'url' => ['https://shop.example/p/26ssh01164-002.html', '26SSH01164', '001', ['002']],
            'both variants' => ['26SSH01164-001 26SSH01164-002', '26SSH01164', '001', ['001', '002']],
            'percentage' => ['Chloé shorts 26SSH01164 100% lana', '26SSH01164', '001', []],
            'spaced percentage' => ['Chloé shorts 26SSH01164 100 % lana', '26SSH01164', '001', []],
            'size' => ['Chloé shorts 26SSH01164 38 IT', '26SSH01164', '001', []],
            'year' => ['Chloé shorts 26SSH01164 2026', '26SSH01164', '001', []],
            'word' => ['Chloé 26SSH01164 TOP', '26SSH01164', '001', []],
            'longer suffix' => ['Herno PI002223D12017Z2157', 'PI002223D', '2157', []],
            'color name as color code' => ['Chloé shorts 26SSH01164 002', '26SSH01164', 'Beige', []],
            'two character color code' => ['Chloé shorts 26SSH01164 38', '26SSH01164', '07', []],
            'short product code' => ['Acme AB12 002', 'AB12', '001', []],
            'no color code' => ['Chloé shorts 26SSH01164 002', '26SSH01164', null, []],
        ];
    }
}
