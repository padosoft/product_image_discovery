<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Throwable;

final class UpsertProductImageSearchProviderRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        $driverRules = ['required', 'string', 'max:100'];
        $registeredDrivers = $this->registeredDrivers();

        if ($registeredDrivers !== []) {
            $driverRules[] = Rule::in($registeredDrivers);
        }

        return [
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => $driverRules,
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

    public function messages(): array
    {
        $registeredDrivers = $this->registeredDrivers();

        return [
            'driver.in' => $registeredDrivers === []
                ? 'The selected driver is not registered.'
                : 'The selected driver is not registered. Registered drivers: ' . implode(', ', $registeredDrivers) . '.',
        ];
    }

    /**
     * Driver names registered in the search provider manager; empty when the
     * registry cannot be resolved (older manager versions without drivers()).
     *
     * @return array<int, string>
     */
    private function registeredDrivers(): array
    {
        try {
            $manager = $this->container?->make(SearchProviderManager::class);
        } catch (Throwable) {
            return [];
        }

        if (! $manager instanceof SearchProviderManager || ! method_exists($manager, 'drivers')) {
            return [];
        }

        return $manager->drivers();
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
