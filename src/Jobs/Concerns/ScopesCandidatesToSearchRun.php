<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs\Concerns;

use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;

trait ScopesCandidatesToSearchRun
{
    /**
     * Candidates of the request's latest search run, so candidates left by earlier runs (a retry
     * or a re-POST) never take part in this run's decision. Requests searched before runs were
     * tracked have no run in their context: all their candidates are returned, as before.
     *
     * @param array<string, mixed> $request
     * @return list<array<string, mixed>>
     */
    private function candidatesOfCurrentRun(PipelineStoreInterface $store, array $request): array
    {
        $candidates = $store->listCandidates($this->requestId);
        $searchRun = $request['context']['search']['run'] ?? null;

        if ($searchRun === null) {
            return $candidates;
        }

        return array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => (int) ($candidate['search_run'] ?? 0) === (int) $searchRun,
        ));
    }
}
