<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductImageTrustedSource extends Model
{
    protected $table = 'product_image_trusted_sources';

    protected $fillable = [
        'client_id',
        'domain',
        'source_name',
        'source_type',
        'trust_score',
        'allow_search',
        'allow_scraping',
        'allow_download',
        'allow_auto_publish',
        'allow_description_import',
        'respect_robots_txt',
        'requires_manual_review',
        'rate_limit_per_minute',
        'brand_scope',
        'supplier_scope',
        'url_patterns',
        'permission_reference',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'trust_score' => 'integer',
        'allow_search' => 'boolean',
        'allow_scraping' => 'boolean',
        'allow_download' => 'boolean',
        'allow_auto_publish' => 'boolean',
        'allow_description_import' => 'boolean',
        'respect_robots_txt' => 'boolean',
        'requires_manual_review' => 'boolean',
        'rate_limit_per_minute' => 'integer',
        'brand_scope' => 'array',
        'supplier_scope' => 'array',
        'url_patterns' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        return $query->when($clientId !== null, fn (Builder $builder) => $builder->where('client_id', $clientId));
    }

    public function scopeForDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain);
    }

    public function scopeAllowedForSearch(Builder $query): Builder
    {
        return $query->where('allow_search', true);
    }
}
