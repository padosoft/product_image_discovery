<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class FakeProductImageTrustedSource extends Model
{
    protected $table = 'product_image_trusted_sources';

    protected $guarded = [];

    protected $casts = [
        'allow_search' => 'boolean',
        'allow_scraping' => 'boolean',
        'allow_download' => 'boolean',
        'allow_auto_publish' => 'boolean',
        'allow_description_import' => 'boolean',
        'respect_robots_txt' => 'boolean',
        'requires_manual_review' => 'boolean',
        'brand_scope' => 'array',
        'supplier_scope' => 'array',
        'url_patterns' => 'array',
        'is_active' => 'boolean',
    ];
}
