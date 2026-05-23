<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\ProductImageDiscovery\Actions\GenerateSearchQueriesAction;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\DTO\SearchQueryData;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\DispatchesPipelineJobs;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\ResolvesQueueName;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\LaravelAiSearchProviders\Data\SearchQueryData as ProviderSearchQueryData;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;

final class SearchProductImageJob implements ShouldQueue
{
    use Dispatchable;
    use DispatchesPipelineJobs;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesQueueName;
    use SerializesModels;

    public function __construct(private readonly int|string $requestId)
    {
        $this->onQueue($this->queueNameFor('search'));
    }

    public function handle(
        PipelineStoreInterface $store,
        SearchProviderManager $manager,
        ProductImageEventLogger $logger,
        ?GenerateSearchQueriesAction $queryGenerator = null,
    ): array {
        $request = $store->getRequest($this->requestId);

        if ($request === null) {
            return [];
        }

        $context = $request['context'] ?? [];

        if (($context['search']['completed_at'] ?? null) !== null) {
            return $request;
        }

        $identity = ProductIdentityData::fromArray(array_merge($request, [
            'raw_payload' => $request['raw_payload'] ?? $request,
        ]));
        $searchQueries = ($queryGenerator ?? new GenerateSearchQueriesAction())->handle($identity);

        if ($searchQueries === []) {
            $searchQueries = [
                new SearchQueryData(
                    query: trim(implode(' ', array_filter([$identity->brand, $identity->modelCode, $identity->colorName, $identity->ean, $identity->supplierSku]))),
                    intent: 'fallback',
                    priority: 1,
                ),
            ];
        }

        $store->updateRequest($this->requestId, [
            'status' => ProductImageDiscoveryRequestStatus::Searching->value,
            'search_started_at' => gmdate('c'),
            'attempts' => (int) ($request['attempts'] ?? 0) + 1,
        ]);

        $executions = [];
        $execution = null;

        foreach ($searchQueries as $searchQuery) {
            $execution = $manager->searchImages(ProviderSearchQueryData::fromArray([
                'client_id' => $identity->clientId,
                'brand' => $identity->brand,
                'model' => $identity->modelCode ?? $identity->description,
                'color' => $identity->colorName,
                'ean' => $identity->ean,
                'supplier_sku' => $identity->supplierSku,
                'query' => $searchQuery->query,
                'site' => $searchQuery->siteDomain,
                'limit' => 10,
                'metadata' => $searchQuery->toArray(),
            ]));

            $executions[] = [
                'search_query' => $searchQuery->toArray(),
                'execution' => $execution->toArray(),
            ];

            if (! $execution->results->isEmpty()) {
                break;
            }
        }

        $execution ??= $manager->searchImages(ProviderSearchQueryData::fromArray([
            'client_id' => $identity->clientId,
            'query' => $identity->ean ?? $identity->supplierSku ?? $identity->modelCode ?? '',
        ]));

        $store->updateRequest($this->requestId, [
            'status' => $execution->results->isEmpty()
                ? ProductImageDiscoveryRequestStatus::NoCandidatesFound->value
                : ProductImageDiscoveryRequestStatus::CandidatesFound->value,
            'search_completed_at' => gmdate('c'),
        ]);
        $store->mergeRequestContext($this->requestId, [
            'search' => [
                'completed_at' => gmdate('c'),
                'queries' => array_map(static fn (SearchQueryData $query): array => $query->toArray(), $searchQueries),
                'execution' => $execution->toArray(),
                'executions' => $executions,
            ],
        ]);

        $logger->record('pipeline.search.completed', [
            'results_count' => $execution->results->count(),
            'provider' => $execution->provider?->toSafeArray(),
            'attempts' => $execution->attempts,
            'used_fallback' => $execution->usedFallback,
        ], requestId: $this->requestId);

        $this->dispatchIfPossible(new ExtractCandidateSourcesJob($this->requestId));

        return $store->getRequest($this->requestId) ?? $request;
    }
}
