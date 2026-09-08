<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Phase 23: One-time verification code service.
 *
 * Issues & validates contact-verification codes (email/phone) with:
 *  - expiration (default 10 minutes)
 *  - attempt limits (default 5)
 *  - resend limits (default 5, with a cooldown)
 *  - used/unused status
 *
 * Sending is provider-agnostic: an implementation hook is invoked so that
 * real SMS/email providers can be wired in without changing flow logic.
 */
class VerificationCodeService
{
    public const TTL_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(protected CodeSenderInterface $sender) {}

    /**
     * Issue (or refresh) a code for the user's channel.
     *
     * @param  'email'|'phone'  $channel
     */
    public function issue(User $user, string $channel, ?string $address = null): VerificationCode
    {
        if (! in_array($channel, [VerificationCode::CHANNEL_EMAIL, VerificationCode::CHANNEL_PHONE], true)) {
            throw new RuntimeException('Unsupported verification channel.');
        }

        // Do not allow a continuous stream of codes: resend limit + cooldown.
        $issuedRecently = VerificationCode::query()
            ->forUser($user->id, $channel)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($issuedRecently >= 5) {
            throw ValidationException::withMessages([
                'code' => ['Too many verification codes. Please wait before requesting another.'],
            ]);
        }

        $latest = VerificationCode::query()
            ->forUser($user->id, $channel)
            ->orderByDesc('id')
            ->first();

        if ($latest && $latest->last_resent_at && $latest->last_resent_at->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS && ! $latest->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Please wait before requesting another code.'],
            ]);
        }

        // Revoke outstanding codes for the same channel so only one is live.
        VerificationCode::query()
            ->forUser($user->id, $channel)
            ->unused()
            ->notExpired()
            ->update(['used' => true, 'used_at' => now()]);

        $code = Str::upper(Str::random(6));
        $token = Str::random(40);

        $rawAddress = $address ?? ($channel === VerificationCode::CHANNEL_EMAIL ? $user->email : $user->phone);

        $record = VerificationCode::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'address' => $rawAddress,
            'code' => Hash::make($code),
            'token' => $token,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'max_attempts' => 5,
            'max_resends' => 5,
            'last_resent_at' => now(),
        ]);

        // Deliver through the pluggable sender (email / SMS).
        $this->sender->send($user, $channel, $code, $this->mask($rawAddress));

        // Debug / logging only — the plaintext code is never persisted.
        Log::info('Verification code issued', [
            'user_id' => $user->id,
            'channel' => $channel,
            'token' => $token,
            'code' => config('app.debug') ? $code : null,
        ]);

        return $record;
    }

    /**
     * Validate a submitted code. Fails closed on any security condition.
     *
     * When `consume` is true (default) the code is marked used immediately on
     * a correct guess. Controllers that still need to run an additional gate
     * (e.g. current-password on contact changes) should pass `false` and call
     * `markUsed()` only after every gate has passed.
     */
    public function verify(User $user, string $channel, string $token, string $code, bool $consume = true): VerificationCode
    {
        $record = VerificationCode::query()
            ->forUser($user->id, $channel)
            ->where('token', $token)
            ->unused()
            ->first();

        if (! $record) {
            throw ValidationException::withMessages(['code' => ['Invalid or already-used verification code.']]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages(['code' => ['This verification code has expired. Request a new one.']]);
        }

        if ($record->isExhausted()) {
            throw ValidationException::withMessages(['code' => ['Too many incorrect attempts. Request a new code.']]);
        }

        if (! Hash::check($code, $record->code)) {
            $record->consumeAttempt();
            throw ValidationException::withMessages(['code' => ['Incorrect verification code.']]);
        }

        if ($consume) {
            $record->markUsed();
        }

        return $record;
    }

    /**
     * Mask an address for display (email or phone). Public so controllers can
     * present the target without exposing the full value.
     */
    public function mask(?string $value): ?string
    {
        if (! $value || strlen($value) < 4) {
            return $value;
        }

        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            $len = min(2, max(1, intdiv(strlen($local), 3)));

            return substr($local, 0, $len).str_repeat('•', strlen($local) - $len).'@'.$domain;
        }

        $keep = min(3, strlen($value) - 4);
        $len = max(0, strlen($value) - 4);

        return substr($value, 0, $keep).str_repeat('•', $len);
    }
}
