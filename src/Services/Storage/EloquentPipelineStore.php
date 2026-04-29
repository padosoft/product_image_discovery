<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Storage;

use Illuminate\Support\Arr;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryCandidate;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoverySourcePage;

final class EloquentPipelineStore implements PipelineStoreInterface
{
    public function upsertRequest(array $identity, array $attributes = []): array
    {
        $clientId = $this->normalizeClientId($identity['client_id'] ?? $attributes['client_id'] ?? null);
        $erpModelColorId = (string) ($identity['erp_model_color_id'] ?? $attributes['erp_model_color_id'] ?? '');

        $request = ProductImageDiscoveryRequest::query()->firstOrNew([
            'client_id' => $clientId,
            'erp_model_color_id' => $erpModelColorId,
        ]);

        $request->fill(array_merge($attributes, [
            'client_id' => $clientId,
            'erp_model_color_id' => $erpModelColorId,
            'raw_payload' => $attributes['raw_payload'] ?? $request->raw_payload ?? $attributes,
        ]));
        $request->save();

        return $this->requestToArray($request->refresh());
    }

    public function getRequest(int|string $requestId): ?array
    {
        $request = ProductImageDiscoveryRequest::query()->find($requestId);

        return $request instanceof ProductImageDiscoveryRequest ? $this->requestToArray($request) : null;
    }

    public function updateRequest(int|string $requestId, array $attributes): array
    {
        $request = ProductImageDiscoveryRequest::query()->findOrFail($requestId);
        $request->fill($attributes);
        $request->save();

        return $this->requestToArray($request->refresh());
    }

    public function mergeRequestContext(int|string $requestId, array $context): array
    {
        $request = ProductImageDiscoveryRequest::query()->findOrFail($requestId);
        $rawPayload = is_array($request->raw_payload) ? $request->raw_payload : [];
        $rawPayload['context'] = array_replace_recursive($rawPayload['context'] ?? [], $context);
        $request->forceFill(['raw_payload' => $rawPayload])->save();

        return $this->requestToArray($request->refresh());
    }

    public function upsertCandidate(int|string $requestId, string $fingerprint, array $attributes): array
    {
        $candidate = ProductImageDiscoveryCandidate::query()
            ->where('request_id', $requestId)
            ->where('fingerprint', $fingerprint)
            ->first();

        if (! $candidate instanceof ProductImageDiscoveryCandidate) {
            $candidate = new ProductImageDiscoveryCandidate([
                'request_id' => $requestId,
                'fingerprint' => $fingerprint,
            ]);
        }

        $candidate->fill(array_merge($attributes, [
            'request_id' => $requestId,
            'fingerprint' => $fingerprint,
            'source_domain' => $attributes['source_domain'] ?? parse_url((string) ($attributes['source_page_url'] ?? ''), PHP_URL_HOST) ?: 'unknown',
            'source_page_url' => $attributes['source_page_url'] ?? '',
            'image_url' => $attributes['image_url'] ?? '',
        ]));
        $candidate->save();

        return $this->candidateToArray($candidate->refresh());
    }

    public function getCandidate(int|string $candidateId): ?array
    {
        $candidate = ProductImageDiscoveryCandidate::query()->find($candidateId);

        return $candidate instanceof ProductImageDiscoveryCandidate ? $this->candidateToArray($candidate) : null;
    }

    public function listCandidates(int|string $requestId): array
    {
        return ProductImageDiscoveryCandidate::query()
            ->where('request_id', $requestId)
            ->orderedByScore()
            ->get()
            ->map(fn (ProductImageDiscoveryCandidate $candidate): array => $this->candidateToArray($candidate))
            ->all();
    }

    public function updateCandidate(int|string $candidateId, array $attributes): array
    {
        $candidate = ProductImageDiscoveryCandidate::query()->findOrFail($candidateId);
        $candidate->fill($attributes);
        $candidate->save();

        return $this->candidateToArray($candidate->refresh());
    }

    public function upsertSourcePage(int|string $clientId, string $url, array $attributes = []): array
    {
        $normalizedClientId = $this->normalizeClientId($clientId);
        $urlHash = hash('sha256', strtolower(trim($url)));

        $sourcePage = ProductImageDiscoverySourcePage::query()->firstOrNew([
            'client_id' => $normalizedClientId,
            'url_hash' => $urlHash,
        ]);

        $sourcePage->fill(array_merge($attributes, [
            'client_id' => $normalizedClientId,
            'url_hash' => $urlHash,
            'url' => $url,
            'domain' => $attributes['domain'] ?? parse_url($url, PHP_URL_HOST) ?: 'unknown',
        ]));
        $sourcePage->save();

        return $sourcePage->refresh()->toArray();
    }

    private function requestToArray(ProductImageDiscoveryRequest $request): array
    {
        $data = $request->toArray();
        $rawPayload = is_array($request->raw_payload) ? $request->raw_payload : [];
        $data['context'] = $rawPayload['context'] ?? [];

        return $data;
    }

    private function candidateToArray(ProductImageDiscoveryCandidate $candidate): array
    {
        $data = $candidate->toArray();
        $qualityAnalysis = is_array($candidate->quality_analysis) ? $candidate->quality_analysis : [];
        $data['provider_metadata'] = Arr::get($qualityAnalysis, 'provider_metadata', $qualityAnalysis);

        return $data;
    }

    private function normalizeClientId(mixed $clientId): ?int
    {
        if ($clientId === null || $clientId === '' || $clientId === 'global') {
            return null;
        }

        return (int) $clientId;
    }
}
