<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

use Illuminate\Support\Str;

final class UpsertProductImageSearchProviderRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:2000'],
            'api_key' => ['nullable', 'string', 'max:4000'],
            'api_secret' => ['nullable', 'string', 'max:4000'],
            'config' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('code')) {
            $this->merge([
                'code' => Str::lower(trim((string) $this->input('code'))),
            ]);
        }

        $this->normalizeJsonFields(['config']);
    }
}
