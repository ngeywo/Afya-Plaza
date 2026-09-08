<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Phase 23: Password security — change, forgot, reset.
 *
 * Forgot/reset flows verify identity via the contact verification code system,
 * then rotate the password and invalidate other sessions.
 */
class PasswordController extends Controller
{
    public function __construct(protected VerificationCodeService $codes) {}

    /**
     * POST /api/account/password/change
     */
    public function change(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Incorrect current password.']]);
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['The new password must be different from the current password.']]);
        }

        $user->update(['password' => Hash::make($validated['password']), 'remember_token' => null]);

        // Invalidate other sessions — keep the current one.
        $current = $request->user()->currentAccessToken();
        foreach ($user->tokens as $token) {
            if ($token->id !== $current?->id) {
                $token->delete();
            }
        }

        AuditLog::record(
            $user->id,
            'auth.password_changed',
            User::class,
            $user->id,
            $user->name,
            null,
            ['password_changed_at' => now()->toIso8601String()],
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Password changed. Other sessions have been signed out.']);
    }

    /**
     * POST /api/auth/forgot-password
     * Always returns the same generic message to avoid user enumeration.
     * Rate limited at the route level.
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $record = $this->codes->issue($user, 'email', $user->email);

            return response()->json([
                'message' => 'If an account exists for that email, a reset code has been sent.',
                'data' => ['token' => $record->token],
            ]);
        }

        return response()->json([
            'message' => 'If an account exists for that email, a reset code has been sent.',
        ]);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|max:100',
            'code' => 'required|string|max:12',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['Invalid reset attempt.']]);
        }

        $this->codes->verify($user, 'email', $validated['token'], $validated['code']);

        $user->update([
            'password' => Hash::make($validated['password']),
            'remember_token' => null,
        ]);

        // Invalidate ALL sessions — the reset event itself is a new context.
        $user->tokens()->delete();

        AuditLog::record(
            $user->id,
            'auth.password_reset',
            User::class,
            $user->id,
            $user->name,
            null,
            ['password_reset_at' => now()->toIso8601String()],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Password reset successfully. Please sign in again.']);
    }
}
