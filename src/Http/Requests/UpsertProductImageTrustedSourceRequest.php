<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class UpsertProductImageTrustedSourceRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'min:1'],
            'domain' => ['required', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'trust_score' => ['nullable', 'integer', 'between:0,100'],
            'allow_search' => ['nullable', 'boolean'],
            'allow_scraping' => ['nullable', 'boolean'],
            'allow_download' => ['nullable', 'boolean'],
            'allow_auto_publish' => ['nullable', 'boolean'],
            'allow_description_import' => ['nullable', 'boolean'],
            'respect_robots_txt' => ['nullable', 'boolean'],
            'requires_manual_review' => ['nullable', 'boolean'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
            'brand_scope' => ['nullable', 'array'],
            'supplier_scope' => ['nullable', 'array'],
            'url_patterns' => ['nullable', 'array'],
            'permission_reference' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $domain = trim(Str::lower((string) $this->input('domain', '')));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = strtok($domain, '/');
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

        $this->merge([
            'domain' => $domain,
        ]);

        $this->normalizeJsonFields(['brand_scope', 'supplier_scope', 'url_patterns']);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $domain = (string) $this->input('domain', '');

            if ($domain === '' || filter_var("https://{$domain}", FILTER_VALIDATE_URL) === false || str_contains($domain, ' ')) {
                $validator->errors()->add('domain', 'The domain field must contain a valid domain name.');
            }
        });
    }
}
