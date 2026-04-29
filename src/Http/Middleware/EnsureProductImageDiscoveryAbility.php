<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Padosoft\ProductImageDiscovery\Http\Concerns\ResolvesProductImageDiscovery;
use Symfony\Component\HttpFoundation\Response;

final class EnsureProductImageDiscoveryAbility
{
    use ResolvesProductImageDiscovery;

    public function handle(Request $request, Closure $next, string ...$abilityKeys): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'tokenCan')) {
            abort(Response::HTTP_FORBIDDEN, 'A valid Sanctum token is required.');
        }

        foreach ($abilityKeys as $abilityKey) {
            if ($user->tokenCan($this->abilityName($abilityKey))) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'Missing required Product Image Discovery ability.');
    }
}
