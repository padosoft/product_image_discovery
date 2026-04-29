<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductImageSearchProvider extends Model
{
    protected $table = 'product_image_search_providers';

    protected $fillable = [
        'code',
        'name',
        'driver',
        'base_url',
        'api_key_encrypted',
        'api_secret_encrypted',
        'config',
        'priority',
        'timeout_seconds',
        'rate_limit_per_minute',
        'is_active',
    ];

    protected $casts = [
        'api_key_encrypted' => 'encrypted',
        'api_secret_encrypted' => 'encrypted',
        'config' => 'array',
        'priority' => 'integer',
        'timeout_seconds' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }
}
