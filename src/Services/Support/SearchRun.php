<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Support;

final class SearchRun
{
    /**
     * Latest search run recorded in a request's raw_payload.context.search.run, or null for
     * requests searched before runs were tracked (their candidates have no run either).
     */
    public static function current(mixed $rawPayload): ?int
    {
        $searchRun = is_array($rawPayload) ? ($rawPayload['context']['search']['run'] ?? null) : null;

        return is_numeric($searchRun) ? (int) $searchRun : null;
    }
}
