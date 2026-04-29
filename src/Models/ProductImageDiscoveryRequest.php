<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;

class ProductImageDiscoveryRequest extends Model
{
    protected $table = 'product_image_discovery_requests';

    protected $fillable = [
        'client_id',
        'erp_model_id',
        'erp_model_color_id',
        'erp_model_color_size_id',
        'identity_hash',
        'brand',
        'supplier',
        'sku',
        'supplier_sku',
        'model_code',
        'color_code',
        'color_name',
        'ean',
        'season',
        'category',
        'material',
        'price',
        'currency',
        'raw_payload',
        'status',
        'best_candidate_id',
        'selected_candidate_id',
        'final_score',
        'rejection_reason',
        'last_error',
        'attempts',
        'search_started_at',
        'search_completed_at',
        'verified_at',
        'published_at',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'raw_payload' => 'array',
        'status' => ProductImageDiscoveryRequestStatus::class,
        'best_candidate_id' => 'integer',
        'selected_candidate_id' => 'integer',
        'final_score' => 'integer',
        'rejection_reason' => ProductImageDiscoveryRejectionReason::class,
        'attempts' => 'integer',
        'price' => 'decimal:2',
        'search_started_at' => 'datetime',
        'search_completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(ProductImageDiscoveryCandidate::class, 'request_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductImageDiscoveryEvent::class, 'request_id');
    }

    public function bestCandidate(): BelongsTo
    {
        return $this->belongsTo(ProductImageDiscoveryCandidate::class, 'best_candidate_id');
    }

    public function selectedCandidate(): BelongsTo
    {
        return $this->belongsTo(ProductImageDiscoveryCandidate::class, 'selected_candidate_id');
    }

    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        return $query->when($clientId !== null, fn (Builder $builder) => $builder->where('client_id', $clientId));
    }

    public function scopeWithStatus(Builder $query, ProductImageDiscoveryRequestStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ProductImageDiscoveryRequestStatus ? $status->value : $status);
    }

    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (ProductImageDiscoveryRequestStatus $status): string => $status->value,
            array_filter(
                ProductImageDiscoveryRequestStatus::cases(),
                static fn (ProductImageDiscoveryRequestStatus $status): bool => $status->isTerminal(),
            ),
        ));
    }
}
