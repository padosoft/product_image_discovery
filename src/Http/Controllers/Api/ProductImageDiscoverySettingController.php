<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Padosoft\ProductImageDiscovery\Http\Concerns\ResolvesProductImageDiscovery;
use Padosoft\ProductImageDiscovery\Http\Requests\UpsertProductImageDiscoverySettingRequest;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageDiscoverySettingResource;

final class ProductImageDiscoverySettingController extends Controller
{
    use ResolvesProductImageDiscovery;

    public function index()
    {
        return ProductImageDiscoverySettingResource::collection(
            $this->newQuery('setting')->orderBy('client_id')->orderBy('setting_key')->paginate(25)
        );
    }

    public function store(UpsertProductImageDiscoverySettingRequest $request): ProductImageDiscoverySettingResource
    {
        $record = $this->newQuery('setting')->create($request->validated());

        return new ProductImageDiscoverySettingResource($record);
    }

    public function show(int|string $setting): ProductImageDiscoverySettingResource
    {
        return new ProductImageDiscoverySettingResource($this->newQuery('setting')->findOrFail($setting));
    }

    public function update(UpsertProductImageDiscoverySettingRequest $request, int|string $setting): ProductImageDiscoverySettingResource
    {
        $record = $this->newQuery('setting')->findOrFail($setting);
        $record->fill($request->validated());
        $record->save();

        return new ProductImageDiscoverySettingResource($record);
    }

    public function destroy(int|string $setting): Response
    {
        $record = $this->newQuery('setting')->findOrFail($setting);
        $record->delete();

        return response()->noContent();
    }
}
