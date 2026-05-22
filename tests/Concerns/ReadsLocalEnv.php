<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Concerns;

trait ReadsLocalEnv
{
    protected function envValue(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

        if (! is_file($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);

            if (trim($name) !== $key) {
                continue;
            }

            $rawValue = trim($rawValue);

            if (
                strlen($rawValue) >= 2
                && (($rawValue[0] === '"' && $rawValue[strlen($rawValue) - 1] === '"')
                    || ($rawValue[0] === "'" && $rawValue[strlen($rawValue) - 1] === "'"))
            ) {
                $rawValue = substr($rawValue, 1, -1);
            }

            return trim($rawValue);
        }

        return null;
    }
}
