<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs\Contracts;

interface PipelineStoreInterface
{
    public function upsertRequest(array $identity, array $attributes = []): array;

    public function getRequest(int|string $requestId): ?array;

    public function updateRequest(int|string $requestId, array $attributes): array;

    public function mergeRequestContext(int|string $requestId, array $context): array;

    public function upsertCandidate(int|string $requestId, string $fingerprint, array $attributes): array;

    public function getCandidate(int|string $candidateId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCandidates(int|string $requestId): array;

    public function updateCandidate(int|string $candidateId, array $attributes): array;

    public function upsertSourcePage(int|string $clientId, string $url, array $attributes = []): array;

    /**
     * Active trusted sources for the client, including global (client-less) entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTrustedSources(int|string|null $clientId): array;

    /**
     * Active settings for the client as a key => value map; client-specific values win over global ones.
     *
     * @return array<string, mixed>
     */
    public function getSettings(int|string|null $clientId): array;
}
