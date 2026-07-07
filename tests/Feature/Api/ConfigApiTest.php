<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Crypt;
use Padosoft\LaravelAiSearchProviders\CallableSearchProviderFactory;
use Padosoft\LaravelAiSearchProviders\Data\SearchProviderDefinition;
use Padosoft\LaravelAiSearchProviders\Providers\FakeSearchProvider;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Tests\Support\InMemorySearchProviderConfigRepository;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoverySearchProvider;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoverySetting;
use Tests\Feature\Api\Fixtures\FakeProductImageTrustedSource;

final class ConfigApiTest extends ApiTestCase
{
    public function test_settings_routes_require_settings_or_admin_ability(): void
    {
        $this->authenticate(['read']);

        $this->getJson('/api/product-image-discovery/settings')
            ->assertForbidden();
    }

    public function test_settings_validation_and_crud_happy_path(): void
    {
        $this->authenticate(['settings']);

        $this->postJson('/api/product-image-discovery/settings', [
            'setting_key' => 'matching.weights.source_score',
            'setting_value' => '{invalid',
            'value_type' => 'json',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['setting_value']);

        $this->postJson('/api/product-image-discovery/settings', [
            'client_id' => 10,
            'setting_key' => 'matching.weights.source_score',
            'setting_value' => ['value' => 25],
            'value_type' => 'json',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.setting_key', 'matching.weights.source_score');

        $setting = FakeProductImageDiscoverySetting::query()->firstOrFail();

        $this->putJson('/api/product-image-discovery/settings/' . $setting->getKey(), [
            'client_id' => 10,
            'setting_key' => 'matching.weights.source_score',
            'setting_value' => ['value' => 30],
            'value_type' => 'json',
        ])->assertOk()
            ->assertJsonPath('data.setting_value.value', 30);

        $this->deleteJson('/api/product-image-discovery/settings/' . $setting->getKey())
            ->assertNoContent();
    }

    public function test_trusted_sources_validate_domain_and_trust_score(): void
    {
        $this->authenticate(['settings']);

        $this->postJson('/api/product-image-discovery/trusted-sources', [
            'domain' => 'not a domain',
            'trust_score' => 120,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['domain', 'trust_score']);
    }

    public function test_search_providers_reject_non_registered_driver(): void
    {
        $this->app->singleton(SearchProviderManager::class, static fn (): SearchProviderManager => new SearchProviderManager(
            repository: new InMemorySearchProviderConfigRepository([]),
            factories: [
                'brave' => new CallableSearchProviderFactory(
                    static fn (SearchProviderDefinition $definition): FakeSearchProvider => FakeSearchProvider::fromDefinition($definition),
                ),
            ],
        ));

        $this->authenticate(['admin']);

        $this->postJson('/api/product-image-discovery/search-providers', [
            'code' => 'serpapi',
            'name' => 'SerpApi',
            'driver' => 'serpapi',
            'base_url' => 'https://serpapi.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['driver']);

        $this->postJson('/api/product-image-discovery/search-providers', [
            'code' => 'brave',
            'name' => 'Brave Search',
            'driver' => 'brave',
            'base_url' => 'https://api.search.brave.com',
        ])->assertCreated();
    }

    public function test_trusted_sources_and_search_providers_crud_happy_path_without_exposing_secrets(): void
    {
        $this->authenticate(['admin']);

        $this->postJson('/api/product-image-discovery/trusted-sources', [
            'client_id' => 10,
            'domain' => 'https://www.Supplier.Example/catalog',
            'source_name' => 'Supplier',
            'trust_score' => 90,
            'allow_auto_publish' => true,
            'url_patterns' => ['product_url_patterns' => ['https://supplier.example/{sku}']],
        ])->assertCreated()
            ->assertJsonPath('data.domain', 'supplier.example');

        $trustedSource = FakeProductImageTrustedSource::query()->firstOrFail();
        $this->assertSame('supplier.example', $trustedSource->domain);

        $this->postJson('/api/product-image-discovery/search-providers', [
            'code' => 'BRAVE',
            'name' => 'Brave Search',
            'driver' => 'brave',
            'base_url' => 'https://api.search.brave.com',
            'api_key' => 'secret-key',
            'api_secret' => 'secret-secret',
            'config' => ['supports_image_search' => true],
            'priority' => 10,
            'timeout_seconds' => 20,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonMissingPath('data.api_key')
            ->assertJsonMissingPath('data.api_secret')
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonPath('data.has_api_secret', true);

        $provider = FakeProductImageDiscoverySearchProvider::query()->firstOrFail();
        $this->assertNotSame('secret-key', $provider->api_key_encrypted);
        $this->assertSame('secret-key', Crypt::decryptString($provider->api_key_encrypted));

        $this->putJson('/api/product-image-discovery/search-providers/' . $provider->getKey(), [
            'code' => 'brave',
            'name' => 'Brave Search Updated',
            'driver' => 'brave',
            'base_url' => 'https://api.search.brave.com',
            'config' => '{"supports_image_search":true}',
            'priority' => 5,
            'timeout_seconds' => 15,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Brave Search Updated');

        $this->deleteJson('/api/product-image-discovery/trusted-sources/' . $trustedSource->getKey())
            ->assertNoContent();
        $this->deleteJson('/api/product-image-discovery/search-providers/' . $provider->getKey())
            ->assertNoContent();
    }
}
