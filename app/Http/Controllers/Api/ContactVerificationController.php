<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountStateMachine;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Phase 23: Email / phone contact verification.
 *
 * Separate verification for each channel: enter contact → send code → validate
 * code → contact becomes verified. Codes expire, have attempt & resend limits,
 * and are single-use.
 */
class ContactVerificationController extends Controller
{
    public function __construct(
        protected VerificationCodeService $codes,
        protected AccountStateMachine $stateMachine,
    ) {}

    /**
     * POST /api/account/verification/send
     *
     * `address` is optional: if omitted, the code goes to the user's current
     * email (channel=email) or phone (channel=phone).
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'channel' => ['required', 'in:email,phone'],
            'address' => 'nullable|string|max:191',
        ]);

        $channel = $validated['channel'];
        $address = $validated['address'] ?? null;

        if ($channel === 'email') {
            if ($address && $address !== $user->email && ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['address' => ['Enter a valid email address.']]);
            }
            if (! $address) {
                if (! $user->email) {
                    throw ValidationException::withMessages(['address' => ['You have no email on file to verify.']]);
                }
                $address = $user->email;
            }
        } else {
            if ($address && ! preg_match('/^[0-9+\-\s()]{7,20}$/', $address)) {
                throw ValidationException::withMessages(['address' => ['Enter a valid phone number.']]);
            }
            if (! $address) {
                if (! $user->phone) {
                    throw ValidationException::withMessages(['address' => ['You have no phone on file to verify.']]);
                }
                $address = $user->phone;
            }
        }

        $record = $this->codes->issue($user, $channel, $address);

        return response()->json([
            'message' => "A verification code was sent to your {$channel}.",
            'data' => [
                'token' => $record->token,
                'masked_address' => $this->codes->mask($address),
                'expires_in' => now()->diffInSeconds($record->expires_at),
                'max_attempts' => $record->max_attempts,
            ],
        ]);
    }

    /**
     * POST /api/account/verification/verify
     *
     * When the code is for the user's current contact, we simply mark it
     * verified. When it was sent to a NEW address (sensitive change), the
     * change is applied and — failing this phase's stronger controls — other
     * active sessions are revoked.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'channel' => ['required', 'in:email,phone'],
            'token' => 'required|string|max:100',
            'code' => 'required|string|max:12',
            'current_password' => 'nullable|string',
        ]);

        $record = $this->codes->verify($user, $validated['channel'], $validated['token'], $validated['code'], consume: false);
        $targetAddress = $record->address;

        $isChange = $validated['channel'] === 'email'
            ? ($targetAddress !== $user->email)
            : ($targetAddress !== $user->phone);

        // Section 17: changing a recovery contact must be gated by more than
        // possession of a code — require the account password too.
        if ($isChange) {
            if (blank($validated['current_password'] ?? null) || ! Hash::check($validated['current_password'] ?? null, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Enter your current password to change your contact details.'],
                ]);
            }
        }

        $record->markUsed();

        $before = ['email_verified_at' => $user->email_verified_at?->toIso8601String(), 'phone_verified_at' => $user->phone_verified_at?->toIso8601String()];

        if ($validated['channel'] === 'email') {
            if ($isChange) {
                $exists = User::where('email', $targetAddress)->where('id', '!=', $user->id)->withTrashed()->first();
                if ($exists && $exists->email_verified_at) {
                    throw ValidationException::withMessages([
                        'address' => ['This email address is already associated with an account.'],
                    ]);
                }
                $user->email = $targetAddress;
            }
            $user->email_verified_at = now();
        } else {
            if ($isChange) {
                $exists = User::where('phone', $targetAddress)->where('id', '!=', $user->id)->whereNotNull('phone_verified_at')->withTrashed()->first();
                if ($exists) {
                    throw ValidationException::withMessages([
                        'phone' => ['This phone number is already associated with an account.'],
                    ]);
                }
                $user->phone = $targetAddress;
            }
            $user->phone_verified_at = now();
        }
        $user->save();

        // A new contact being verified is a security-sensitive change; evict
        // other sessions so recovery isn't silently redirected.
        if ($isChange) {
            foreach ($user->tokens as $token) {
                if ($token->id !== $request->user()->currentAccessToken()?->id) {
                    $token->delete();
                }
            }
        }

        AuditLog::record(
            $user->id,
            $isChange ? 'account.contact_changed' : 'account.contact_verified',
            User::class,
            $user->id,
            $user->name,
            $before,
            [
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'channel' => $validated['channel'],
            ],
            $request->ip(),
            $request->userAgent(),
        );

        $this->stateMachine->derive($user);

        $data = [
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'account_state' => $user->account_state->value,
            'contact_changed' => $isChange,
        ];

        return response()->json([
            'message' => $isChange
                ? 'Your contact has been updated and verified.'
                : 'Your contact has been verified.',
            'data' => $data,
        ]);
    }
}
