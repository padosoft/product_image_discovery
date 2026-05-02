<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Padosoft\ProductImageDiscovery\ProductImageDiscoveryServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        $providers = [
            ProductImageDiscoveryServiceProvider::class,
        ];

        if (class_exists(\Laravel\Ai\AiServiceProvider::class)) {
            $providers[] = \Laravel\Ai\AiServiceProvider::class;
        }

        if (class_exists(\Padosoft\LaravelAiRegolo\LaravelAiRegoloServiceProvider::class)) {
            $providers[] = \Padosoft\LaravelAiRegolo\LaravelAiRegoloServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
