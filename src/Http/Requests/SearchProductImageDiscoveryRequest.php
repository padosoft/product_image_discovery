<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

use Illuminate\Validation\Rule;

final class SearchProductImageDiscoveryRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:191'],
            'supplier' => ['nullable', 'string', 'max:191'],
            'erp_model_id' => ['nullable', 'string', 'max:191'],
            'erp_model_color_id' => ['nullable', 'string', 'max:191'],
            'ean' => ['nullable', 'string', 'max:64'],
            'source_domain' => ['nullable', 'string', 'max:255'],
            'rejection_reason' => ['nullable', 'string', 'max:100'],
            'min_score' => ['nullable', 'numeric', 'between:0,100'],
            'max_score' => ['nullable', 'numeric', 'between:0,100'],
            'has_candidates' => ['nullable', 'boolean'],
            'has_selected_image' => ['nullable', 'boolean'],
            'manual_review_required' => ['nullable', 'boolean'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'updated_from' => ['nullable', 'date'],
            'updated_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'updated_at', 'final_score', 'status', 'brand', 'supplier', 'client_id'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
