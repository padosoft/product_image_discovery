<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageDiscoverySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'client_id' => $this->resource->getAttribute('client_id'),
            'setting_key' => $this->resource->getAttribute('setting_key'),
            'setting_value' => $this->resource->getAttribute('setting_value'),
            'value_type' => $this->resource->getAttribute('value_type'),
            'description' => $this->resource->getAttribute('description'),
            'is_active' => (bool) $this->resource->getAttribute('is_active'),
            'created_at' => $this->resource->getAttribute('created_at'),
            'updated_at' => $this->resource->getAttribute('updated_at'),
        ];
    }
}
