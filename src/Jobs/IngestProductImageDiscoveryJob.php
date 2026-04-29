<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\DispatchesPipelineJobs;
use Padosoft\ProductImageDiscovery\Jobs\Concerns\ResolvesQueueName;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;

final class IngestProductImageDiscoveryJob implements ShouldQueue
{
    use Dispatchable;
    use DispatchesPipelineJobs;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesQueueName;
    use SerializesModels;

    public function __construct(private readonly array|int|string $payloadOrRequestId)
    {
        $this->onQueue($this->queueNameFor('ingest'));
    }

    public function handle(PipelineStoreInterface $store, ProductImageEventLogger $logger): array
    {
        if (! is_array($this->payloadOrRequestId)) {
            return $this->dispatchExistingRequest($store, $logger);
        }

        $payload = $this->payloadOrRequestId;
        $identity = ProductIdentityData::fromArray($payload);

        $request = $store->upsertRequest([
            'client_id' => $identity->clientId,
            'erp_model_color_id' => $identity->erpModelColorId,
        ], [
            'status' => ProductImageDiscoveryRequestStatus::Queued->value,
            'client_id' => $identity->clientId,
            'erp_model_id' => $identity->erpModelId,
            'erp_model_color_id' => $identity->erpModelColorId,
            'erp_model_color_size_id' => $identity->erpModelColorSizeId,
            'brand' => $identity->brand,
            'supplier' => $identity->supplier,
            'sku' => $identity->sku,
            'supplier_sku' => $identity->supplierSku,
            'model_code' => $identity->modelCode ?? ($payload['model'] ?? null),
            'color_code' => $identity->colorCode,
            'color_name' => $identity->colorName ?? ($payload['color'] ?? null),
            'ean' => $identity->ean,
            'season' => $identity->season,
            'category' => $identity->category,
            'material' => $identity->material,
            'raw_payload' => array_merge($identity->rawPayload, [
                'model' => $payload['model'] ?? null,
                'color' => $payload['color'] ?? null,
            ]),
        ]);

        $context = $request['context'] ?? [];
        $ingestHash = sha1(json_encode($payload, JSON_THROW_ON_ERROR));

        if (($context['ingest']['payload_hash'] ?? null) !== $ingestHash) {
            $store->mergeRequestContext($request['id'], [
                'ingest' => [
                    'payload_hash' => $ingestHash,
                    'identity' => $identity->toArray(),
                    'processed_at' => gmdate('c'),
                ],
            ]);

            $logger->record('pipeline.ingest.processed', [
                'identity' => $identity->toArray(),
            ], requestId: $request['id']);
        }

        $this->dispatchIfPossible(new SearchProductImageJob($request['id']));

        return $store->getRequest($request['id']) ?? $request;
    }

    private function dispatchExistingRequest(PipelineStoreInterface $store, ProductImageEventLogger $logger): array
    {
        $request = $store->getRequest($this->payloadOrRequestId);

        if ($request === null) {
            return [];
        }

        $request = $store->updateRequest($this->payloadOrRequestId, [
            'status' => ProductImageDiscoveryRequestStatus::Queued->value,
            'rejection_reason' => null,
        ]);

        $logger->record('pipeline.ingest.resumed', [
            'request_id' => $this->payloadOrRequestId,
        ], requestId: $this->payloadOrRequestId);

        $this->dispatchIfPossible(new SearchProductImageJob($this->payloadOrRequestId));

        return $store->getRequest($this->payloadOrRequestId) ?? $request;
    }
}
