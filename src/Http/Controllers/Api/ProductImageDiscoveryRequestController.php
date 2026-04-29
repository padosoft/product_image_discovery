<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Padosoft\ProductImageDiscovery\Http\Concerns\ResolvesProductImageDiscovery;
use Padosoft\ProductImageDiscovery\Http\Requests\SearchProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Http\Requests\StoreProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageDiscoveryRequestResource;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageDiscoveryRequestSummaryResource;

final class ProductImageDiscoveryRequestController extends Controller
{
    use ResolvesProductImageDiscovery;

    public function store(StoreProductImageDiscoveryRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $rawPayload = $request->input('raw_payload', $request->all());
        $persistableAttributes = array_intersect_key($attributes, array_flip([
            'client_id',
            'erp_model_id',
            'erp_model_color_id',
            'erp_model_color_size_id',
            'brand',
            'supplier',
            'sku',
            'supplier_sku',
            'model_code',
            'color_code',
            'color_name',
            'ean',
            'season',
            'category',
            'material',
            'price',
            'currency',
        ]));

        $record = $this->newQuery('request')->firstOrNew([
            'client_id' => $persistableAttributes['client_id'],
            'erp_model_color_id' => $persistableAttributes['erp_model_color_id'],
        ]);

        $created = ! $record->exists;

        $record->fill(array_merge($persistableAttributes, [
            'raw_payload' => $rawPayload,
            'status' => 'queued',
            'rejection_reason' => null,
        ]));
        $record->save();

        $this->dispatchConfiguredJob('ingest', $record->getKey());
        $this->recordAuditEvent($record, 'request_ingested', [
            'created' => $created,
        ]);

        return response()->json([
            'ok' => true,
            'request_id' => $record->getKey(),
            'erp_model_color_id' => $record->getAttribute('erp_model_color_id'),
            'status' => $record->getAttribute('status'),
        ], $created ? 201 : 200);
    }

    public function search(SearchProductImageDiscoveryRequest $request)
    {
        $query = $this->newQuery('request');
        $model = $this->newModel('request');
        $filters = $request->validated();

        foreach (['client_id', 'status', 'brand', 'supplier', 'erp_model_id', 'erp_model_color_id', 'ean', 'rejection_reason'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        if (($filters['min_score'] ?? null) !== null) {
            $query->where('final_score', '>=', $filters['min_score']);
        }

        if (($filters['max_score'] ?? null) !== null) {
            $query->where('final_score', '<=', $filters['max_score']);
        }

        if (($filters['created_from'] ?? null) !== null) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (($filters['created_to'] ?? null) !== null) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (($filters['updated_from'] ?? null) !== null) {
            $query->where('updated_at', '>=', $filters['updated_from']);
        }

        if (($filters['updated_to'] ?? null) !== null) {
            $query->where('updated_at', '<=', $filters['updated_to']);
        }

        if (($filters['manual_review_required'] ?? null) !== null) {
            if ((bool) $filters['manual_review_required']) {
                $query->where('status', 'manual_review');
            } else {
                $query->where('status', '!=', 'manual_review');
            }
        }

        if (($filters['has_selected_image'] ?? null) !== null) {
            if ((bool) $filters['has_selected_image']) {
                $query->whereNotNull('selected_candidate_id');
            } else {
                $query->whereNull('selected_candidate_id');
            }
        }

        if (method_exists($model, 'candidates')) {
            if (($filters['has_candidates'] ?? null) !== null) {
                $filters['has_candidates']
                    ? $query->has('candidates')
                    : $query->doesntHave('candidates');
            }

            if (($filters['source_domain'] ?? null) !== null) {
                $query->whereHas('candidates', function ($candidateQuery) use ($filters): void {
                    $candidateQuery->where('source_domain', $filters['source_domain']);
                });
            }
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $paginator = $query->orderBy($sortBy, $sortDirection)->paginate($filters['per_page'] ?? 15);

        return ProductImageDiscoveryRequestSummaryResource::collection($paginator);
    }

    public function show(int|string $request): ProductImageDiscoveryRequestResource
    {
        $record = $this->newQuery('request')->findOrFail($request);
        $this->loadAvailableRelations($record, ['bestCandidate', 'selectedCandidate']);

        return new ProductImageDiscoveryRequestResource($record);
    }

    public function retry(Request $httpRequest, int|string $request): JsonResponse
    {
        /** @var Model $record */
        $record = $this->newQuery('request')->findOrFail($request);

        $record->fill([
            'status' => 'queued',
            'rejection_reason' => null,
            'last_error' => null,
            'attempts' => ((int) $record->getAttribute('attempts')) + 1,
        ]);
        $record->save();

        $this->dispatchConfiguredJob('ingest', $record->getKey());
        $this->recordAuditEvent($record, 'request_retry_requested', [
            'requested_by' => $httpRequest->user()?->getAuthIdentifier(),
        ]);

        $this->loadAvailableRelations($record, ['bestCandidate', 'selectedCandidate']);

        return response()->json([
            'ok' => true,
            'request' => (new ProductImageDiscoveryRequestResource($record))->resolve(),
        ]);
    }
}
