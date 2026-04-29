<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Services\Quality\ImageQualityAnalyzer;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 4) . '/src/Services/Quality/ImageQualityAnalyzer.php';

final class ImageQualityAnalyzerTest extends TestCase
{
    public function test_low_resolution_image_fails(): void
    {
        $result = (new ImageQualityAnalyzer())->analyze([
            'width' => 300,
            'height' => 300,
            'mime_type' => 'image/jpeg',
            'file_size' => 100000,
            'url' => 'https://cdn.example/product.jpg',
        ]);

        self::assertFalse($result['passed']);
        self::assertContains('LOW_RESOLUTION', $result['issues']);
    }

    public function test_invalid_mime_type_fails(): void
    {
        $result = (new ImageQualityAnalyzer())->analyze([
            'width' => 1000,
            'height' => 1000,
            'mime_type' => 'image/svg+xml',
            'file_size' => 100000,
            'url' => 'https://cdn.example/product.svg',
        ]);

        self::assertFalse($result['passed']);
        self::assertContains('INVALID_MIME_TYPE', $result['issues']);
    }

    public function test_placeholder_image_fails_with_issue(): void
    {
        $result = (new ImageQualityAnalyzer())->analyze([
            'width' => 1000,
            'height' => 1000,
            'mime_type' => 'image/jpeg',
            'file_size' => 100000,
            'url' => 'https://cdn.example/no-image-placeholder.jpg',
        ]);

        self::assertFalse($result['passed']);
        self::assertContains('PLACEHOLDER_IMAGE', $result['issues']);
    }

    public function test_valid_image_passes(): void
    {
        $result = (new ImageQualityAnalyzer())->analyze([
            'width' => 1200,
            'height' => 1500,
            'mime_type' => 'image/jpeg',
            'file_size' => 450000,
            'url' => 'https://cdn.example/product.jpg',
        ]);

        self::assertTrue($result['passed']);
        self::assertSame([], $result['issues']);
    }
}
