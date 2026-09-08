<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountState;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected AccountStateMachine $stateMachine) {}

    /**
     * POST /api/auth/login
     * Returns user + Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device' => 'nullable|string|max:191',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Closed accounts are denied even with valid credentials.
        if (! $user->canAuthenticate()) {
            AuditLog::record($user->id, 'auth.login_denied', User::class, $user->id, $user->name, ['account_state' => $user->account_state->value], null, 'Account closed', $request->ip(), $request->userAgent());
            throw ValidationException::withMessages([
                'email' => ['This account is currently closed. Please contact support.'],
            ]);
        }

        $device = $validated['device'] ?? 'api-token';
        $token = $user->createToken($device)->plainTextToken;
        $user->update(['last_login_at' => now()]);

        $this->stateMachine->derive($user);

        AuditLog::record(
            $user->id,
            'auth.login',
            User::class,
            $user->id,
            $user->name,
            null,
            ['device' => $device],
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    /**
     * POST /api/auth/register
     * Creates an account. Defaults to patient; accepts an optional `role`
     * (doctor | facility | patient) so providers can sign up and then complete
     * onboarding (profile creation) from the dashboard.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|string|in:patient,doctor,facility',
        ]);

        // Phase 23: duplicate-identity detection — verified phones are only
        // ever bound to one account. Privacy-safe response (never reveals who).
        if (filled($validated['phone'] ?? null)) {
            $dupe = User::where('phone', $validated['phone'])
                ->whereNotNull('phone_verified_at')
                ->withTrashed()
                ->first();

            if ($dupe) {
                throw ValidationException::withMessages([
                    'phone' => ['This phone number is already associated with an account. Try signing in or recovering the existing account.'],
                ]);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
            'account_state' => AccountState::CONTACT_UNVERIFIED,
        ]);

        $roleSlug = $validated['role'] ?? 'patient';

        // The public-facing registration role "facility" maps to the internal
        // "facility-admin" role (there is no standalone "facility" role).
        if ($roleSlug === 'facility') {
            $roleSlug = 'facility-admin';
        }

        $role = Role::where('slug', $roleSlug)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        } else {
            // Fallback: always ensure the user has at least the patient role.
            $patientRole = Role::where('slug', 'patient')->first();
            if ($patientRole) {
                $user->roles()->attach($patientRole->id);
            }
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ], 201);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Shared user payload for login/register/me, including provider onboarding
     * flags so the frontend can steer doctors/facilities to profile completion.
     */
    private function userPayload(User $user): array
    {
        $doctor = $user->doctor;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'roles' => $user->roles->pluck('slug'),
            'doctor_profile_exists' => (bool) $doctor,
            'facility_admin_for' => $user->facilities()->count(),
            'account_state' => $user->account_state->value,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'contact_verified' => $user->hasVerifiedContact(),
            'provider_verification' => $doctor ? $doctor->verification_status?->value : null,
        ];
    }
}
