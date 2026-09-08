<?php

namespace Tests\Feature\Phase24;

use App\Enums\DoctorRelationshipStatus;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\Payment;

/**
 * Phase 28: expanded facility workspace pages — operating hours, schedules,
 * services, payments, transactions, reports, billing and settings. All of them
 * must be scoped to the actor's own facility (golden rule).
 */
class FacilityWorkspacePagesTest extends Phase24TestCase
{
    private function makeRelationship(Doctor $doctor, Facility $facility): DoctorFacility
    {
        return DoctorFacility::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'status' => DoctorRelationshipStatus::ACTIVE,
            'is_active' => true,
            'accepts_appointments' => true,
            'consultation_fee' => 1000,
        ]);
    }

    private function makeFacilityPayment(Facility $facility, Doctor $doctor): Payment
    {
        $patient = $this->makeVerifiedUser('patient');
        $session = ClinicSession::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'session_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'slot_duration_minutes' => 30,
            'max_appointments' => 6,
            'booked_appointments' => 1,
            'consultation_fee' => 1000,
            'status' => 'confirmed',
            'doctor_confirmation' => 'confirmed',
            'facility_confirmation' => 'confirmed',
        ]);

        $appointment = Appointment::create([
            'appointment_number' => 'APT-'.uniqid(),
            'user_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_session_id' => $session->id,
            'facility_id' => $facility->id,
            'appointment_date' => $session->session_date,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'amount_paid' => '4500.00',
        ]);

        return Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'appointment_id' => $appointment->id,
            'user_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'gross_amount' => '5000.00',
            'commission_amount' => '500.00',
            'net_amount' => '4500.00',
            'currency' => 'KES',
            'status' => 'paid',
            'recipient_type' => 'facility',
        ]);
    }

    public function test_operating_hours_index_and_save(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        $this->actingAsUser($admin)
            ->getJson('/api/facility/operating-hours')
            ->assertOk()->assertJsonCount(0, 'data');

        $hours = [];
        foreach (range(0, 6) as $day) {
            $hours[] = ['day_of_week' => $day, 'status' => $day === 0 || $day === 6 ? 'closed' : 'open', 'open_time' => '08:00', 'close_time' => '17:00'];
        }
        $this->actingAsUser($admin)
            ->putJson('/api/facility/operating-hours', ['hours' => $hours])
            ->assertOk()->assertJsonCount(7, 'data');

        $this->actingAsUser($admin)
            ->getJson('/api/facility/operating-hours')
            ->assertOk()->assertJsonCount(7, 'data');
    }

    public function test_operating_hours_are_admin_only(): void
    {
        $staff = $this->makeVerifiedUser('facility-staff');
        $facility = $this->makeCompleteFacility($staff);

        $this->actingAsUser($staff)
            ->getJson('/api/facility/operating-hours')
            ->assertStatus(403);
    }

    public function test_services_crud_is_facility_scoped(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $relationship = $this->makeRelationship($doctor, $facilityA);

        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);

        $created = $this->actingAsUser($adminA)
            ->postJson('/api/facility/services', ['doctor_id' => $doctor->id, 'service_name' => 'Consultation', 'price' => 1500, 'duration_minutes' => 30])
            ->assertStatus(201)->json('data');

        $this->assertSame('Consultation', $created['service_name']);

        $this->actingAsUser($adminB)
            ->getJson('/api/facility/services')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsUser($adminB)
            ->patchJson("/api/facility/services/{$created['id']}", ['price' => 999])
            ->assertNotFound();

        $this->actingAsUser($adminA)
            ->patchJson("/api/facility/services/{$created['id']}", ['price' => 1600])
            ->assertOk()->assertJsonPath('data.price', 1600);

        $this->assertDatabaseHas('doctor_facility_services', ['id' => $created['id'], 'doctor_facility_id' => $relationship->id]);
    }

    public function test_schedules_list_is_facility_scoped(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $relationship = $this->makeRelationship($doctor, $facilityA);

        DoctorFacilitySchedule::create([
            'doctor_facility_id' => $relationship->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'slot_duration_minutes' => 30,
            'max_appointments' => 8,
            'is_active' => true,
        ]);

        $adminB = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($adminB);

        $this->actingAsUser($adminA)
            ->getJson('/api/facility/schedules')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.day_of_week', 1);

        $this->actingAsUser($adminB)
            ->getJson('/api/facility/schedules')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_payments_are_scoped_to_own_facility(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $this->makeFacilityPayment($facilityA, $doctor);

        $adminB = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($adminB);

        $this->actingAsUser($adminA)
            ->getJson('/api/facility/payments')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsUser($adminB)
            ->getJson('/api/facility/payments')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_transactions_include_payments_with_totals(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $this->makeFacilityPayment($facility, $doctor);

        $this->actingAsUser($admin)
            ->getJson('/api/facility/transactions')
            ->assertOk()
            ->assertJsonPath('totals.count', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_reports_return_analytics_shape(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $this->makeFacilityPayment($facility, $doctor);

        $this->actingAsUser($admin)
            ->getJson('/api/facility/reports?days=30')
            ->assertOk()
            ->assertJsonPath('data.range.days', 30)
            ->assertJsonStructure(['data' => ['appointments', 'revenue', 'by_doctor', 'daily']]);
    }

    public function test_settings_round_trip(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        $this->actingAsUser($admin)
            ->putJson('/api/facility/settings', [FacilitySetting::KEY_SLOT_DURATION => '30', FacilitySetting::KEY_CURRENCY => 'KES'])
            ->assertOk();

        $this->actingAsUser($admin)
            ->getJson('/api/facility/settings')
            ->assertOk()
            ->assertJsonPath('data.'.FacilitySetting::KEY_SLOT_DURATION, '30');
    }

    public function test_non_facility_role_is_rejected(): void
    {
        $patient = $this->makeVerifiedUser('patient');

        $this->actingAsUser($patient)
            ->getJson('/api/facility/operating-hours')
            ->assertStatus(403);
        $this->actingAsUser($patient)
            ->getJson('/api/facility/reports')
            ->assertStatus(403);
        $this->actingAsUser($patient)
            ->getJson('/api/facility/transactions')
            ->assertStatus(403);
    }
}
