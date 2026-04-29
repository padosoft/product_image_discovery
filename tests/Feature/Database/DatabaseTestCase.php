<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Feature\Database;

use Orchestra\Testbench\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
        $this->artisan('migrate')->run();

        if (! class_exists(\Padosoft\ProductImageDiscovery\Database\Seeders\ProductImageDiscoveryDefaultsSeeder::class)) {
            require_once __DIR__.'/../../../database/seeders/ProductImageDiscoveryDefaultsSeeder.php';
        }
    }
}
