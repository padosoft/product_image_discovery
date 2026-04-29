<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\ProductImageDiscovery\Http\Controllers\Api\ProductImageDiscoveryCandidateController;
use Padosoft\ProductImageDiscovery\Http\Controllers\Api\ProductImageDiscoveryRequestController;
use Padosoft\ProductImageDiscovery\Http\Controllers\Api\ProductImageDiscoverySearchProviderController;
use Padosoft\ProductImageDiscovery\Http\Controllers\Api\ProductImageDiscoverySettingController;
use Padosoft\ProductImageDiscovery\Http\Controllers\Api\ProductImageTrustedSourceController;
use Padosoft\ProductImageDiscovery\Http\Middleware\EnsureProductImageDiscoveryAbility;

Route::prefix(config('product-image-discovery.route_prefix', 'api/product-image-discovery'))
    ->middleware(config('product-image-discovery.route_middleware', ['api', 'auth:sanctum']))
    ->group(function (): void {
        Route::get('requests/search', [ProductImageDiscoveryRequestController::class, 'search'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':read');
        Route::post('requests', [ProductImageDiscoveryRequestController::class, 'store'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':write');
        Route::get('requests/{request}', [ProductImageDiscoveryRequestController::class, 'show'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':read');
        Route::post('requests/{request}/retry', [ProductImageDiscoveryRequestController::class, 'retry'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':write,review');

        Route::get('requests/{request}/candidates', [ProductImageDiscoveryCandidateController::class, 'index'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':read');
        Route::post('requests/{request}/candidates/{candidate}/approve', [ProductImageDiscoveryCandidateController::class, 'approve'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':review,write');
        Route::post('requests/{request}/candidates/{candidate}/reject', [ProductImageDiscoveryCandidateController::class, 'reject'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':review,write');

        Route::get('settings', [ProductImageDiscoverySettingController::class, 'index'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::post('settings', [ProductImageDiscoverySettingController::class, 'store'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::get('settings/{setting}', [ProductImageDiscoverySettingController::class, 'show'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::put('settings/{setting}', [ProductImageDiscoverySettingController::class, 'update'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::delete('settings/{setting}', [ProductImageDiscoverySettingController::class, 'destroy'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');

        Route::get('trusted-sources', [ProductImageTrustedSourceController::class, 'index'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::post('trusted-sources', [ProductImageTrustedSourceController::class, 'store'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::get('trusted-sources/{trustedSource}', [ProductImageTrustedSourceController::class, 'show'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::put('trusted-sources/{trustedSource}', [ProductImageTrustedSourceController::class, 'update'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::delete('trusted-sources/{trustedSource}', [ProductImageTrustedSourceController::class, 'destroy'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');

        Route::get('search-providers', [ProductImageDiscoverySearchProviderController::class, 'index'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::post('search-providers', [ProductImageDiscoverySearchProviderController::class, 'store'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::get('search-providers/{searchProvider}', [ProductImageDiscoverySearchProviderController::class, 'show'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::put('search-providers/{searchProvider}', [ProductImageDiscoverySearchProviderController::class, 'update'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
        Route::delete('search-providers/{searchProvider}', [ProductImageDiscoverySearchProviderController::class, 'destroy'])
            ->middleware(EnsureProductImageDiscoveryAbility::class . ':settings,admin');
    });
