<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRejectionReason;

class ProductImageDiscoveryCandidate extends Model
{
    protected $table = 'product_image_discovery_candidates';

    protected $fillable = [
        'request_id',
        'client_id',
        'fingerprint',
        'source_domain',
        'source_page_url',
        'image_url',
        'local_original_path',
        'local_processed_path',
        'source_resolver',
        'search_provider',
        'source_trust_score',
        'textual_match_score',
        'structured_match_score',
        'visual_match_score',
        'quality_score',
        'risk_penalty',
        'final_score',
        'width',
        'height',
        'mime_type',
        'file_size',
        'sha256',
        'phash',
        'status',
        'rejection_reason',
        'evidence',
        'structured_data',
        'ai_analysis',
        'quality_analysis',
        'downloaded_at',
        'verified_at',
        'quality_checked_at',
    ];

    protected $casts = [
        'request_id' => 'integer',
        'client_id' => 'integer',
        'source_trust_score' => 'integer',
        'textual_match_score' => 'integer',
        'structured_match_score' => 'integer',
        'visual_match_score' => 'integer',
        'quality_score' => 'integer',
        'risk_penalty' => 'integer',
        'final_score' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'status' => ProductImageDiscoveryCandidateStatus::class,
        'rejection_reason' => ProductImageDiscoveryRejectionReason::class,
        'evidence' => 'array',
        'structured_data' => 'array',
        'ai_analysis' => 'array',
        'quality_analysis' => 'array',
        'downloaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'quality_checked_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProductImageDiscoveryRequest::class, 'request_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductImageDiscoveryEvent::class, 'candidate_id');
    }

    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        return $query->when($clientId !== null, fn (Builder $builder) => $builder->where('client_id', $clientId));
    }

    public function scopeForRequest(Builder $query, int $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeWithStatus(Builder $query, ProductImageDiscoveryCandidateStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ProductImageDiscoveryCandidateStatus ? $status->value : $status);
    }

    public function scopeOrderedByScore(Builder $query): Builder
    {
        return $query->orderByDesc('final_score')->orderByDesc('id');
    }
}
