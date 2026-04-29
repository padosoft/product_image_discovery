<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Tests\Feature\Api\Fixtures\FakeIngestJob;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryCandidate;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryEvent;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoveryRequest;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoverySearchProvider;
use Tests\Feature\Api\Fixtures\FakeProductImageDiscoverySetting;
use Tests\Feature\Api\Fixtures\FakeProductImageTrustedSource;
use Tests\Feature\Api\Fixtures\FakeUser;

abstract class ApiTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        require dirname(__DIR__, 3) . '/routes/api.php';
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:6vO7NO1A5fADG7n2AFD+a83H8XTur2qxGn8pY/+bexQ=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => FakeUser::class,
        ]);
        $app['config']->set('product-image-discovery.abilities', [
            'read' => 'product-image-discovery:read',
            'write' => 'product-image-discovery:write',
            'admin' => 'product-image-discovery:admin',
            'review' => 'product-image-discovery:review',
            'settings' => 'product-image-discovery:settings',
        ]);
        $app['config']->set('product-image-discovery.models', [
            'request' => FakeProductImageDiscoveryRequest::class,
            'candidate' => FakeProductImageDiscoveryCandidate::class,
            'setting' => FakeProductImageDiscoverySetting::class,
            'trusted_source' => FakeProductImageTrustedSource::class,
            'search_provider' => FakeProductImageDiscoverySearchProvider::class,
            'event' => FakeProductImageDiscoveryEvent::class,
        ]);
        $app['config']->set('product-image-discovery.jobs.ingest', FakeIngestJob::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('product_image_discovery_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('erp_model_id');
            $table->string('erp_model_color_id');
            $table->string('erp_model_color_size_id')->nullable();
            $table->string('brand')->nullable();
            $table->string('supplier')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->string('ean')->nullable();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('model_code')->nullable();
            $table->string('color_code')->nullable();
            $table->string('color_name')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('best_candidate_id')->nullable();
            $table->unsignedBigInteger('selected_candidate_id')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'erp_model_color_id']);
        });

        Schema::create('product_image_discovery_candidates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('source_domain');
            $table->text('source_page_url');
            $table->text('image_url');
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('status')->default('candidate');
            $table->string('rejection_reason')->nullable();
            $table->json('evidence')->nullable();
            $table->json('structured_data')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->json('quality_analysis')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_image_discovery_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('setting_key', 150);
            $table->json('setting_value');
            $table->string('value_type', 50)->default('json');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['client_id', 'setting_key']);
        });

        Schema::create('product_image_trusted_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('domain');
            $table->string('source_name')->nullable();
            $table->string('source_type', 80)->default('website');
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->boolean('allow_search')->default(true);
            $table->boolean('allow_scraping')->default(true);
            $table->boolean('allow_download')->default(true);
            $table->boolean('allow_auto_publish')->default(false);
            $table->boolean('allow_description_import')->default(false);
            $table->boolean('respect_robots_txt')->nullable();
            $table->boolean('requires_manual_review')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->json('brand_scope')->nullable();
            $table->json('supplier_scope')->nullable();
            $table->json('url_patterns')->nullable();
            $table->text('permission_reference')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_image_search_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('driver', 100);
            $table->text('base_url')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('api_secret_encrypted')->nullable();
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('timeout_seconds')->default(15);
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_image_discovery_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->string('event_type');
            $table->string('level')->default('info');
            $table->string('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    protected function authenticate(array $abilityKeys): FakeUser
    {
        $user = FakeUser::query()->create([
            'name' => 'API Tester',
            'email' => uniqid('tester_', true) . '@example.test',
        ]);

        Sanctum::actingAs($user, array_map(
            fn (string $abilityKey): string => (string) config("product-image-discovery.abilities.{$abilityKey}", $abilityKey),
            $abilityKeys
        ));

        return $user;
    }

    protected function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 10,
            'erp_model_id' => 'MODEL-001',
            'erp_model_color_id' => 'MODEL-001-BLK',
            'erp_model_color_size_id' => 'MODEL-001-BLK-42',
            'brand' => 'Pado',
            'supplier' => 'Supplier X',
            'supplier_sku' => 'SUP-001',
            'ean' => '8050000000012',
            'name' => 'Sample Sneaker',
            'model_code' => 'MODEL-001',
            'color_code' => 'BLK',
            'color_name' => 'Black',
            'metadata' => ['season' => 'SS26'],
        ], $overrides);
    }
}
