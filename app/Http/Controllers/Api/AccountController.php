<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountState;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ProfileCompletenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Phase 23: Account overview, session management and self-service
 * deactivation.
 */
class AccountController extends Controller
{
    /**
     * GET /api/account
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $doctor = $user->doctor;
        $facility = $user->facilities()->first();

        $passwordChanged = AuditLog::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', ['auth.password_changed', 'auth.password_reset'])
            ->orderByDesc('created_at')
            ->first();

        $recentLogins = AuditLog::query()
            ->where('actor_id', $user->id)
            ->where('action', 'auth.login')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($log) => [
                'created_at' => $log->created_at?->toIso8601String(),
                'ip_address' => $log->ip_address,
            ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'roles' => $user->roles->pluck('slug'),
                'account_state' => $user->accountState()->value,
                'account_state_label' => $user->accountState()->label(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'contact_verified' => $user->hasVerifiedContact(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'doctor_profile_exists' => (bool) $doctor,
                'facility_admin_for' => $user->facilities()->count(),
                'provider' => $doctor ? $this->providerDetails($doctor, 'doctor') : null,
                'facility' => $facility ? [
                    'verification_status' => $facility->verification_status?->value,
                    'completeness' => ProfileCompletenessService::facilityCompleteness($facility),
                ] : null,
                'completeness' => $doctor ? ProfileCompletenessService::doctorCompleteness($doctor) : null,
                'security' => [
                    'password_last_changed' => $passwordChanged?->created_at?->toIso8601String(),
                    'recent_logins' => $recentLogins,
                ],
            ],
        ]);
    }

    /**
     * GET /api/account/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'current' => $token->id === $currentTokenId,
            ]);

        return response()->json(['data' => $sessions]);
    }

    /**
     * DELETE /api/account/sessions/{tokenId}
     * Revoke an active session. A user may never revoke the current session.
     */
    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->find($tokenId);

        if (! $token) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        if ($token->id === $request->user()->currentAccessToken()?->id) {
            return response()->json(['message' => 'You cannot revoke the current session.'], 422);
        }

        $token->delete();

        AuditLog::record(
            $request->user()->id,
            'auth.session_revoked',
            'sanctum_session',
            $tokenId,
            null,
            null,
            ['revoked' => true],
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Session revoked.']);
    }

    /**
     * POST /api/account/sessions/revoke-others
     */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        $revoked = 0;

        foreach ($request->user()->tokens as $token) {
            if ($token->id !== $current?->id) {
                $token->delete();
                $revoked++;
            }
        }

        AuditLog::record(
            $request->user()->id,
            'auth.sessions_revoked',
            'sanctum_session',
            null,
            null,
            null,
            ['revoked' => $revoked],
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => "Revoked {$revoked} other session(s)."]);
    }

    /**
     * POST /api/account/deactivate
     *
     * Self-service deactivation: requires password confirmation, cancels
     * future appointments, removes public visibility, revokes all sessions.
     * Records are preserved (soft delete), never destroyed.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'reason' => 'required|string|max:255',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Incorrect password.']]);
        }

        // Handle active appointments: cancel future ones, keep history.
        Appointment::where('user_id', $user->id)
            ->whereDate('appointment_date', '>=', today()->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => 'Account deactivated',
            ]);

        $user->update([
            'account_state' => AccountState::DEACTIVATED,
            'deactivated_at' => now(),
            'is_active' => false,
        ]);

        $user->tokens()->delete();

        AuditLog::record(
            $user->id,
            'account.deactivated',
            User::class,
            $user->id,
            $user->name,
            ['account_state' => 'active'],
            ['account_state' => AccountState::DEACTIVATED->value],
            $validated['reason'],
            $request->ip(),
            $request->userAgent(),
        );

        // Also mark any provider profiles non-public.
        if ($user->doctor) {
            $user->doctor->update(['is_active' => false]);
        }
        $user->facilities()->update(['is_active' => false]);

        return response()->json([
            'message' => 'Your account has been deactivated. Records are retained per platform policy.',
        ]);
    }

    private function providerDetails($model, string $type): array
    {
        if ($type === 'doctor') {
            return [
                'display_name' => $model->display_name,
                'verification_status' => $model->verification_status?->value,
                'is_verified' => (bool) $model->is_verified,
                'is_active' => (bool) $model->is_active,
                'rejection_reason' => $model->rejection_reason,
            ];
        }

        return [];
    }
}
