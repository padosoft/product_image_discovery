<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Requests;

final class RejectProductImageDiscoveryCandidateRequest extends ProductImageDiscoveryFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
