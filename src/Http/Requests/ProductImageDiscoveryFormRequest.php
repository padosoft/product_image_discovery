<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ProductImageDiscoveryFormRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    protected array $invalidJsonFields = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function normalizeJsonFields(array $fields): void
    {
        foreach ($fields as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || is_array($value)) {
                continue;
            }

            if (! is_string($value)) {
                $this->invalidJsonFields[] = $field;
                continue;
            }

            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->invalidJsonFields[] = $field;
                continue;
            }

            $this->merge([$field => $decoded]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_unique($this->invalidJsonFields) as $field) {
                $validator->errors()->add($field, 'The field must contain valid JSON.');
            }
        });
    }
}
