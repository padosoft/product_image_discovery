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

    public function test_it_finds_a_code_in_a_url_slug_or_in_a_file_name_split_by_separators(): void
    {
        $code = '763435008IW2885';

        self::assertTrue(TextNormalizer::containsExactCode('https://fashionclinic.com/products/saint-laurent-caramel-cognac-le-5-a-7-bea-suede-tote-763435008iw2885', $code));
        self::assertTrue(TextNormalizer::containsExactCode('https://fashionclinic.com/cdn/shop/files/763435008IW.2885_06.jpg?v=1747414057', $code));
        self::assertTrue(TextNormalizer::containsExactCode('https://shop.example/p/bea-763435-008iw-2885.html', $code));
        // The same model in another color is a similar code, never this one.
        self::assertFalse(TextNormalizer::containsExactCode('https://shop.example/p/bea-763435008iw2884', $code));
        self::assertTrue(TextNormalizer::hasSimilarCodeMismatch('https://shop.example/p/bea-763435008iw2884', $code));
    }

    public function test_a_code_written_with_encoded_spaces_in_a_url_is_found_once_the_url_is_decoded(): void
    {
        $url = 'https://img.vitkac.com/uploads/product_thumb/BUTY%20J15WS0047%20P7821-904/up/1.png';
        $decoded = TextNormalizer::decodeUrls($url);

        // Encoded, "%" splits the path and "20" sticks to the code: "20J15WS0047".
        self::assertFalse(TextNormalizer::containsExactCode($url, 'J15WS0047'));
        self::assertSame('https://img.vitkac.com/uploads/product_thumb/BUTY J15WS0047 P7821-904/up/1.png', $decoded);
        self::assertTrue(TextNormalizer::containsExactCode($decoded, 'J15WS0047'));
        self::assertTrue(TextNormalizer::containsPhrase($decoded, 'J15WS0047 P7821'));
    }

    public function test_only_urls_are_decoded_and_a_url_that_is_not_utf8_once_decoded_is_kept(): void
    {
        self::assertSame(
            'Cotton 100%25, see https://shop.example/a b.jpg',
            TextNormalizer::decodeUrls('Cotton 100%25, see https://shop.example/a%20b.jpg'),
        );
        self::assertSame('https://shop.example/caf%E9.jpg', TextNormalizer::decodeUrls('https://shop.example/caf%E9.jpg'));
    }

    public function test_plain_words_of_a_url_slug_are_not_joined_into_a_code(): void
    {
        $url = 'https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr';

        self::assertFalse(TextNormalizer::containsExactCode($url, 'Air Force 1 07'));
        self::assertFalse(TextNormalizer::containsCodePrefix($url, 'Air Force 1 07'));
    }

    public function test_shop_names_of_brown_shades_and_adjacent_colors_are_read_only_on_request(): void
    {
        self::assertSame([], TextNormalizer::mentionedColors('Caramel Cognac suede tote'));
        self::assertSame(['brown'], TextNormalizer::mentionedColors('Caramel Cognac suede tote', true));
        self::assertSame('cognac', TextNormalizer::canonicalColor('Cognac'));
        self::assertSame('brown', TextNormalizer::canonicalColor('Cognac', true));
        self::assertTrue(TextNormalizer::colorsAreAdjacent('beige', ['brown']));
        self::assertTrue(TextNormalizer::colorsAreAdjacent('brown', ['beige', 'brown']));
        self::assertFalse(TextNormalizer::colorsAreAdjacent('beige', ['brown', 'black']));
        self::assertFalse(TextNormalizer::colorsAreAdjacent('black', ['brown']));
        self::assertFalse(TextNormalizer::colorsAreAdjacent('beige', []));
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
