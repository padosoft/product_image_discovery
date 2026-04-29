<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Jobs\Concerns;

trait DispatchesPipelineJobs
{
    protected function dispatchIfPossible(object $job): void
    {
        if (! function_exists('dispatch')) {
            return;
        }

        try {
            dispatch($job);
        } catch (\Throwable) {
            // Standalone pipeline tests can execute jobs without a Laravel bus binding.
        }
    }
}
