<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

trait ResolvesProductImageDiscovery
{
    protected function abilityName(string $key): string
    {
        return (string) config("product-image-discovery.abilities.{$key}", "product-image-discovery:{$key}");
    }

    protected function modelClass(string $key): string
    {
        $defaults = [
            'request' => 'Padosoft\\ProductImageDiscovery\\Models\\ProductImageDiscoveryRequest',
            'candidate' => 'Padosoft\\ProductImageDiscovery\\Models\\ProductImageDiscoveryCandidate',
            'setting' => 'Padosoft\\ProductImageDiscovery\\Models\\ProductImageDiscoverySetting',
            'trusted_source' => 'Padosoft\\ProductImageDiscovery\\Models\\ProductImageTrustedSource',
            'search_provider' => 'Padosoft\\ProductImageDiscovery\\Models\\ProductImageSearchProvider',
            'event' => 'Padosoft\\ProductImageDiscovery\\Models\\ProductImageDiscoveryEvent',
        ];

        return (string) config("product-image-discovery.models.{$key}", $defaults[$key] ?? '');
    }

    protected function newQuery(string $key): Builder
    {
        $modelClass = $this->modelClass($key);

        if ($modelClass === '' || ! class_exists($modelClass)) {
            throw new RuntimeException("Product Image Discovery model [{$key}] is not available.");
        }

        return $modelClass::query();
    }

    protected function newModel(string $key): Model
    {
        $modelClass = $this->modelClass($key);

        if ($modelClass === '' || ! class_exists($modelClass)) {
            throw new RuntimeException("Product Image Discovery model [{$key}] is not available.");
        }

        return new $modelClass();
    }

    protected function loadAvailableRelations(Model $model, array $relations): void
    {
        $available = array_values(array_filter($relations, static fn (string $relation): bool => method_exists($model, $relation)));

        if ($available !== []) {
            $model->loadMissing($available);
        }
    }

    protected function availableRelationsFor(string $key, array $relations): array
    {
        $model = $this->newModel($key);

        return array_values(array_filter($relations, static fn (string $relation): bool => method_exists($model, $relation)));
    }

    protected function dispatchConfiguredJob(string $jobKey, mixed ...$arguments): void
    {
        $jobClass = config("product-image-discovery.jobs.{$jobKey}");

        if (! is_string($jobClass) || $jobClass === '' || ! class_exists($jobClass)) {
            return;
        }

        Bus::dispatch(new $jobClass(...$arguments));
    }

    protected function recordAuditEvent(Model $request, string $eventType, array $context = [], ?Model $candidate = null): void
    {
        $eventClass = $this->modelClass('event');

        if (! class_exists($eventClass)) {
            return;
        }

        $payload = [
            'request_id' => $request->getKey(),
            'candidate_id' => $candidate?->getKey(),
            'event_type' => $eventType,
            'level' => 'info',
            'message' => str_replace('_', ' ', $eventType),
            'context' => $context,
        ];

        $eventClass::query()->create($payload);
    }
}
