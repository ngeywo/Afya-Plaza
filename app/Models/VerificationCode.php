<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Phase 23: A one-time verification code (email/phone).
 */
class VerificationCode extends Model
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_PHONE = 'phone';

    protected $fillable = [
        'user_id',
        'channel',
        'address',
        'code',
        'token',
        'expires_at',
        'attempts',
        'max_attempts',
        'max_resends',
        'resends',
        'used',
        'used_at',
        'last_resent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
            'used_at' => 'datetime',
            'last_resent_at' => 'datetime',
            'attempts' => 'integer',
            'resends' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $q, int $userId, string $channel): Builder
    {
        return $q->where('user_id', $userId)->where('channel', $channel);
    }

    public function scopeUnused(Builder $q): Builder
    {
        return $q->where('used', false);
    }

    public function scopeNotExpired(Builder $q): Builder
    {
        return $q->where('expires_at', '>', now());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function canResend(): bool
    {
        return $this->resends < $this->max_resends;
    }

    public function consumeAttempt(): void
    {
        $this->increment('attempts');
        if ($this->attempts >= $this->max_attempts) {
            $this->expires_at = Carbon::now();
            $this->save();
        }
    }

    public function markUsed(): void
    {
        $this->update(['used' => true, 'used_at' => now()]);
    }
}
