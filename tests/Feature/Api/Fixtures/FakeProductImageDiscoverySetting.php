<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class FakeProductImageDiscoverySetting extends Model
{
    protected $table = 'product_image_discovery_settings';

    protected $guarded = [];

    protected $casts = [
        'setting_value' => 'array',
        'is_active' => 'boolean',
    ];
}
