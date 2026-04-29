<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class FakeProductImageDiscoveryEvent extends Model
{
    protected $table = 'product_image_discovery_events';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];
}
