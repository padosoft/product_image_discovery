<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Support;

final class TextNormalizer
{
    /**
     * @return array<string, list<string>>
     */
    public static function colorAliases(): array
    {
        return [
            'black' => ['black', 'nero', 'nera', 'noir', 'schwarz'],
            'white' => ['white', 'bianco', 'bianca', 'blanc', 'weiss'],
            'blue' => ['blue', 'blu', 'azzurro', 'azzurra', 'navy', 'bleu'],
            'red' => ['red', 'rosso', 'rossa', 'rouge', 'rot'],
            'green' => ['green', 'verde', 'vert', 'gruen'],
            'yellow' => ['yellow', 'giallo', 'gialla', 'jaune', 'gelb'],
            'brown' => ['brown', 'marrone', 'cuoio', 'brun'],
            'grey' => ['grey', 'gray', 'grigio', 'grigia', 'gris'],
            'beige' => ['beige', 'sabbia', 'sand', 'tan', 'camel', 'cammello', 'biscuit', 'light brown'],
            'pink' => ['pink', 'rosa', 'rose'],
            'purple' => ['purple', 'viola', 'lilla', 'violet'],
            'orange' => ['orange', 'arancio', 'arancione'],
            'silver' => ['silver', 'argento'],
            'gold' => ['gold', 'oro'],
        ];
    }

    /**
     * Shop names for brown shades, read only with the tolerate_adjacent_colors setting: without
     * the adjacent groups below they would turn today's neutral titles into color mismatches.
     *
     * @return array<string, list<string>>
     */
    public static function extendedColorAliases(): array
    {
        return [
            'brown' => ['caramel', 'cognac', 'tobacco', 'tabacco', 'chocolate', 'cioccolato', 'chestnut', 'castagna', 'hazelnut', 'nocciola', 'mocha', 'moka'],
        ];
    }

    /**
     * Colors that shops and ERPs name differently for the same shade: a "camel" (beige) item is
     * "caramel" or "brown" on a shop page.
     *
     * @return list<list<string>>
     */
    public static function adjacentColorGroups(): array
    {
        return [
            ['beige', 'brown'],
        ];
    }

    /**
     * True when every mentioned color is the expected one or in its adjacent group.
     *
     * @param list<string> $mentionedColors canonical colors
     */
    public static function colorsAreAdjacent(?string $expectedColor, array $mentionedColors): bool
    {
        if ($expectedColor === null || $mentionedColors === []) {
            return false;
        }

        foreach (self::adjacentColorGroups() as $group) {
            if (in_array($expectedColor, $group, true) && array_diff($mentionedColors, $group) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function aliases(bool $extended): array
    {
        if (! $extended) {
            return self::colorAliases();
        }

        $aliases = self::colorAliases();

        foreach (self::extendedColorAliases() as $canonical => $extra) {
            $aliases[$canonical] = array_values(array_unique(array_merge($aliases[$canonical] ?? [], $extra)));
        }

        return $aliases;
    }

    public static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }

    public static function normalizeText(?string $value): ?string
    {
        $value = self::nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = self::toAscii($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }

    public static function normalizeCode(?string $value): ?string
    {
        $value = self::nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = self::toAscii($value);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]/', '', $value) ?? $value;

        return $value === '' ? null : $value;
    }

    public static function normalizeEan(?string $value): ?string
    {
        $value = self::nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\D+/', '', $value) ?? '';

        return $value === '' ? null : $value;
    }

    public static function containsWord(string $haystack, ?string $needle): bool
    {
        $needle = self::normalizeText($needle);
        $haystack = self::normalizeText($haystack);

        if ($needle === null || $haystack === null) {
            return false;
        }

        return preg_match('/(?:^| )' . preg_quote($needle, '/') . '(?: |$)/', $haystack) === 1;
    }

    public static function containsPhrase(string $haystack, ?string $phrase): bool
    {
        $phrase = self::normalizeText($phrase);
        $haystack = self::normalizeText($haystack);

        if ($phrase === null || $haystack === null || ! str_contains($phrase, ' ')) {
            return false;
        }

        return preg_match('/(?:^| )' . preg_quote($phrase, '/') . '(?: |$)/', $haystack) === 1;
    }

    public static function containsExactCode(string $haystack, ?string $code): bool
    {
        $code = self::normalizeCode($code);

        if ($code === null) {
            return false;
        }

        return in_array($code, self::extractCodeTokens($haystack), true);
    }

    public static function containsCodePrefix(string $haystack, ?string $code): bool
    {
        $code = self::normalizeCode($code);

        if ($code === null || strlen($code) < 6) {
            return false;
        }

        foreach (self::extractCodeTokens($haystack) as $token) {
            if ($token === $code || str_starts_with($token, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function extractCodeTokens(string $text): array
    {
        $text = self::toAscii($text);
        preg_match_all('/[A-Za-z0-9][A-Za-z0-9._\-\/]*[A-Za-z0-9]|[A-Za-z0-9]/', $text, $matches);

        $tokens = [];

        foreach ($matches[0] ?? [] as $match) {
            // A URL path is a single match ("shop.com/products/bag-763435008iw2885"): its words
            // are tokens too, so a code in a slug or in a file name is found.
            $parts = str_contains($match, '/') ? [$match, ...self::urlPathParts($match)] : [$match];

            foreach ($parts as $part) {
                $token = self::normalizeCode($part);

                if ($token !== null && strlen($token) >= 2) {
                    $tokens[$token] = $token;
                }
            }
        }

        return array_values($tokens);
    }

    /**
     * The words of each path segment and runs of up to four adjacent words with a digit each, so
     * a code the shop split with separators ("763435-008iw-2885", "763435008IW.2885_06.jpg") is
     * rebuilt while a slug of plain words ("air-force-1-07") is not read as one code.
     *
     * @return list<string>
     */
    private static function urlPathParts(string $path): array
    {
        $parts = [];
        $hasDigit = static fn (string $word): bool => preg_match('/\d/', $word) === 1;

        foreach (explode('/', $path) as $segment) {
            $words = preg_split('/[._\-]+/', $segment, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $count = count($words);

            for ($start = 0; $start < $count; $start++) {
                $parts[] = $run = $words[$start];

                for ($end = $start + 1; $end < min($count, $start + 4) && $hasDigit($words[$start]) && $hasDigit($words[$end]); $end++) {
                    $parts[] = $run .= $words[$end];
                }
            }
        }

        return $parts;
    }

    public static function hasSimilarCodeMismatch(string $haystack, ?string $expected): bool
    {
        $expected = self::normalizeCode($expected);

        if ($expected === null || strlen($expected) < 4) {
            return false;
        }

        foreach (self::extractCodeTokens($haystack) as $token) {
            if ($token === $expected) {
                return false;
            }

            if (abs(strlen($token) - strlen($expected)) > 1) {
                continue;
            }

            if (substr($token, 0, 3) !== substr($expected, 0, 3)) {
                continue;
            }

            if (levenshtein($token, $expected) <= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Color codes written right after a product code, the way retailers spell color variants
     * ("26SSH01164-002", "26SSH01164 002", ".../26ssh01164-002.html"). Only product codes of at
     * least six characters are looked up, and a suffix counts only when it has the shape of the
     * expected color code (same length, digits and letters in the same places, at least three
     * characters with a digit), so sizes, years and percentages ("38", "2026", "100%") are ignored.
     *
     * @return list<string> normalized suffixes found after the code, e.g. ["002"]
     */
    public static function colorCodesAfterCode(string $haystack, ?string $code, ?string $colorCode): array
    {
        $code = self::normalizeCode($code);
        $colorCode = self::normalizeCode($colorCode);

        if ($code === null || $colorCode === null || strlen($code) < 6 || strlen($colorCode) < 3 || preg_match('/\d/', $colorCode) !== 1) {
            return [];
        }

        $shape = static fn (string $value): string => (string) preg_replace(['/[A-Z]/', '/\d/'], ['A', '9'], $value);
        $pattern = '/(?<![A-Z0-9])' . preg_quote($code, '/') . '[\s\-_\/.]?([A-Z0-9]{' . strlen($colorCode) . '})(?![A-Z0-9%]|\s*%|\s*(?:CM|MM)\b)/';

        preg_match_all($pattern, strtoupper(self::toAscii($haystack)), $matches);

        $suffixes = [];

        foreach ($matches[1] ?? [] as $suffix) {
            if ($shape($suffix) === $shape($colorCode)) {
                $suffixes[$suffix] = $suffix;
            }
        }

        return array_values($suffixes);
    }

    public static function canonicalColor(?string $value, bool $extended = false): ?string
    {
        $value = self::normalizeText($value);

        if ($value === null) {
            return null;
        }

        foreach (self::aliases($extended) as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/(?:^| )' . preg_quote($alias, '/') . '(?: |$)/', $value) === 1) {
                    return $canonical;
                }
            }
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public static function mentionedColors(string $text, bool $extended = false): array
    {
        $text = self::normalizeText($text);

        if ($text === null) {
            return [];
        }

        $colors = [];

        foreach (self::aliases($extended) as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/(?:^| )' . preg_quote($alias, '/') . '(?: |$)/', $text) === 1) {
                    $colors[$canonical] = $canonical;
                    break;
                }
            }
        }

        return array_values($colors);
    }

    public static function flattenStrings(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return '';
        }

        $strings = [];

        array_walk_recursive($value, static function (mixed $item) use (&$strings): void {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        });

        return implode(' ', $strings);
    }

    /**
     * Percent-decodes every http(s) URL in the text. Some shops write the code in the file path
     * with spaces ("BUTY%20J15WS0047%20P7821-904"): left encoded, "%" splits the token and "20"
     * sticks to the code ("20J15WS0047"), so neither the code nor the words around it are found.
     * A URL that does not decode to valid UTF-8 is kept as it is.
     */
    public static function decodeUrls(string $text): string
    {
        return preg_replace_callback('~https?://\S+~i', static function (array $match): string {
            $decoded = rawurldecode($match[0]);

            return mb_check_encoding($decoded, 'UTF-8') ? $decoded : $match[0];
        }, $text) ?? $text;
    }

    private static function toAscii(string $value): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $value;
    }
}
