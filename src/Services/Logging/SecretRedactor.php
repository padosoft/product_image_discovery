<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Logging;

final class SecretRedactor
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'api_key',
        'apikey',
        'api_secret',
        'secret',
        'password',
        'token',
        'authorization',
    ];

    public function redact(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        $redacted = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }
}
