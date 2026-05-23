<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Logging;

use Padosoft\LaravelAiSearchProviders\Contracts\SearchEventLoggerInterface;

final class ProductImageEventLogger implements SearchEventLoggerInterface
{
    public function __construct(
        private readonly ?AuditEventStoreInterface $store = null,
        private readonly ?SecretRedactor $redactor = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function record(
        string $eventType,
        array $context = [],
        string $level = 'info',
        int|string|null $requestId = null,
        int|string|null $candidateId = null,
        ?string $message = null,
    ): array {
        $safeContext = ($this->redactor ?? new SecretRedactor())->redact($context);

        $event = [
            'request_id' => $requestId,
            'candidate_id' => $candidateId,
            'event_type' => $eventType,
            'level' => $level,
            'message' => $message,
            'context' => $safeContext,
            'created_at' => gmdate('c'),
        ];

        $this->store?->storeAuditEvent($event);

        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
            try {
                \Illuminate\Support\Facades\Log::log($level, $eventType, [
                    'request_id' => $requestId,
                    'candidate_id' => $candidateId,
                    'message' => $message,
                    'context' => $safeContext,
                ]);
            } catch (\Throwable) {
                // Pure unit tests and standalone jobs may run without a Laravel log binding.
            }
        }

        return $event;
    }
}
