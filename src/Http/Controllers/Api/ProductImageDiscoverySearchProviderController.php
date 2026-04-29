<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Controllers\Api;

use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Padosoft\ProductImageDiscovery\Http\Concerns\ResolvesProductImageDiscovery;
use Padosoft\ProductImageDiscovery\Http\Requests\UpsertProductImageSearchProviderRequest;
use Padosoft\ProductImageDiscovery\Http\Resources\ProductImageDiscoverySearchProviderResource;

final class ProductImageDiscoverySearchProviderController extends Controller
{
    use ResolvesProductImageDiscovery;

    public function index()
    {
        return ProductImageDiscoverySearchProviderResource::collection(
            $this->newQuery('search_provider')->orderBy('priority')->orderBy('code')->paginate(25)
        );
    }

    public function store(UpsertProductImageSearchProviderRequest $request): ProductImageDiscoverySearchProviderResource
    {
        $record = $this->newQuery('search_provider')->create($this->payloadWithEncryptedSecrets($request->validated()));

        return new ProductImageDiscoverySearchProviderResource($record);
    }

    public function show(int|string $searchProvider): ProductImageDiscoverySearchProviderResource
    {
        return new ProductImageDiscoverySearchProviderResource($this->newQuery('search_provider')->findOrFail($searchProvider));
    }

    public function update(UpsertProductImageSearchProviderRequest $request, int|string $searchProvider): ProductImageDiscoverySearchProviderResource
    {
        $record = $this->newQuery('search_provider')->findOrFail($searchProvider);
        $record->fill($this->payloadWithEncryptedSecrets($request->validated(), $record->getAttributes()));
        $record->save();

        return new ProductImageDiscoverySearchProviderResource($record);
    }

    public function destroy(int|string $searchProvider): Response
    {
        $record = $this->newQuery('search_provider')->findOrFail($searchProvider);
        $record->delete();

        return response()->noContent();
    }

    private function payloadWithEncryptedSecrets(array $payload, array $current = []): array
    {
        $hasApiKey = array_key_exists('api_key', $payload);
        $hasApiSecret = array_key_exists('api_secret', $payload);
        $apiKey = $payload['api_key'] ?? null;
        $apiSecret = $payload['api_secret'] ?? null;

        unset($payload['api_key'], $payload['api_secret']);

        if ($apiKey !== null && $apiKey !== '') {
            $payload['api_key_encrypted'] = $this->encryptValue($apiKey);
        } elseif ($hasApiKey) {
            $payload['api_key_encrypted'] = null;
        } elseif (array_key_exists('api_key_encrypted', $current)) {
            $payload['api_key_encrypted'] = $current['api_key_encrypted'];
        }

        if ($apiSecret !== null && $apiSecret !== '') {
            $payload['api_secret_encrypted'] = $this->encryptValue($apiSecret);
        } elseif ($hasApiSecret) {
            $payload['api_secret_encrypted'] = null;
        } elseif (array_key_exists('api_secret_encrypted', $current)) {
            $payload['api_secret_encrypted'] = $current['api_secret_encrypted'];
        }

        return $payload;
    }

    private function encryptValue(string $value): string
    {
        try {
            return Crypt::encryptString($value);
        } catch (EncryptException) {
            return $value;
        }
    }
}
