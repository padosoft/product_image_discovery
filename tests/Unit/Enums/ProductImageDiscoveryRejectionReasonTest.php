<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;

class ProductImageDiscoveryRejectionReasonTest extends TestCase
{
    public function test_it_contains_every_standard_rejection_reason(): void
    {
        self::assertSame(
            [
                'LOW_RESOLUTION',
                'BLURRY_IMAGE',
                'WATERMARK_DETECTED',
                'TEXT_OVERLAY_DETECTED',
                'WRONG_PRODUCT',
                'WRONG_COLOR',
                'WRONG_BRAND',
                'LOW_CONFIDENCE',
                'SOURCE_NOT_ALLOWED',
                'DUPLICATE_WORSE_QUALITY',
                'ROBOTS_OR_PERMISSION_BLOCKED',
                'DOWNLOAD_FAILED',
                'INVALID_MIME_TYPE',
            ],
            array_map(static fn (ProductImageDiscoveryRejectionReason $reason): string => $reason->value, ProductImageDiscoveryRejectionReason::cases()),
        );
    }

    public function test_retryable_and_manual_review_helpers_are_exposed_where_sensible(): void
    {
        self::assertTrue(ProductImageDiscoveryRejectionReason::DownloadFailed->isRetryable());
        self::assertTrue(ProductImageDiscoveryRejectionReason::LowConfidence->requiresManualReview());
        self::assertTrue(ProductImageDiscoveryRejectionReason::WrongBrand->requiresManualReview());
        self::assertFalse(ProductImageDiscoveryRejectionReason::WrongColor->isRetryable());
        self::assertFalse(ProductImageDiscoveryRejectionReason::WatermarkDetected->requiresManualReview());
    }
}
