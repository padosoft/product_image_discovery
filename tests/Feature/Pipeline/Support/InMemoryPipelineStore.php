<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline\Support;

use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\AuditEventStoreInterface;

final class InMemoryPipelineStore implements PipelineStoreInterface, AuditEventStoreInterface
{
    /**
     * @var array<int|string, array<string, mixed>>
     */
    public array $requests = [];

    /**
     * @var array<int|string, array<string, mixed>>
     */
    public array $candidates = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $sourcePages = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $events = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $trustedSources = [];

    /**
     * @var array<string, mixed>
     */
    public array $settings = [];

    private int $requestSequence = 0;

    private int $candidateSequence = 0;

    public function upsertRequest(array $identity, array $attributes = []): array
    {
        $identityKey = $this->requestKey($identity);

        foreach ($this->requests as $request) {
            if (($request['identity_key'] ?? null) === $identityKey) {
                $id = $request['id'];
                $this->requests[$id] = $this->merge($request, $attributes);

                return $this->requests[$id];
            }
        }

        $id = ++$this->requestSequence;
        $record = $attributes + [
            'id' => $id,
            'identity_key' => $identityKey,
            'context' => [],
        ];

        $this->requests[$id] = $record;

        return $record;
    }

    public function getRequest(int|string $requestId): ?array
    {
        return $this->requests[$requestId] ?? null;
    }

    public function updateRequest(int|string $requestId, array $attributes): array
    {
        $request = $this->requests[$requestId] ?? ['id' => $requestId, 'context' => []];
        $this->requests[$requestId] = $this->merge($request, $attributes);

        return $this->requests[$requestId];
    }

    public function mergeRequestContext(int|string $requestId, array $context): array
    {
        $request = $this->requests[$requestId] ?? ['id' => $requestId, 'context' => []];
        $request['context'] = $this->merge($request['context'] ?? [], $context);
        $this->requests[$requestId] = $request;

        return $request;
    }

    public function upsertCandidate(int|string $requestId, string $fingerprint, array $attributes): array
    {
        foreach ($this->candidates as $id => $candidate) {
            if (($candidate['request_id'] ?? null) === $requestId && ($candidate['fingerprint'] ?? null) === $fingerprint) {
                $this->candidates[$id] = $this->merge($candidate, $attributes);

                return $this->candidates[$id];
            }
        }

        $id = ++$this->candidateSequence;
        $record = $attributes + [
            'id' => $id,
            'request_id' => $requestId,
            'fingerprint' => $fingerprint,
        ];

        $this->candidates[$id] = $record;

        return $record;
    }

    public function getCandidate(int|string $candidateId): ?array
    {
        return $this->candidates[$candidateId] ?? null;
    }

    public function listCandidates(int|string $requestId): array
    {
        return array_values(array_filter(
            $this->candidates,
            static fn (array $candidate): bool => ($candidate['request_id'] ?? null) === $requestId,
        ));
    }

    public function updateCandidate(int|string $candidateId, array $attributes): array
    {
        $candidate = $this->candidates[$candidateId] ?? ['id' => $candidateId];
        $this->candidates[$candidateId] = $this->merge($candidate, $attributes);

        return $this->candidates[$candidateId];
    }

    public function upsertSourcePage(int|string $clientId, string $url, array $attributes = []): array
    {
        $key = $clientId.'|'.sha1(strtolower($url));

        if (isset($this->sourcePages[$key])) {
            $this->sourcePages[$key] = $this->merge($this->sourcePages[$key], $attributes);

            return $this->sourcePages[$key];
        }

        $record = $attributes + [
            'client_id' => $clientId,
            'url' => $url,
            'url_hash' => sha1(strtolower($url)),
        ];

        $this->sourcePages[$key] = $record;

        return $record;
    }

    public function listTrustedSources(int|string|null $clientId): array
    {
        return array_values(array_filter(
            $this->trustedSources,
            static fn (array $source): bool => ($source['is_active'] ?? true) !== false
                && (($source['client_id'] ?? null) === null || ($clientId !== null && (int) $source['client_id'] === (int) $clientId)),
        ));
    }

    public function getSettings(int|string|null $clientId): array
    {
        return $this->settings;
    }

    public function storeAuditEvent(array $event): void
    {
        $this->events[] = $event;
    }

    private function requestKey(array $identity): string
    {
        return implode('|', [
            (string) ($identity['client_id'] ?? ''),
            (string) ($identity['erp_model_color_id'] ?? ''),
        ]);
    }

    private function merge(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value) && is_array($current[$key] ?? null)) {
                $current[$key] = $this->merge($current[$key], $value);
                continue;
            }

            $current[$key] = $value;
        }

        return $current;
    }
}
