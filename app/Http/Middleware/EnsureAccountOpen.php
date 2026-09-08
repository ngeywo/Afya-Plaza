<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 23: Fail-closed gate for closed accounts.
 *
 * Suspended / disabled / rejected / deactivated accounts may not use
 * protected platform resources, even with a valid token.
 */
class EnsureAccountOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isClosedAccount()) {
            return response()->json([
                'message' => 'Your account is currently closed. Please contact support.',
                'account_state' => $user->accountState()->value,
            ], 403);
        }

        return $next($request);
    }
}
