<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Actions;

use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\Services\Quality\ImageQualityAnalyzer;

final readonly class AssessImageQualityAction
{
    public function __construct(
        private ?ImageQualityAnalyzer $analyzer = null,
    ) {
    }

    /**
     * @param string|array<string, mixed>|CandidateImageData $image
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function handle(string|array|CandidateImageData $image, array $settings = []): array
    {
        $analyzer = $this->analyzer ?? new ImageQualityAnalyzer();

        if ($image instanceof CandidateImageData) {
            $image = [
                'path' => $image->imageUrl,
                'url' => $image->imageUrl,
                'width' => $image->width,
                'height' => $image->height,
                'mime_type' => $image->mimeType,
                'file_size' => $image->fileSize,
                'quality_analysis' => $image->qualityAnalysis,
            ];
        }

        return $analyzer->analyze($image, $settings);
    }
}
