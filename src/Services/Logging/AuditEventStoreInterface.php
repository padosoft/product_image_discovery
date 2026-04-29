<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Logging;

interface AuditEventStoreInterface
{
    public function storeAuditEvent(array $event): void;
}
