<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageTrustedSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'client_id' => $this->resource->getAttribute('client_id'),
            'domain' => $this->resource->getAttribute('domain'),
            'source_name' => $this->resource->getAttribute('source_name'),
            'source_type' => $this->resource->getAttribute('source_type'),
            'trust_score' => $this->resource->getAttribute('trust_score'),
            'allow_search' => (bool) $this->resource->getAttribute('allow_search'),
            'allow_scraping' => (bool) $this->resource->getAttribute('allow_scraping'),
            'allow_download' => (bool) $this->resource->getAttribute('allow_download'),
            'allow_auto_publish' => (bool) $this->resource->getAttribute('allow_auto_publish'),
            'allow_description_import' => (bool) $this->resource->getAttribute('allow_description_import'),
            'respect_robots_txt' => $this->resource->getAttribute('respect_robots_txt'),
            'requires_manual_review' => (bool) $this->resource->getAttribute('requires_manual_review'),
            'rate_limit_per_minute' => $this->resource->getAttribute('rate_limit_per_minute'),
            'brand_scope' => $this->resource->getAttribute('brand_scope'),
            'supplier_scope' => $this->resource->getAttribute('supplier_scope'),
            'url_patterns' => $this->resource->getAttribute('url_patterns'),
            'permission_reference' => $this->resource->getAttribute('permission_reference'),
            'notes' => $this->resource->getAttribute('notes'),
            'is_active' => (bool) $this->resource->getAttribute('is_active'),
            'created_at' => $this->resource->getAttribute('created_at'),
            'updated_at' => $this->resource->getAttribute('updated_at'),
        ];
    }
}
