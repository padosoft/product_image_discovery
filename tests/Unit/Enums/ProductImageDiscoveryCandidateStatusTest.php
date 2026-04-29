<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;

class ProductImageDiscoveryCandidateStatusTest extends TestCase
{
    public function test_it_contains_every_documented_candidate_status(): void
    {
        self::assertSame(
            [
                'candidate',
                'low_score_rejected',
                'verified_match',
                'wrong_product',
                'wrong_color',
                'downloaded',
                'quality_passed',
                'quality_failed',
                'enhanced',
                'selected',
                'rejected',
            ],
            array_map(static fn (ProductImageDiscoveryCandidateStatus $status): string => $status->value, ProductImageDiscoveryCandidateStatus::cases()),
        );
    }

    public function test_terminal_helper_marks_end_states(): void
    {
        self::assertTrue(ProductImageDiscoveryCandidateStatus::Selected->isTerminal());
        self::assertTrue(ProductImageDiscoveryCandidateStatus::WrongProduct->isTerminal());
        self::assertFalse(ProductImageDiscoveryCandidateStatus::Downloaded->isTerminal());
    }
}
