<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'facility_id',
        'doctor_id',
        'appointment_id',
        'payment_id',
        'gross_amount',
        'platform_commission',
        'facility_amount',
        'doctor_amount',
        'agreement_type',
        'agreement_snapshot',
        'currency',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'paid_at',
        'payment_method',
        'payment_reference',
        'created_by',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'facility_amount' => 'decimal:2',
        'doctor_amount' => 'decimal:2',
        'agreement_snapshot' => 'array',
        'status' => SettlementStatus::class,
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public static function generateReference(): string
    {
        return 'STL-'.now()->format('Ymd').'-'.str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', SettlementStatus::PENDING);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', SettlementStatus::APPROVED);
    }

    public function scopePaid($q)
    {
        return $q->where('status', SettlementStatus::PAID);
    }

    public function scopeForFacility($q, int $facilityId)
    {
        return $q->where('facility_id', $facilityId);
    }

    public function scopeForDoctor($q, int $doctorId)
    {
        return $q->where('doctor_id', $doctorId);
    }

    public function isPending(): bool
    {
        return $this->status === SettlementStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === SettlementStatus::PAID;
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => SettlementStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function markPaid(string $method, ?string $reference = null): void
    {
        $this->update([
            'status' => SettlementStatus::PAID,
            'paid_at' => now(),
            'payment_method' => $method,
            'payment_reference' => $reference,
        ]);
    }

    public function hold(): void
    {
        $this->update(['status' => SettlementStatus::ON_HOLD]);
    }
}
