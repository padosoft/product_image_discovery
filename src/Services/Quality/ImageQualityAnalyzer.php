<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Quality;

use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

final class ImageQualityAnalyzer
{
    /**
     * @param string|array<string, mixed> $image
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function analyze(string|array $image, array $settings = []): array
    {
        $metadata = is_array($image) ? $image : ['path' => $image];
        $path = TextNormalizer::nullableString($metadata['path'] ?? null);
        $url = TextNormalizer::nullableString($metadata['url'] ?? $path);
        $width = isset($metadata['width']) ? (int) $metadata['width'] : null;
        $height = isset($metadata['height']) ? (int) $metadata['height'] : null;
        $mimeType = TextNormalizer::nullableString($metadata['mime_type'] ?? $metadata['mimeType'] ?? null);
        $fileSize = isset($metadata['file_size']) ? (int) $metadata['file_size'] : (isset($metadata['fileSize']) ? (int) $metadata['fileSize'] : null);

        if ($path !== null && is_file($path)) {
            $fileSize ??= filesize($path) ?: null;
            $info = @getimagesize($path);

            if (is_array($info)) {
                $width ??= (int) ($info[0] ?? 0);
                $height ??= (int) ($info[1] ?? 0);
                $mimeType ??= TextNormalizer::nullableString($info['mime'] ?? null);
            }

            if ($mimeType === null && function_exists('mime_content_type')) {
                $mimeType = TextNormalizer::nullableString(@mime_content_type($path) ?: null);
            }
        }

        $issues = [];
        $score = 100;
        $minWidth = (int) ($settings['min_width'] ?? 500);
        $minHeight = (int) ($settings['min_height'] ?? 500);
        $minArea = (int) ($settings['min_area'] ?? 250000);
        $maxFileSize = (int) ($settings['max_file_size'] ?? 8000000);
        $minAspectRatio = (float) ($settings['min_aspect_ratio'] ?? 0.45);
        $maxAspectRatio = (float) ($settings['max_aspect_ratio'] ?? 2.40);
        $allowedMimeTypes = $settings['allowed_mime_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];

        if (! is_array($allowedMimeTypes)) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        }

        if ($mimeType === null || ! in_array(strtolower($mimeType), array_map('strtolower', $allowedMimeTypes), true)) {
            $issues[] = 'INVALID_MIME_TYPE';
            $score -= 40;
        }

        if ($width === null || $height === null) {
            $issues[] = 'MISSING_DIMENSIONS';
            $score -= 25;
        } else {
            if ($width < $minWidth || $height < $minHeight || ($width * $height) < $minArea) {
                $issues[] = 'LOW_RESOLUTION';
                $score -= 45;
            }

            $aspectRatio = $height === 0 ? 0.0 : $width / $height;

            if ($aspectRatio < $minAspectRatio || $aspectRatio > $maxAspectRatio) {
                $issues[] = 'BAD_ASPECT_RATIO';
                $score -= 25;
            }
        }

        if ($fileSize !== null && $fileSize > $maxFileSize) {
            $issues[] = 'FILE_TOO_LARGE';
            $score -= 10;
        }

        if ($this->looksLikePlaceholder($url)) {
            $issues[] = 'PLACEHOLDER_IMAGE';
            $score -= 60;
        }

        foreach ([
            'is_blurry' => ['BLURRY_IMAGE', 35],
            'has_watermark' => ['WATERMARK_DETECTED', 45],
            'has_text_overlay' => ['TEXT_OVERLAY_DETECTED', 30],
        ] as $flag => [$issue, $penalty]) {
            if (($metadata[$flag] ?? false) === true) {
                $issues[] = $issue;
                $score -= $penalty;
            }
        }

        $score = max(0, min(100, $score));
        $minQualityScore = (int) ($settings['min_quality_score'] ?? 70);

        return [
            'passed' => $score >= $minQualityScore && ! in_array('INVALID_MIME_TYPE', $issues, true) && ! in_array('PLACEHOLDER_IMAGE', $issues, true),
            'quality_score' => $score,
            'issues' => array_values(array_unique($issues)),
            'width' => $width,
            'height' => $height,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'aspect_ratio' => $width !== null && $height !== null && $height > 0 ? round($width / $height, 4) : null,
        ];
    }

    private function looksLikePlaceholder(?string $url): bool
    {
        $url = TextNormalizer::normalizeText($url);

        if ($url === null) {
            return false;
        }

        foreach (['placeholder', 'no image', 'noimage', 'image coming soon', 'spacer', 'transparent', 'blank', 'missing image'] as $needle) {
            if (str_contains($url, $needle)) {
                return true;
            }
        }

        return false;
    }
}
