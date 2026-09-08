<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_number',
        'idempotency_key',
        'user_id',
        'doctor_id',
        'clinic_session_id',
        'doctor_facility_service_id',
        'service_name',
        'service_price_snapshot',
        'facility_id',
        'facility_location_id',
        'appointment_date',
        'start_time',
        'end_time',
        'reason',
        'notes',
        'status',
        'confirmed_at',
        'checked_in_at',
        'checked_in_by',
        'consultation_started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'no_show_at',
        'no_show_by',
        'amount_paid',
        'payment_status',
        // Phase 12 financial snapshots written by PaymentService (commission guard)
        'plan_slug',
        'consultation_fee_snapshot',
        'platform_commission_snapshot',
        'doctor_earning_snapshot',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'consultation_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Phase 11: clinic-day status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    /**
     * Authoritative transition map. Key = current state, value = allowed next states.
     */
    public const ALLOWED_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['in_progress', 'cancelled', 'no_show'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function facilityLocation(): BelongsTo
    {
        return $this->belongsTo(FacilityLocation::class);
    }

    public function clinicSession(): BelongsTo
    {
        return $this->belongsTo(ClinicSession::class);
    }

    public function doctorFacilityService(): BelongsTo
    {
        return $this->belongsTo(DoctorFacilityService::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function noShowMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_show_by');
    }

    /** Phase 17: Payments belong to a separate financial domain but are referenced here for transparency. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function generateNumber(): string
    {
        $prefix = 'APT';
        $date = now()->format('Ymd');
        $random = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return "{$prefix}-{$date}-{$random}";
    }

    // ─── Phase 17: Query scopes ─────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeUpcoming($q)
    {
        return $q->where('appointment_date', '>=', today()->toDateString())
            ->whereIn('status', ['pending', 'confirmed', 'checked_in', 'in_progress']);
    }

    public function scopePast($q)
    {
        return $q->where('appointment_date', '<', today()->toDateString())
            ->whereNotIn('status', ['cancelled']);
    }

    public function scopeCancelled($q)
    {
        return $q->where('status', 'cancelled');
    }

    /**
     * Phase 17: Actions the authenticated patient may currently perform.
     * The backend remains authoritative — this only drives the UI so invalid
     * actions are never shown (Section 34 / 35 / 41).
     */
    public function allowedPatientActions(): array
    {
        $actions = [];

        if (in_array($this->status, ['pending', 'confirmed', 'checked_in'], true)) {
            $actions[] = 'cancel';
        }

        if (in_array($this->status, ['pending', 'confirmed'], true)
            && $this->appointment_date->gte(today())) {
            $actions[] = 'reschedule';
        }

        if ($this->payment_status !== 'paid'
            && in_array($this->status, ['pending', 'confirmed', 'checked_in', 'in_progress'], true)) {
            $actions[] = 'pay';
        }

        return $actions;
    }

    /**
     * Phase 17: Actions an authorized doctor may perform on this appointment.
     * Derived from the authoritative transition map so the UI matches the backend.
     */
    public function allowedDoctorActions(): array
    {
        $actions = [];
        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        if (in_array(self::STATUS_IN_PROGRESS, $allowed, true)) {
            $actions[] = 'start';
        }
        if (in_array(self::STATUS_COMPLETED, $allowed, true)) {
            $actions[] = 'complete';
        }
        if (in_array(self::STATUS_CANCELLED, $allowed, true)) {
            $actions[] = 'cancel';
        }

        return $actions;
    }

    /**
     * Phase 17: Actions authorized facility staff may perform.
     */
    public function allowedFacilityActions(): array
    {
        $actions = [];
        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        if (in_array(self::STATUS_CHECKED_IN, $allowed, true)) {
            $actions[] = 'check_in';
        }
        if (in_array(self::STATUS_NO_SHOW, $allowed, true)) {
            $actions[] = 'no_show';
        }
        if (in_array(self::STATUS_CANCELLED, $allowed, true)) {
            $actions[] = 'cancel';
        }

        return $actions;
    }

    /**
     * Phase 11: check if the appointment can legally transition to the given status.
     */
    public function canTransitionTo(string $next): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        return in_array($next, $allowed, true);
    }
}
