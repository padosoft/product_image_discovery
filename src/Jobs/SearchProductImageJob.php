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
use Padosoft\LaravelAiSearchProviders\Data\SearchProviderExecutionResult;
use Padosoft\LaravelAiSearchProviders\Data\SearchQueryData as ProviderSearchQueryData;
use Padosoft\LaravelAiSearchProviders\Data\SearchResult;
use Padosoft\LaravelAiSearchProviders\Data\SearchResultCollection;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;

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
        $trustedSources = $store->listTrustedSources($request['client_id'] ?? null);
        $searchQueries = ($queryGenerator ?? new GenerateSearchQueriesAction())->handle($identity, $trustedSources);

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
            $rawExecution = $manager->searchImages(ProviderSearchQueryData::fromArray([
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

            $execution = $this->discardResultsMissingIdentifier($searchQuery, $identity, $rawExecution);
            $discardedResults = $rawExecution->results->count() - $execution->results->count();

            $executions[] = [
                'search_query' => $searchQuery->toArray(),
                'execution' => $execution->toArray(),
                'discarded_results' => $discardedResults,
            ];

            if ($discardedResults > 0) {
                $logger->record('pipeline.search.results_discarded', [
                    'intent' => $searchQuery->intent,
                    'query' => $searchQuery->query,
                    'discarded_results' => $discardedResults,
                    'kept_results' => $execution->results->count(),
                ], requestId: $this->requestId);
            }

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

    /**
     * Fuzzy providers return unrelated results even for identifiers that are not
     * indexed anywhere; an identifier-driven query must only win the "first
     * non-empty query" race with results that actually mention the identifier.
     */
    private function discardResultsMissingIdentifier(
        SearchQueryData $searchQuery,
        ProductIdentityData $identity,
        SearchProviderExecutionResult $execution,
    ): SearchProviderExecutionResult {
        if ($identity->ean === null || ! in_array($searchQuery->intent, ['ean', 'site_ean'], true)) {
            return $execution;
        }

        $kept = array_values(array_filter(
            $execution->results->all(),
            fn (SearchResult $result): bool => $this->resultMentionsIdentifier($result, $identity->ean),
        ));

        if (count($kept) === $execution->results->count()) {
            return $execution;
        }

        return new SearchProviderExecutionResult(
            provider: $execution->provider,
            results: new SearchResultCollection($kept),
            attempts: $execution->attempts,
            usedFallback: $execution->usedFallback,
        );
    }

    private function resultMentionsIdentifier(SearchResult $result, string $identifier): bool
    {
        $needle = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $identifier));

        if ($needle === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $result->title,
            $result->pageUrl,
            $result->imageUrl,
            $result->snippet,
            $result->sourceDomain,
            TextNormalizer::flattenStrings($result->providerMetadata),
        ], static fn (?string $part): bool => $part !== null && $part !== '')));

        return str_contains((string) preg_replace('/[^a-z0-9]+/', '', $haystack), $needle);
    }
}
