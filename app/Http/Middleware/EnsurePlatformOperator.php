<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOperator
{
    /**
     * Platform-level operators may reach the Control Centre: the platform owner
     * (super-admin) and operational administrators (platform-admin). Scope to
     * specific screens is applied per-route (super-admin-only groups keep the
     * `ensure.super.admin` middleware).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (! $user->isSuperAdmin() && ! $user->hasRole('platform-admin')) {
            return response()->json(['message' => 'Forbidden. Platform operator access required.'], 403);
        }

        return $next($request);
    }
}
