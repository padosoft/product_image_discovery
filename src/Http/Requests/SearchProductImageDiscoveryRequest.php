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
            'barcode' => ['nullable', 'string', 'max:64'],
            'bar_code' => ['nullable', 'string', 'max:64'],
            'gtin' => ['nullable', 'string', 'max:64'],
            'gtin13' => ['nullable', 'string', 'max:64'],
            'gtin14' => ['nullable', 'string', 'max:64'],
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

    protected function prepareForValidation(): void
    {
        if ($this->filled('ean')) {
            $this->merge(['ean' => $this->normalizeBarcode($this->input('ean'))]);
        }

        if (! $this->filled('ean')) {
            foreach (['barcode', 'bar_code', 'gtin', 'gtin13', 'gtin14'] as $field) {
                if (! $this->filled($field)) {
                    continue;
                }

                $this->merge(['ean' => $this->normalizeBarcode($this->input($field))]);

                break;
            }
        }
    }

    private function normalizeBarcode(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits === '' ? null : $digits;
    }
}
