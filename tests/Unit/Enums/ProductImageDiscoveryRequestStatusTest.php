<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;

class ProductImageDiscoveryRequestStatusTest extends TestCase
{
    public function test_it_contains_every_documented_status(): void
    {
        self::assertSame(
            [
                'pending',
                'queued',
                'searching',
                'candidates_found',
                'no_candidates_found',
                'extracting',
                'verifying',
                'matched',
                'downloaded',
                'quality_checking',
                'ready_to_publish',
                'manual_review',
                'rejected',
                'failed',
                'published',
            ],
            array_map(static fn (ProductImageDiscoveryRequestStatus $status): string => $status->value, ProductImageDiscoveryRequestStatus::cases()),
        );
    }

    public function test_terminal_retryable_and_manual_review_helpers_are_consistent(): void
    {
        self::assertTrue(ProductImageDiscoveryRequestStatus::NoCandidatesFound->isTerminal());
        self::assertTrue(ProductImageDiscoveryRequestStatus::NoCandidatesFound->isRetryable());
        self::assertTrue(ProductImageDiscoveryRequestStatus::Failed->isRetryable());
        self::assertTrue(ProductImageDiscoveryRequestStatus::ManualReview->requiresManualReview());
        self::assertFalse(ProductImageDiscoveryRequestStatus::Searching->isTerminal());
        self::assertFalse(ProductImageDiscoveryRequestStatus::Published->isRetryable());
    }
}
