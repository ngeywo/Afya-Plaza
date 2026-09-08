<?php

namespace App\Services;

use App\Models\User;

/**
 * Pluggable delivery of verification codes. Swap in a real SMS/email provider
 * here without changing flow logic.
 */
interface CodeSenderInterface
{
    public function send(User $user, string $channel, string $code, ?string $maskedAddress): void;
}
