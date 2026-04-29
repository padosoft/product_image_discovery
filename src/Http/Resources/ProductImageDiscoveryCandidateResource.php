<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageDiscoveryCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'request_id' => $this->resource->getAttribute('request_id'),
            'client_id' => $this->resource->getAttribute('client_id'),
            'source_domain' => $this->resource->getAttribute('source_domain'),
            'source_page_url' => $this->resource->getAttribute('source_page_url'),
            'image_url' => $this->resource->getAttribute('image_url'),
            'status' => $this->resource->getAttribute('status'),
            'rejection_reason' => $this->resource->getAttribute('rejection_reason'),
            'final_score' => $this->resource->getAttribute('final_score'),
            'width' => $this->resource->getAttribute('width'),
            'height' => $this->resource->getAttribute('height'),
            'mime_type' => $this->resource->getAttribute('mime_type'),
            'file_size' => $this->resource->getAttribute('file_size'),
            'evidence' => $this->resource->getAttribute('evidence'),
            'structured_data' => $this->resource->getAttribute('structured_data'),
            'ai_analysis' => $this->resource->getAttribute('ai_analysis'),
            'quality_analysis' => $this->resource->getAttribute('quality_analysis'),
            'downloaded_at' => $this->resource->getAttribute('downloaded_at'),
            'verified_at' => $this->resource->getAttribute('verified_at'),
            'created_at' => $this->resource->getAttribute('created_at'),
            'updated_at' => $this->resource->getAttribute('updated_at'),
        ];
    }
}
