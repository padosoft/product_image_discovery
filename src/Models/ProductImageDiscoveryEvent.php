<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImageDiscoveryEvent extends Model
{
    protected $table = 'product_image_discovery_events';

    public const UPDATED_AT = null;

    protected $fillable = [
        'request_id',
        'candidate_id',
        'event_type',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'request_id' => 'integer',
        'candidate_id' => 'integer',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProductImageDiscoveryRequest::class, 'request_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProductImageDiscoveryCandidate::class, 'candidate_id');
    }

    public function scopeForRequest(Builder $query, int $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeForCandidate(Builder $query, int $candidateId): Builder
    {
        return $query->where('candidate_id', $candidateId);
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('created_at')->orderBy('id');
    }
}
