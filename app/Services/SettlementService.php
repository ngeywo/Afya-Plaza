<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\DoctorFacilityAgreementType;
use App\Enums\SettlementStatus;
use App\Models\Appointment;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilityAgreement;
use App\Models\Payment;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;

/**
 * SettlementService — Phase 25
 *
 * Manages doctor-facility financial settlements.
 * Settlements created only for COMPLETED appointments with PAID payments.
 */
class SettlementService
{
    public function __construct(private CommissionService $commissionService) {}

    /**
     * Create settlement for a completed appointment. Idempotent.
     */
    public function createForAppointment(Appointment $appointment, int $createdBy): Settlement
    {
        if ($appointment->status !== AppointmentStatus::COMPLETED) {
            throw new \InvalidArgumentException('Cannot settle non-completed appointment');
        }

        $existing = Settlement::where('appointment_id', $appointment->id)->first();
        if ($existing) {
            return $existing;
        }

        $payment = $appointment->payment;
        if (! $payment || ! $payment->isPaid()) {
            throw new \InvalidArgumentException('No paid payment found');
        }

        return DB::transaction(fn () => $this->createRecord($appointment, $payment, $createdBy));
    }

    private function createRecord(Appointment $appt, Payment $payment, int $createdBy): Settlement
    {
        $df = $this->getDoctorFacility($appt);
        $agreement = $df ? DoctorFacilityAgreement::getForDoctorFacility($df->id, $appt->appointment_date) : null;

        $gross = $payment->gross_amount;
        $platform = $payment->commission_amount ?? '0.00';
        $agreementType = $agreement?->agreement_type->value ?? DoctorFacilityAgreementType::SALARIED->value;

        $doctorAmt = '0.00';
        $facilityAmt = bcsub($gross, $platform, 2);

        if ($agreement && $agreement->requiresSettlement()) {
            $amounts = $agreement->calculateSettlement($gross, $platform);
            $doctorAmt = $amounts['doctor_amount'];
            $facilityAmt = $amounts['facility_amount'];
        }

        return Settlement::create([
            'reference' => Settlement::generateReference(),
            'facility_id' => $appt->facility_id,
            'doctor_id' => $appt->doctor_id,
            'appointment_id' => $appt->id,
            'payment_id' => $payment->id,
            'gross_amount' => $gross,
            'platform_commission' => $platform,
            'facility_amount' => $facilityAmt,
            'doctor_amount' => $doctorAmt,
            'agreement_type' => $agreementType,
            'agreement_snapshot' => $agreement?->toSnapshot(),
            'currency' => 'KES',
            'status' => SettlementStatus::PENDING,
            'created_by' => $createdBy,
        ]);
    }

    private function getDoctorFacility(Appointment $appt): ?DoctorFacility
    {
        if ($appt->clinic_session_id && $appt->clinicSession?->doctor_facility_id) {
            return DoctorFacility::find($appt->clinicSession->doctor_facility_id);
        }

        return DoctorFacility::where('doctor_id', $appt->doctor_id)
            ->where('facility_id', $appt->facility_id)->first();
    }

    public function approve(Settlement $s, int $userId): Settlement
    {
        if (! $s->isPending()) {
            throw new \InvalidArgumentException('Only pending settlements can be approved');
        }
        $s->approve($userId);

        return $s->fresh();
    }

    public function markPaid(Settlement $s, string $method, ?string $reference = null): Settlement
    {
        $s->markPaid($method, $reference);

        return $s->fresh();
    }

    public function getFacilitySummary(int $facilityId, ?string $period = null): array
    {
        $query = Settlement::forFacility($facilityId);
        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        return [
            'total_gross' => $query->sum('gross_amount'),
            'total_platform_commission' => $query->sum('platform_commission'),
            'total_facility_amount' => $query->sum('facility_amount'),
            'total_doctor_amount' => $query->sum('doctor_amount'),
            'pending_count' => Settlement::forFacility($facilityId)->pending()->count(),
            'paid_count' => Settlement::forFacility($facilityId)->paid()->count(),
        ];
    }

    public function getDoctorSummary(int $doctorId): array
    {
        return [
            'pending' => Settlement::forDoctor($doctorId)->pending()->sum('doctor_amount'),
            'approved' => Settlement::forDoctor($doctorId)->approved()->sum('doctor_amount'),
            'paid' => Settlement::forDoctor($doctorId)->paid()->sum('doctor_amount'),
            'total' => Settlement::forDoctor($doctorId)->sum('doctor_amount'),
        ];
    }

    public function getDoctorBreakdown(int $facilityId, ?string $period = null): array
    {
        $query = Settlement::forFacility($facilityId);
        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        return $query->selectRaw('doctor_id, COUNT(*) as count, SUM(gross_amount) as gross, SUM(doctor_amount) as due')
            ->groupBy('doctor_id')->with('doctor:id,user_id,name')->get()
            ->map(fn ($r) => [
                'doctor_id' => $r->doctor_id,
                'doctor_name' => $r->doctor?->name ?? 'Unknown',
                'appointment_count' => $r->count,
                'gross_revenue' => $r->gross,
                'doctor_due' => $r->due,
            ])->toArray();
    }
}
