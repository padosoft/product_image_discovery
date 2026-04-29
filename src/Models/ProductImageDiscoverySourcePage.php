<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductImageDiscoverySourcePage extends Model
{
    protected $table = 'product_image_discovery_source_pages';

    protected $fillable = [
        'client_id',
        'url_hash',
        'url',
        'domain',
        'fetch_strategy',
        'http_status',
        'title',
        'canonical_url',
        'structured_data',
        'extracted_text',
        'extracted_images',
        'robots_allowed',
        'crawl_error',
        'last_crawled_at',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'http_status' => 'integer',
        'structured_data' => 'array',
        'extracted_images' => 'array',
        'robots_allowed' => 'boolean',
        'last_crawled_at' => 'datetime',
    ];

    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        return $query->when($clientId !== null, fn (Builder $builder) => $builder->where('client_id', $clientId));
    }

    public function scopeForDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain);
    }
}
