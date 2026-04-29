<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageDiscoverySearchProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'code' => $this->resource->getAttribute('code'),
            'name' => $this->resource->getAttribute('name'),
            'driver' => $this->resource->getAttribute('driver'),
            'base_url' => $this->resource->getAttribute('base_url'),
            'config' => $this->resource->getAttribute('config'),
            'priority' => $this->resource->getAttribute('priority'),
            'timeout_seconds' => $this->resource->getAttribute('timeout_seconds'),
            'rate_limit_per_minute' => $this->resource->getAttribute('rate_limit_per_minute'),
            'is_active' => (bool) $this->resource->getAttribute('is_active'),
            'has_api_key' => $this->resource->getAttribute('api_key_encrypted') !== null,
            'has_api_secret' => $this->resource->getAttribute('api_secret_encrypted') !== null,
            'created_at' => $this->resource->getAttribute('created_at'),
            'updated_at' => $this->resource->getAttribute('updated_at'),
        ];
    }
}
