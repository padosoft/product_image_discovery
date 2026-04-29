<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Enums;

enum ProductImageDiscoveryRequestStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Searching = 'searching';
    case CandidatesFound = 'candidates_found';
    case NoCandidatesFound = 'no_candidates_found';
    case Extracting = 'extracting';
    case Verifying = 'verifying';
    case Matched = 'matched';
    case Downloaded = 'downloaded';
    case QualityChecking = 'quality_checking';
    case ReadyToPublish = 'ready_to_publish';
    case ManualReview = 'manual_review';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Published = 'published';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::NoCandidatesFound,
            self::ManualReview,
            self::Rejected,
            self::Failed,
            self::Published => true,
            default => false,
        };
    }

    public function isRetryable(): bool
    {
        return match ($this) {
            self::NoCandidatesFound,
            self::Failed => true,
            default => false,
        };
    }

    public function requiresManualReview(): bool
    {
        return $this === self::ManualReview;
    }
}
