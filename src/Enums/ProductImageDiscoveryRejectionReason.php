<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Enums;

enum ProductImageDiscoveryRejectionReason: string
{
    case LowResolution = 'LOW_RESOLUTION';
    case BlurryImage = 'BLURRY_IMAGE';
    case WatermarkDetected = 'WATERMARK_DETECTED';
    case TextOverlayDetected = 'TEXT_OVERLAY_DETECTED';
    case WrongProduct = 'WRONG_PRODUCT';
    case WrongColor = 'WRONG_COLOR';
    case WrongBrand = 'WRONG_BRAND';
    case LowConfidence = 'LOW_CONFIDENCE';
    case SourceNotAllowed = 'SOURCE_NOT_ALLOWED';
    case DuplicateWorseQuality = 'DUPLICATE_WORSE_QUALITY';
    case RobotsOrPermissionBlocked = 'ROBOTS_OR_PERMISSION_BLOCKED';
    case DownloadFailed = 'DOWNLOAD_FAILED';
    case InvalidMimeType = 'INVALID_MIME_TYPE';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::DownloadFailed => true,
            default => false,
        };
    }

    public function requiresManualReview(): bool
    {
        return match ($this) {
            self::LowConfidence,
            self::WrongBrand => true,
            default => false,
        };
    }
}
