<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpsertProductImageDiscoverySettingRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'min:1'],
            'setting_key' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'setting_value' => ['present'],
            'value_type' => ['nullable', Rule::in(['json', 'string', 'integer', 'float', 'boolean', 'null'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('value_type', 'json') === 'json' && $this->exists('setting_value')) {
            $value = $this->input('setting_value');

            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge(['setting_value' => $decoded]);
                } else {
                    $this->invalidJsonFields[] = 'setting_value';
                }
            }
        }

        if (! $this->exists('value_type')) {
            $this->merge(['value_type' => 'json']);
        }
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if (! $this->exists('setting_value')) {
                $validator->errors()->add('setting_value', 'The setting_value field is required.');
            }
        });
    }
}
