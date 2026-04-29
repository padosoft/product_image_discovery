<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\ProductImageDiscovery\DTO\SearchResultData;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\DispatchesPipelineJobs;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\ResolvesQueueName;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;

final class ExtractCandidateSourcesJob implements ShouldQueue
{
    use Dispatchable;
    use DispatchesPipelineJobs;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesQueueName;
    use SerializesModels;

    public function __construct(private readonly int|string $requestId)
    {
        $this->onQueue($this->queueNameFor('extract'));
    }

    public function handle(PipelineStoreInterface $store, ProductImageEventLogger $logger): array
    {
        $request = $store->getRequest($this->requestId);

        if ($request === null) {
            return [];
        }

        $context = $request['context'] ?? [];
        $results = $context['search']['execution']['results'] ?? [];

        if (($context['extract']['completed_at'] ?? null) !== null) {
            return $request;
        }

        $store->updateRequest($this->requestId, [
            'status' => ProductImageDiscoveryRequestStatus::Extracting->value,
        ]);

        $candidateIds = [];
        $sourcePageUrls = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $searchResult = SearchResultData::fromArray([
                'provider' => $result['provider_metadata']['provider'] ?? ($result['provider'] ?? 'unknown'),
                'title' => $result['title'] ?? null,
                'source_page_url' => $result['page_url'] ?? $result['source_page_url'] ?? null,
                'image_url' => $result['image_url'] ?? null,
                'snippet' => $result['snippet'] ?? null,
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'metadata' => $result['provider_metadata'] ?? [],
            ]);
            $candidateData = $searchResult->toCandidate();
            $pageUrl = $candidateData->sourcePageUrl;

            if (is_string($pageUrl) && $pageUrl !== '') {
                $sourcePage = $store->upsertSourcePage(
                    $request['client_id'] ?? 'global',
                    $pageUrl,
                    [
                        'request_id' => $this->requestId,
                        'url' => $pageUrl,
                        'domain' => parse_url($pageUrl, PHP_URL_HOST) ?: null,
                        'fetch_strategy' => 'search_result',
                        'extracted_images' => $candidateData->imageUrl !== null ? [$candidateData->imageUrl] : [],
                    ],
                );

                $sourcePageUrls[] = $sourcePage['url'] ?? $pageUrl;
            }

            $candidate = $store->upsertCandidate(
                $this->requestId,
                (string) ($result['fingerprint'] ?? sha1(strtolower((string) $candidateData->sourcePageUrl).'|'.strtolower((string) $candidateData->imageUrl))),
                array_merge($candidateData->toArray(), [
                    'client_id' => $request['client_id'] ?? null,
                    'status' => ProductImageDiscoveryCandidateStatus::Candidate->value,
                    'quality_analysis' => $result['provider_metadata'] ?? [],
                ]),
            );

            $candidateIds[] = $candidate['id'];
        }

        $candidateIds = array_values(array_unique($candidateIds));
        $sourcePageUrls = array_values(array_unique(array_filter($sourcePageUrls)));

        $store->updateRequest($this->requestId, [
            'status' => $candidateIds === []
                ? ProductImageDiscoveryRequestStatus::NoCandidatesFound->value
                : ProductImageDiscoveryRequestStatus::CandidatesFound->value,
        ]);
        $store->mergeRequestContext($this->requestId, [
            'extract' => [
                'completed_at' => gmdate('c'),
                'candidate_ids' => $candidateIds,
                'source_pages' => $sourcePageUrls,
            ],
        ]);

        $logger->record('pipeline.extract.completed', [
            'candidate_count' => count($candidateIds),
            'source_page_count' => count($sourcePageUrls),
        ], requestId: $this->requestId);

        foreach ($candidateIds as $candidateId) {
            $this->dispatchIfPossible(new VerifyCandidateImageJob($this->requestId, $candidateId));
        }

        return $store->getRequest($this->requestId) ?? $request;
    }
}
