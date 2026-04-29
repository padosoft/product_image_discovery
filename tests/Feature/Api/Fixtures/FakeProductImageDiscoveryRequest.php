<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FakeProductImageDiscoveryRequest extends Model
{
    protected $table = 'product_image_discovery_requests';

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'verified_at' => 'datetime',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(FakeProductImageDiscoveryCandidate::class, 'request_id');
    }

    public function bestCandidate(): BelongsTo
    {
        return $this->belongsTo(FakeProductImageDiscoveryCandidate::class, 'best_candidate_id');
    }

    public function selectedCandidate(): BelongsTo
    {
        return $this->belongsTo(FakeProductImageDiscoveryCandidate::class, 'selected_candidate_id');
    }
}
