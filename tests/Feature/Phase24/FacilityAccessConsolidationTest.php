<?php

namespace Tests\Feature\Phase24;

use App\Enums\DoctorRelationshipStatus;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;

/**
 * Regressions for the consolidated FacilityAccessService golden rule:
 * facility-scoped surfaces may only ever address the actor's own facilities.
 */
class FacilityAccessConsolidationTest extends Phase24TestCase
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

    private function makeSettlement(Facility $facility, Doctor $doctor, User $patient): Settlement
    {
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
        ]);

        $payment = Payment::create([
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

        return Settlement::create([
            'reference' => Settlement::generateReference(),
            'facility_id' => $facility->id,
            'doctor_id' => $doctor->id,
            'appointment_id' => $appointment->id,
            'payment_id' => $payment->id,
            'gross_amount' => '5000.00',
            'platform_commission' => '500.00',
            'facility_amount' => '3150.00',
            'doctor_amount' => '1350.00',
            'agreement_type' => 'revenue_share',
            'currency' => 'KES',
            'status' => 'pending',
        ]);
    }

    public function test_facility_settlements_are_scoped_to_own_facility(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $doctorA = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);
        $patient = $this->makeVerifiedUser('patient');

        $otherFacility = $this->makeSettlement($facilityA, $doctorA, $patient);
        $own = $this->makeSettlement($facilityB, $doctorA, $patient);

        $this->actingAsUser($adminB);
        $response = $this->getJson('/api/facility/settlements')->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($otherFacility->id, $ids);
    }

    public function test_non_workspace_user_cannot_reach_facility_settlements(): void
    {
        $patient = $this->makeVerifiedUser('patient');
        $this->actingAsUser($patient);

        $this->getJson('/api/facility/settlements')->assertStatus(403);
    }

    public function test_agreement_store_is_scoped_to_own_facility(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $doctorA = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);

        $otherFacilityDf = $this->makeRelationship($doctorA, $facilityA);
        $ownDf = $this->makeRelationship($doctorA, $facilityB);

        $this->actingAsUser($adminB);

        // Admin B cannot attach an agreement to facility A's relationship.
        $this->postJson('/api/facility/agreements', [
            'doctor_facility_id' => $otherFacilityDf->id,
            'agreement_type' => 'revenue_share',
            'doctor_share_percentage' => 70,
            'facility_share_percentage' => 30,
            'effective_from' => now()->toDateString(),
        ])->assertStatus(403);

        // Admin B can attach one to their own relationship.
        $this->postJson('/api/facility/agreements', [
            'doctor_facility_id' => $ownDf->id,
            'agreement_type' => 'revenue_share',
            'doctor_share_percentage' => 70,
            'facility_share_percentage' => 30,
            'effective_from' => now()->toDateString(),
        ])->assertStatus(201);
    }

    public function test_facility_staff_cannot_manage_agreements(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $df = $this->makeRelationship($doctor, $facility);

        $staff = $this->makeVerifiedUser('facility-staff');
        $facility->admins()->attach($staff->id, ['is_primary' => false]);
        $this->actingAsUser($staff);

        $this->postJson('/api/facility/agreements', [
            'doctor_facility_id' => $df->id,
            'agreement_type' => 'revenue_share',
            'doctor_share_percentage' => 70,
            'facility_share_percentage' => 30,
            'effective_from' => now()->toDateString(),
        ])->assertStatus(403);
    }
}
