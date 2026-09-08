<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnlyIfEnabled
{
    /**
     * Route middleware that rejects requests unless the given dot-notation
     * config boolean is truthy.
     *
     * Usage (route level): middleware("only-if:services.payments.simulation_enabled")
     */
    public function handle(Request $request, Closure $next, string $configPath): Response
    {
        if (! filter_var(config($configPath), FILTER_VALIDATE_BOOL)) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
