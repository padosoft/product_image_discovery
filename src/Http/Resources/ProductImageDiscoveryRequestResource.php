<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageDiscoveryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'client_id' => $this->resource->getAttribute('client_id'),
            'erp_model_id' => $this->resource->getAttribute('erp_model_id'),
            'erp_model_color_id' => $this->resource->getAttribute('erp_model_color_id'),
            'erp_model_color_size_id' => $this->resource->getAttribute('erp_model_color_size_id'),
            'status' => $this->resource->getAttribute('status'),
            'brand' => $this->resource->getAttribute('brand'),
            'supplier' => $this->resource->getAttribute('supplier'),
            'supplier_sku' => $this->resource->getAttribute('supplier_sku'),
            'ean' => $this->resource->getAttribute('ean'),
            'name' => $this->resource->getAttribute('name'),
            'title' => $this->resource->getAttribute('title'),
            'model_code' => $this->resource->getAttribute('model_code'),
            'color_code' => $this->resource->getAttribute('color_code'),
            'color_name' => $this->resource->getAttribute('color_name'),
            'final_score' => $this->resource->getAttribute('final_score'),
            'rejection_reason' => $this->resource->getAttribute('rejection_reason'),
            'attempts' => $this->resource->getAttribute('attempts'),
            'raw_payload' => $this->resource->getAttribute('raw_payload'),
            'best_candidate' => $this->when(
                $this->resource->relationLoaded('bestCandidate'),
                fn (): ?array => $this->resource->bestCandidate === null
                    ? null
                    : (new ProductImageDiscoveryCandidateResource($this->resource->bestCandidate))->resolve()
            ),
            'selected_candidate' => $this->when(
                $this->resource->relationLoaded('selectedCandidate'),
                fn (): ?array => $this->resource->selectedCandidate === null
                    ? null
                    : (new ProductImageDiscoveryCandidateResource($this->resource->selectedCandidate))->resolve()
            ),
            'created_at' => $this->resource->getAttribute('created_at'),
            'updated_at' => $this->resource->getAttribute('updated_at'),
        ];
    }
}
