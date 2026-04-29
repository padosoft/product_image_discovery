<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Fixtures;

final class FakeIngestJob
{
    public function __construct(public readonly int|string $requestId)
    {
    }

    public function __invoke(): void
    {
    }
}
