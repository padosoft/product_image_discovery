<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageDiscoveryRequestSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'client_id' => $this->resource->getAttribute('client_id'),
            'erp_model_id' => $this->resource->getAttribute('erp_model_id'),
            'erp_model_color_id' => $this->resource->getAttribute('erp_model_color_id'),
            'status' => $this->resource->getAttribute('status'),
            'brand' => $this->resource->getAttribute('brand'),
            'supplier' => $this->resource->getAttribute('supplier'),
            'final_score' => $this->resource->getAttribute('final_score'),
            'rejection_reason' => $this->resource->getAttribute('rejection_reason'),
            'selected_candidate_id' => $this->resource->getAttribute('selected_candidate_id'),
            'best_candidate_id' => $this->resource->getAttribute('best_candidate_id'),
            'created_at' => $this->resource->getAttribute('created_at'),
            'updated_at' => $this->resource->getAttribute('updated_at'),
        ];
    }
}
