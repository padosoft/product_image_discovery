<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductImageDiscoverySetting extends Model
{
    protected $table = 'product_image_discovery_settings';

    protected $fillable = [
        'client_id',
        'setting_key',
        'setting_value',
        'value_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected function settingValue(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): mixed => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value,
            set: static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        return $query->when($clientId !== null, fn (Builder $builder) => $builder->where('client_id', $clientId));
    }

    public function scopeForClientOrGlobal(Builder $query, ?int $clientId): Builder
    {
        return $query
            ->when(
                $clientId !== null,
                fn (Builder $builder) => $builder->where(static function (Builder $nested) use ($clientId): void {
                    $nested->where('client_id', $clientId)->orWhereNull('client_id');
                }),
                fn (Builder $builder) => $builder->whereNull('client_id'),
            )
            ->orderByRaw('case when client_id is null then 1 else 0 end');
    }

    public static function resolveValue(string $settingKey, ?int $clientId = null, mixed $default = null): mixed
    {
        return static::query()
            ->active()
            ->where('setting_key', $settingKey)
            ->forClientOrGlobal($clientId)
            ->first()?->setting_value ?? $default;
    }
}
