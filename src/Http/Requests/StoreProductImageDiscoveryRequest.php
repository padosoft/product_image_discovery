<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

final class StoreProductImageDiscoveryRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'min:1'],
            'erp_model_id' => ['required', 'string', 'max:191'],
            'erp_model_color_id' => ['required', 'string', 'max:191'],
            'erp_model_color_size_id' => ['nullable', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:191'],
            'supplier' => ['nullable', 'string', 'max:191'],
            'supplier_sku' => ['nullable', 'string', 'max:191'],
            'ean' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'bar_code' => ['nullable', 'string', 'max:64'],
            'gtin' => ['nullable', 'string', 'max:64'],
            'gtin13' => ['nullable', 'string', 'max:64'],
            'gtin14' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'model_code' => ['nullable', 'string', 'max:191'],
            'color_code' => ['nullable', 'string', 'max:191'],
            'color_name' => ['nullable', 'string', 'max:191'],
            'season' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
            'attributes' => ['nullable', 'array'],
            'raw_payload' => ['nullable', 'array'],
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
