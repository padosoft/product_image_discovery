<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Logging;

use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryEvent;

final class DatabaseAuditEventStore implements AuditEventStoreInterface
{
    public function storeAuditEvent(array $event): void
    {
        if (! class_exists(ProductImageDiscoveryEvent::class)) {
            return;
        }

        if (($event['request_id'] ?? null) === null) {
            return;
        }

        ProductImageDiscoveryEvent::query()->create([
            'request_id' => $event['request_id'] ?? null,
            'candidate_id' => $event['candidate_id'] ?? null,
            'event_type' => $event['event_type'] ?? 'unknown',
            'level' => $event['level'] ?? 'info',
            'message' => $event['message'] ?? null,
            'context' => $event['context'] ?? [],
            'created_at' => $event['created_at'] ?? (function_exists('now') ? now() : gmdate('c')),
        ]);
    }
}
