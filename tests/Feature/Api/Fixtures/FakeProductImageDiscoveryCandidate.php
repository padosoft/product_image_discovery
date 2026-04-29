<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FakeProductImageDiscoveryCandidate extends Model
{
    protected $table = 'product_image_discovery_candidates';

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'structured_data' => 'array',
        'ai_analysis' => 'array',
        'quality_analysis' => 'array',
        'downloaded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(FakeProductImageDiscoveryRequest::class, 'request_id');
    }
}
