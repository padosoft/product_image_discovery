<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs\Concerns;

trait ResolvesQueueName
{
    protected function queueNameFor(string $stage): string
    {
        $default = 'product-image-discovery-'.$stage;

        if (! function_exists('config')) {
            return $default;
        }

        try {
            $configValue = config('product-image-discovery.queues.'.$stage)
                ?? config('product_image_discovery.queues.'.$stage)
                ?? $default;
        } catch (\Throwable) {
            return $default;
        }

        return is_string($configValue) && $configValue !== '' ? $configValue : $default;
    }
}
