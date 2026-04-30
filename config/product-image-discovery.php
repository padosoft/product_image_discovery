<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Jobs\IngestProductImageDiscoveryJob;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryCandidate;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryEvent;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoverySetting;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Models\ProductImageTrustedSource;

return [
    'route_prefix' => env('PRODUCT_IMAGE_DISCOVERY_ROUTE_PREFIX', 'api/product-image-discovery'),
    'route_middleware' => ['api', 'auth:sanctum'],

    'abilities' => [
        'read' => 'product-image-discovery:read',
        'write' => 'product-image-discovery:write',
        'admin' => 'product-image-discovery:admin',
        'review' => 'product-image-discovery:review',
        'settings' => 'product-image-discovery:settings',
    ],

    'models' => [
        'request' => ProductImageDiscoveryRequest::class,
        'candidate' => ProductImageDiscoveryCandidate::class,
        'setting' => ProductImageDiscoverySetting::class,
        'trusted_source' => ProductImageTrustedSource::class,
        'search_provider' => ProductImageSearchProvider::class,
        'event' => ProductImageDiscoveryEvent::class,
    ],

    'jobs' => [
        'ingest' => IngestProductImageDiscoveryJob::class,
    ],

    'queues' => [
        'ingest' => 'image-discovery-ingest',
        'search' => 'image-discovery-search',
        'fetch' => 'image-discovery-fetch',
        'extract' => 'image-discovery-extract',
        'verify' => 'image-discovery-verify',
        'download' => 'image-discovery-download',
        'quality' => 'image-discovery-quality',
        'enhance' => 'image-discovery-enhance',
        'description' => 'image-discovery-description',
    ],

    'storage' => [
        'disk' => env('PRODUCT_IMAGE_DISCOVERY_STORAGE_DISK', 'local'),
    ],

    'defaults' => [
        'search_max_queries_per_product' => 8,
        'search_max_results_per_query' => 20,
        'quality_min_width' => 800,
        'quality_min_height' => 800,
        'auto_publish_threshold' => 90,
        'manual_review_threshold' => 60,
        'reject_below_threshold' => 60,
    ],

    'ai' => [
        'enabled' => env('PRODUCT_IMAGE_DISCOVERY_AI_ENABLED', false),
        'provider' => env('PRODUCT_IMAGE_DISCOVERY_AI_PROVIDER', 'anthropic'),
        'timeout' => (int) env('PRODUCT_IMAGE_DISCOVERY_AI_TIMEOUT', 45),
        'fail_silently' => env('PRODUCT_IMAGE_DISCOVERY_AI_FAIL_SILENTLY', true),
        'attach_remote_image' => env('PRODUCT_IMAGE_DISCOVERY_AI_ATTACH_REMOTE_IMAGE', false),
        'vision_model' => env('PRODUCT_IMAGE_DISCOVERY_AI_VISION_MODEL'),
        'description_model' => env('PRODUCT_IMAGE_DISCOVERY_AI_DESCRIPTION_MODEL'),
        'providers' => [
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'base_url' => env('OPENAI_URL', env('OPENAI_BASE_URL')),
            ],
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'base_url' => env('ANTHROPIC_URL', env('ANTHROPIC_BASE_URL')),
            ],
            'openrouter' => [
                'api_key' => env('OPENROUTER_API_KEY'),
                'base_url' => env('OPENROUTER_URL', env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1')),
            ],
        ],
    ],
];
