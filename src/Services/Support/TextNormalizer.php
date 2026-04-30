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
            'beige' => ['beige', 'sabbia', 'sand', 'tan'],
            'pink' => ['pink', 'rosa', 'rose'],
            'purple' => ['purple', 'viola', 'lilla', 'violet'],
            'orange' => ['orange', 'arancio', 'arancione'],
            'silver' => ['silver', 'argento'],
            'gold' => ['gold', 'oro'],
        ];
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
            $token = self::normalizeCode($match);

            if ($token !== null && strlen($token) >= 2) {
                $tokens[$token] = $token;
            }
        }

        return array_values($tokens);
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

    public static function canonicalColor(?string $value): ?string
    {
        $value = self::normalizeText($value);

        if ($value === null) {
            return null;
        }

        foreach (self::colorAliases() as $canonical => $aliases) {
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
    public static function mentionedColors(string $text): array
    {
        $text = self::normalizeText($text);

        if ($text === null) {
            return [];
        }

        $colors = [];

        foreach (self::colorAliases() as $canonical => $aliases) {
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
