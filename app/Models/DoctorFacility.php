<?php

namespace App\Models;

use App\Enums\DoctorRelationshipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorFacility extends Model
{
    use HasFactory;

    protected $table = 'doctor_facilities';

    protected $fillable = [
        'doctor_id',
        'facility_id',
        'consultation_fee',
        'accepts_appointments',
        'is_active',
        'started_at',
        'ended_at',
        'notes',
        // Phase 13: Governance
        'status',
        'invited_by',
        'approved_by',
        'approved_at',
        'declined_at',
        'decline_reason',
        'ended_at_governance',
        'ended_by',
        'notes_governance',
        // Phase 23: Doctor-initiated join request
        'requested_by',
    ];

    protected $casts = [
        'consultation_fee' => 'decimal:2',
        'accepts_appointments' => 'boolean',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'status' => DoctorRelationshipStatus::class,
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'ended_at_governance' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorFacilitySchedule::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(DoctorScheduleException::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(DoctorFacilityService::class);
    }

    // ─── Phase 13: Governance Relations ─────────────────────────────────────────

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function endedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // ─── Phase 23: Convenience Helpers ──────────────────────────────────────────

    /**
     * Whether the relationship has been terminated (spec alias: ENDED).
     */
    public function isEnded(): bool
    {
        return $this->status?->isEnded() === true;
    }

    /**
     * Whether the relationship was refused by either party (spec alias: REJECTED).
     */
    public function isRejected(): bool
    {
        return $this->status?->isRejected() === true;
    }

    /**
     * Spec-friendly status label: INACTIVE displays as "Ended", DECLINED as "Rejected".
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            DoctorRelationshipStatus::INACTIVE => 'Ended',
            DoctorRelationshipStatus::DECLINED => 'Rejected',
            default => $this->status?->label() ?? 'Unknown',
        };
    }

    // ─── Phase 13: Convenience Helpers ──────────────────────────────────────────

    /**
     * Whether this relationship is active and approved by both parties.
     */
    public function isAuthoritative(): bool
    {
        return $this->status?->isActive() === true;
    }

    /**
     * Whether a clinic session can be created/booked through this relationship.
     */
    public function canHostSession(): bool
    {
        return $this->isAuthoritative()
            && $this->accepts_appointments
            && $this->is_active;
    }
}
