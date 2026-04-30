<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Actions;

use ArrayAccess;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;

final class NormalizeProductIdentityAction
{
    public function handle(array|object $request): ProductIdentityData
    {
        $data = [
            'client_id' => $this->read($request, ['client_id', 'clientId']),
            'erp_model_id' => $this->read($request, ['erp_model_id', 'erpModelId']),
            'erp_model_color_id' => $this->read($request, ['erp_model_color_id', 'erpModelColorId']),
            'erp_model_color_size_id' => $this->read($request, ['erp_model_color_size_id', 'erpModelColorSizeId']),
            'brand' => $this->read($request, ['brand']),
            'supplier' => $this->read($request, ['supplier']),
            'sku' => $this->read($request, ['sku']),
            'supplier_sku' => $this->read($request, ['supplier_sku', 'supplierSku']),
            'model_code' => $this->read($request, ['model_code', 'modelCode']),
            'color_code' => $this->read($request, ['color_code', 'colorCode']),
            'color_name' => $this->read($request, ['color_name', 'colorName']),
            'ean' => $this->read($request, ['ean', 'barcode', 'bar_code', 'gtin', 'gtin13', 'gtin14']),
            'season' => $this->read($request, ['season']),
            'category' => $this->read($request, ['category']),
            'material' => $this->read($request, ['material']),
            'description' => $this->read($request, ['description', 'name', 'title']),
            'raw_payload' => $this->rawPayload($request),
        ];

        return ProductIdentityData::fromArray($data);
    }

    /**
     * @param list<string> $keys
     */
    private function read(array|object $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (is_array($source) && array_key_exists($key, $source)) {
                return $source[$key];
            }

            if ($source instanceof ArrayAccess && $source->offsetExists($key)) {
                return $source[$key];
            }

            if (is_object($source) && isset($source->{$key})) {
                return $source->{$key};
            }

            if (is_object($source) && method_exists($source, 'getAttribute')) {
                $value = $source->getAttribute($key);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawPayload(array|object $source): array
    {
        if (is_array($source)) {
            $raw = $source['raw_payload'] ?? $source['rawPayload'] ?? null;

            if (is_array($raw)) {
                return $raw;
            }

            return $source;
        }

        if (method_exists($source, 'toArray')) {
            $array = $source->toArray();

            if (is_array($array)) {
                return $array;
            }
        }

        return get_object_vars($source);
    }
}
