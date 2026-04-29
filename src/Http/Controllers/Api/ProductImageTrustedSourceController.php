<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Padosoft\ProductImageDiscovery\Http\Concerns\ResolvesProductImageDiscovery;
use Padosoft\ProductImageDiscovery\Http\Requests\UpsertProductImageTrustedSourceRequest;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageTrustedSourceResource;

final class ProductImageTrustedSourceController extends Controller
{
    use ResolvesProductImageDiscovery;

    public function index()
    {
        return ProductImageTrustedSourceResource::collection(
            $this->newQuery('trusted_source')->orderByDesc('trust_score')->orderBy('domain')->paginate(25)
        );
    }

    public function store(UpsertProductImageTrustedSourceRequest $request): ProductImageTrustedSourceResource
    {
        $record = $this->newQuery('trusted_source')->create($request->validated());

        return new ProductImageTrustedSourceResource($record);
    }

    public function show(int|string $trustedSource): ProductImageTrustedSourceResource
    {
        return new ProductImageTrustedSourceResource($this->newQuery('trusted_source')->findOrFail($trustedSource));
    }

    public function update(UpsertProductImageTrustedSourceRequest $request, int|string $trustedSource): ProductImageTrustedSourceResource
    {
        $record = $this->newQuery('trusted_source')->findOrFail($trustedSource);
        $record->fill($request->validated());
        $record->save();

        return new ProductImageTrustedSourceResource($record);
    }

    public function destroy(int|string $trustedSource): Response
    {
        $record = $this->newQuery('trusted_source')->findOrFail($trustedSource);
        $record->delete();

        return response()->noContent();
    }
}
