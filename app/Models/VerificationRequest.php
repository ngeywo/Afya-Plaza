<?php

namespace App\Models;

use App\Enums\VerificationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Phase 23: A single verification submission and its outcome.
 */
class VerificationRequest extends Model
{
    protected $fillable = [
        'verifiable_type',
        'verifiable_id',
        'user_id',
        'type',
        'status',
        'registry_number',
        'submitted_data',
        'evidence',
        'verification_source',
        'reviewer_notes',
        'rejection_reason',
        'reviewer_id',
        'submitted_at',
        'reviewed_at',
        'expires_at',
        'requested_changes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VerificationRequestStatus::class,
            'submitted_data' => 'array',
            'evidence' => 'array',
            'requested_changes' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            VerificationRequestStatus::PENDING,
            VerificationRequestStatus::UNDER_REVIEW,
            VerificationRequestStatus::MORE_INFO,
        ], true);
    }
}
