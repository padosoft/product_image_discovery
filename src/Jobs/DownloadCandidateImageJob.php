<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\DispatchesPipelineJobs;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\ResolvesQueueName;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;

final class DownloadCandidateImageJob implements ShouldQueue
{
    use Dispatchable;
    use DispatchesPipelineJobs;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesQueueName;
    use SerializesModels;

    public function __construct(
        private readonly int|string $requestId,
        private readonly int|string $candidateId,
    ) {
        $this->onQueue($this->queueNameFor('download'));
    }

    public function handle(PipelineStoreInterface $store, ProductImageEventLogger $logger): array
    {
        $candidate = $store->getCandidate($this->candidateId);

        if ($candidate === null) {
            return [];
        }

        if (($candidate['status'] ?? null) === ProductImageDiscoveryCandidateStatus::Downloaded->value || ! empty($candidate['local_original_path'])) {
            return $candidate;
        }

        [$contents, $extension] = $this->resolveContents($candidate);

        if ($contents === null) {
            $updated = $store->updateCandidate($this->candidateId, [
                'status' => ProductImageDiscoveryCandidateStatus::Rejected->value,
                'rejection_reason' => ProductImageDiscoveryRejectionReason::DownloadFailed->value,
                'quality_analysis' => array_merge($candidate['quality_analysis'] ?? [], [
                    'download_status' => 'skipped',
                    'download_reason' => 'no_downloadable_image',
                ]),
            ]);

            $logger->record('pipeline.download.skipped', [
                'reason' => 'no_downloadable_image',
            ], 'warning', $this->requestId, $this->candidateId);

            return $updated;
        }

        $path = null;
        $status = 'captured';

        if (class_exists(\Illuminate\Support\Facades\Storage::class)) {
            try {
                $disk = function_exists('config')
                    ? (string) (config('product-image-discovery.storage.disk') ?? config('product_image_discovery.storage.disk') ?? 'local')
                    : 'local';

                $path = sprintf('product-image-discovery/%s/%s.%s', $this->requestId, $this->candidateId, $extension);
                \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $contents);
                $status = 'stored';
            } catch (\Throwable) {
                $path = null;
                $status = 'captured';
            }
        }

        $mimeType = $candidate['mime_type'] ?? $this->guessMimeType($extension);

        $updated = $store->updateCandidate($this->candidateId, [
            'status' => ProductImageDiscoveryCandidateStatus::Downloaded->value,
            'local_original_path' => $path,
            'sha256' => hash('sha256', $contents),
            'file_size' => strlen($contents),
            'mime_type' => $mimeType,
            'downloaded_at' => gmdate('c'),
            'quality_analysis' => array_merge($candidate['quality_analysis'] ?? [], [
                'download_status' => $status,
                'download_bytes' => strlen($contents),
            ]),
        ]);

        $store->updateRequest($this->requestId, [
            'status' => ProductImageDiscoveryRequestStatus::Downloaded->value,
        ]);

        $logger->record('pipeline.download.completed', [
            'download_status' => $status,
            'local_path' => $path,
            'bytes' => strlen($contents),
        ], requestId: $this->requestId, candidateId: $this->candidateId);

        $this->dispatchIfPossible(new AssessImageQualityJob($this->requestId, $this->candidateId));

        return $updated;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function resolveContents(array $candidate): array
    {
        $providerMetadata = is_array($candidate['provider_metadata'] ?? null)
            ? $candidate['provider_metadata']
            : (is_array($candidate['quality_analysis'] ?? null) ? $candidate['quality_analysis'] : []);
        $inlineBase64 = $providerMetadata['inline_image_base64'] ?? null;

        if (is_string($inlineBase64) && $inlineBase64 !== '') {
            return [base64_decode($inlineBase64, true) ?: null, (string) ($providerMetadata['inline_extension'] ?? 'jpg')];
        }

        $imageUrl = (string) ($candidate['image_url'] ?? '');

        if ($imageUrl === '') {
            return [null, 'jpg'];
        }

        if (str_starts_with($imageUrl, 'data:')) {
            [$header, $payload] = explode(',', $imageUrl, 2);
            preg_match('/data:image\/([a-zA-Z0-9+]+);base64/', $header, $matches);

            return [base64_decode($payload, true) ?: null, $matches[1] ?? 'jpg'];
        }

        if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
            return [null, 'jpg'];
        }

        $response = \Illuminate\Support\Facades\Http::timeout(15)->get($imageUrl)->throw();
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';

        return [$response->body(), (string) $extension];
    }

    private function guessMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
