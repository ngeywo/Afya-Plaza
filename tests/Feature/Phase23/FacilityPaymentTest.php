<?php

namespace Tests\Feature\Phase23;

use App\Enums\DoctorRelationshipStatus;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorEarning;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilityService;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Services\PaymentService;

/**
 * Phase 23 (Facility Payments): patients pay the selected FACILITY, not the
 * doctor. Platform fee is configurable and snapshotted; no doctor payroll is
 * created for facility-directed payments; callbacks stay idempotent; abandoned
 * payments expire.
 */
class FacilityPaymentTest extends Phase23TestCase
{
    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = app(PaymentService::class);
        config([
            'services.payments.platform_fee_type' => 1,
            'services.payments.platform_fee_percent' => 5.0,
            'services.payments.platform_fee_fixed' => 0,
            'services.payments.pending_expiry_minutes' => 30,
        ]);
        // The three marketplace Plans (Starter as default) are seeded by the
        // plans migration; the legacy payroll flow reads them via resolveSubscription().
    }

    private function facilitySession(
        Doctor $doctor,
        Facility $facility,
        float $servicePrice = 1200,
        ?DoctorFacility $relationship = null,
    ): ClinicSession {
        $df = $relationship ?? DoctorFacility::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'status' => DoctorRelationshipStatus::ACTIVE,
            'is_active' => true,
            'accepts_appointments' => true,
            'consultation_fee' => $servicePrice,
        ]);
        DoctorFacilityService::create([
            'doctor_facility_id' => $df->id,
            'service_name' => 'Consultation',
            'price' => $servicePrice,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        return ClinicSession::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'doctor_facility_id' => $df->id,
            'session_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'slot_duration_minutes' => 30,
            'max_appointments' => 6,
            'booked_appointments' => 0,
            'consultation_fee' => $servicePrice,
            'status' => 'confirmed',
            'doctor_confirmation' => 'confirmed',
            'facility_confirmation' => 'confirmed',
        ]);
    }

    public function test_booking_snapshots_the_facility_service(): void
    {
        [$patient, $session] = $this->setupDoctorFacilityPatient(900);
        [$appointment] = $this->book($patient, $session);

        $this->assertEquals('Consultation', $appointment->service_name);
        $this->assertEquals('900', $appointment->service_price_snapshot);
        $this->assertNotNull($appointment->doctor_facility_service_id);
    }

    public function test_facility_directed_payment_flows_through_platform_fee_no_doctor_payroll(): void
    {
        [$patient, $session] = $this->setupDoctorFacilityPatient(1200);
        [$appointment, $payment] = $this->book($patient, $session);

        $this->assertEquals(Payment::RECIPIENT_FACILITY, $payment->recipient_type);

        $confirmed = $this->payments->confirm($payment, 'REF-FAC-001');
        $this->assertEquals(Payment::STATUS_PAID, $confirmed->status);
        $this->assertEquals('60.00', $confirmed->commission_amount);
        $this->assertEquals('1140.00', $confirmed->net_amount);
        $this->assertEquals('platform_fee:config', $confirmed->commission_rule_source);

        // Snapshots are written exactly once and no doctor earning is created.
        $appointment->refresh();
        $this->assertEquals('paid', $appointment->payment_status);
        $this->assertEquals('platform_fee:config', $appointment->plan_slug);
        $this->assertEqualsWithDelta(60.0, (float) $appointment->platform_commission_snapshot, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $appointment->doctor_earning_snapshot, 0.001);
        $this->assertDatabaseMissing('doctor_earnings', ['payment_id' => $confirmed->id]);
    }

    public function test_facility_payment_callback_is_idempotent(): void
    {
        [$patient, $session] = $this->setupDoctorFacilityPatient(1200);
        [, $payment] = $this->book($patient, $session);

        $this->payments->confirm($payment, 'REF-FAC-002');
        $again = $this->payments->confirm($payment, 'REF-FAC-002');

        $this->assertEquals('60.00', $again->fresh()->commission_amount);
        $this->assertEquals('60.00', $payment->fresh()->commission_amount);
        $this->assertEquals(0, DoctorEarning::where('payment_id', $payment->id)->count());
    }

    public function test_persisted_platform_fee_overrides_config(): void
    {
        PlatformFee::create([
            'name' => 'Facility flat fee',
            'fee_type' => PlatformFee::TYPE_FIXED,
            'rate' => 1000,
            'is_active' => true,
            'created_by' => $this->makeVerifiedUser('super-admin')->id,
        ]);

        [$patient, $session] = $this->setupDoctorFacilityPatient(1200);
        [, $payment] = $this->book($patient, $session);

        $confirmed = $this->payments->confirm($payment, 'REF-FAC-003');

        $this->assertEquals('1000.00', $confirmed->commission_amount);
        $this->assertEquals('200.00', $confirmed->net_amount);
        $this->assertEquals('platform_fee:persisted', $confirmed->commission_rule_source);
    }

    public function test_pending_payments_expire_after_threshold(): void
    {
        [$patient, $session] = $this->setupDoctorFacilityPatient(1000);
        [, $payment] = $this->book($patient, $session);
        $payment->update(['initiated_at' => now()->subHours(2)]);

        $expired = $this->payments->expireStalePayments();

        $this->assertEquals(1, $expired);
        $this->assertTrue($payment->fresh()->isExpired());
    }

    public function test_legacy_doctor_recipient_payment_still_creates_earnings(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $patient = $this->makeVerifiedUser('patient');

        // Legacy session WITHOUT doctor_facility_id → doctor-recipient flow.
        $session = ClinicSession::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'session_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'slot_duration_minutes' => 30,
            'max_appointments' => 4,
            'consultation_fee' => 1000,
            'status' => 'confirmed',
            'doctor_confirmation' => 'confirmed',
            'facility_confirmation' => 'confirmed',
        ]);

        [, $payment] = $this->book($patient, $session);

        $this->assertEquals(Payment::RECIPIENT_DOCTOR, $payment->recipient_type);

        $confirmed = $this->payments->confirm($payment, 'REF-LEGACY-001');

        $this->assertEquals(Payment::STATUS_PAID, $confirmed->status);
        $this->assertDatabaseHas('doctor_earnings', ['payment_id' => $confirmed->id]);
    }

    public function test_facility_payment_account_is_masked_in_responses(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        $response = $this->actingAsUser($admin)
            ->postJson('/api/facility/payment-accounts', [
                'facility_id' => $facility->id,
                'provider' => 'mpesa',
                'account_type' => 'paybill',
                'account_name' => 'Kamiti Clinic',
                'account_number' => '1234567890',
                'is_primary' => true,
            ])
            ->assertStatus(201);

        $this->assertEquals('****7890', $response->json('data.account_number_masked'));
        $this->assertStringNotContainsString('1234567890', $response->getContent());
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'App\Models\FacilityPaymentAccount', 'action' => 'facility_payment_account.created']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function setupDoctorFacilityPatient(float $fee): array
    {
        $patient = $this->makeVerifiedUser('patient');
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        return [$patient, $this->facilitySession($doctor, $facility, $fee)];
    }

    private function book($patient, ClinicSession $session): array
    {
        $booking = $this->actingAsUser($patient)
            ->postJson('/api/appointments', [
                'clinic_session_id' => $session->id,
                'start_time' => '09:00',
            ])
            ->assertStatus(201);

        $appointment = Appointment::findOrFail($booking->json('data.id'));

        $initiated = $this->actingAsUser($patient)
            ->postJson('/api/payments/initiate', [
                'appointment_id' => $appointment->id,
                'method' => 'mobile_money',
                'provider' => 'simulation',
            ])
            ->assertStatus(201);

        return [$appointment, Payment::findOrFail($initiated->json('data.id'))];
    }
}
