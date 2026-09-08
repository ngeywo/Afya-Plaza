<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default code sender: logs the code and emits an in-app notification.
 *
 * Designed as a seam — replace with a real SMS gateway / transactional email
 * provider in production without touching flow logic.
 */
class LoggingCodeSender implements CodeSenderInterface
{
    public function send(User $user, string $channel, string $code, ?string $maskedAddress): void
    {
        Log::info('OTP delivery requested', [
            'user_id' => $user->id,
            'channel' => $channel,
            'to' => $maskedAddress,
            'code' => config('app.debug') ? $code : '********',
        ]);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ContactVerification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'category' => 'verification',
                'title' => "Verification code sent ({$channel})",
                'message' => "A {$channel} verification code was sent to {$maskedAddress}. It expires in 10 minutes.",
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
