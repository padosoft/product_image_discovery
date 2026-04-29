<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class FakeProductImageDiscoverySearchProvider extends Model
{
    protected $table = 'product_image_search_providers';

    protected $guarded = [];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];
}
