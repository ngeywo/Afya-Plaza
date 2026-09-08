<?php

namespace Tests\Feature\Phase23;

use App\Enums\AccountState;
use App\Enums\DoctorRelationshipStatus;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\Facility;

/**
 * Phase 23 (Authorization Scoping): role + resource scope. Facility B can never
 * touch Facility A's data; doctors cannot touch relationships they are not
 * party to; patients cannot manage any relationship; scoping failures return
 * 404 (never leaking existence).
 */
class AuthorizationScopingTest extends Phase23TestCase
{
    private function activeRelationship(Doctor $doctor, Facility $facility): DoctorFacility
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

    private function facilityPair(): array
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);
        $idA = $this->activeRelationship($doctor, $facilityA)->id;

        return [$doctorUser, $adminA, $adminB, $facilityB, $idA];
    }

    public function test_facility_b_admin_cannot_read_a_relationships_schedules(): void
    {
        [, $adminA, $adminB, , $idA] = $this->facilityPair();

        $this->actingAsUser($adminB)
            ->getJson("/api/facility/relationships/{$idA}/schedules")
            ->assertStatus(404);

        // Its own surface (empty but valid collection) still works.
        $this->actingAsUser($adminA)
            ->getJson("/api/facility/relationships/{$idA}/schedules")
            ->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_facility_b_admin_cannot_act_on_a_relationship(): void
    {
        [, , $adminB, , $idA] = $this->facilityPair();

        $this->actingAsUser($adminB)
            ->postJson("/api/facility/relationships/{$idA}/suspend", ['reason' => 'Unauthorized.'])
            ->assertStatus(404);

        $this->actingAsUser($adminB)
            ->postJson("/api/facility/relationships/{$idA}/end", ['reason' => 'Unauthorized.'])
            ->assertStatus(404);
    }

    public function test_unrelated_doctor_cannot_approve_a_join_request(): void
    {
        $doctorAUser = $this->makeVerifiedUser('doctor');
        $doctorA = $this->makeCompleteDoctor($doctorAUser);
        $doctorBUser = $this->makeVerifiedUser('doctor');
        $this->makeCompleteDoctor($doctorBUser);
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $idA = $this->activeRelationship($doctorA, $facility)->id;

        $this->actingAsUser($doctorBUser)
            ->postJson("/api/doctor/relationships/{$idA}/accept")
            ->assertStatus(404);
    }

    public function test_patient_cannot_manage_relationships_or_payment_accounts(): void
    {
        $patient = $this->makeVerifiedUser('patient');
        [,,, $facilityB] = $this->facilityPair();

        // No doctor profile → the join surface refuses the request.
        $this->actingAsUser($patient)
            ->postJson('/api/doctor/relationships/join', ['facility_id' => $facilityB->id])
            ->assertStatus(404);

        $this->actingAsUser($patient)
            ->postJson('/api/facility/payment-accounts', [
                'facility_id' => $facilityB->id,
                'provider' => 'mpesa',
                'account_number' => '5551234',
            ])->assertStatus(403);
    }

    public function test_suspended_facility_admin_is_blocked_from_management(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->activeRelationship($doctor, $facility);

        $admin->transitionTo(AccountState::SUSPENDED);

        $this->actingAsUser($admin)
            ->postJson('/api/facility/payment-accounts', [
                'facility_id' => $facility->id,
                'provider' => 'mpesa',
                'account_number' => '5551234',
            ])->assertStatus(403);
    }

    public function test_platform_admin_can_read_platform_fee_configuration(): void
    {
        $superAdmin = $this->makeVerifiedUser('super-admin');
        $facilityUser = $this->makeVerifiedUser('facility-admin');

        $this->actingAsUser($superAdmin)
            ->getJson('/api/admin/platform-fees')
            ->assertStatus(200);

        $this->actingAsUser($facilityUser)
            ->getJson('/api/admin/platform-fees')
            ->assertStatus(403);
    }
}
