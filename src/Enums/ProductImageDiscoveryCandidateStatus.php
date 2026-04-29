<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Enums;

enum ProductImageDiscoveryCandidateStatus: string
{
    case Candidate = 'candidate';
    case LowScoreRejected = 'low_score_rejected';
    case VerifiedMatch = 'verified_match';
    case WrongProduct = 'wrong_product';
    case WrongColor = 'wrong_color';
    case Downloaded = 'downloaded';
    case QualityPassed = 'quality_passed';
    case QualityFailed = 'quality_failed';
    case Enhanced = 'enhanced';
    case Selected = 'selected';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::LowScoreRejected,
            self::WrongProduct,
            self::WrongColor,
            self::QualityFailed,
            self::Selected,
            self::Rejected => true,
            default => false,
        };
    }
}
