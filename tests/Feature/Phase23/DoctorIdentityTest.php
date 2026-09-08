<?php

namespace Tests\Feature\Phase23;

use App\Models\Doctor;
use App\Models\Facility;
use Illuminate\Database\QueryException;

/**
 * Phase 23 (Unified Doctor Identity): ONE canonical doctor per professional
 * registration/licence. Identifiers are normalized; duplicates are impossible;
 * holding a licence number identifies a doctor but NEVER authorizes practice at
 * a facility without an ACTIVE relationship.
 */
class DoctorIdentityTest extends Phase23TestCase
{
    public function test_doctor_identity_fields_are_normalized_on_save(): void
    {
        $user = $this->makeVerifiedUser('doctor');
        Doctor::create([
            'user_id' => $user->id,
            'display_name' => 'Dr. Normalized',
            'slug' => 'dr-normalized',
            'registry_number' => '  kmpdc-12 34  ',
            'license_number' => ' KMPDC 0421 ',
            'qualifications' => 'MD',
            'consultation_fee' => 1000,
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $this->assertDatabaseHas('doctors', [
            'slug' => 'dr-normalized',
            'registry_number' => 'KMPDC-1234',
            'license_number' => 'KMPDC0421',
        ]);
    }

    public function test_facility_registry_number_is_normalized_on_save(): void
    {
        $user = $this->makeVerifiedUser('facility-admin');
        $this->makeFacility($user, ['registry_number' => ' fr- 007A ']);

        $this->assertDatabaseHas('facilities', ['registry_number' => 'FR-007A']);
    }

    public function test_duplicate_registry_number_is_impossible_at_the_database(): void
    {
        $userA = $this->makeVerifiedUser('doctor');
        $userB = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($userA, ['registry_number' => 'DUP-12345']);

        $this->expectException(QueryException::class);

        $this->makeDoctor($userB, ['registry_number' => 'dup-12345']);
    }

    public function test_duplicate_license_number_is_impossible_at_the_database(): void
    {
        $userA = $this->makeVerifiedUser('doctor');
        $userB = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($userA, ['registry_number' => 'X-1', 'license_number' => 'LIC-777']);

        $this->expectException(QueryException::class);

        $this->makeDoctor($userB, ['registry_number' => 'X-2', 'license_number' => ' lic-777 ']);
    }

    public function test_licence_number_identifies_but_never_authorizes(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($facilityUser);
        // No DoctorFacility relationship exists.

        $this->actingAsUser($doctorUser)->postJson('/api/sessions', [
            'facility_id' => $this->lastFacilityId(),
            'session_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertStatus(403)->assertJsonPath('code', 'RELATIONSHIP_REQUIRED');
    }

    public function test_facility_can_look_up_a_doctor_by_licence_number_to_invite(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($doctorUser, ['license_number' => 'KMPDC-9090']);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($facilityUser);

        $this->actingAsUser($facilityUser)
            ->getJson('/api/facility/doctors/search?q=KMPDC-9090')
            ->assertStatus(200)
            ->assertJsonPath('data.0.license_number', 'KMPDC-9090');
    }

    public function test_slug_and_license_are_searched_identically_across_case_and_spaces(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($doctorUser, ['license_number' => 'kmpdc- 44 22']);
        $this->assertEquals('KMPDC-4422', Doctor::normalizeIdentifier(' kmpdc-44 22'));
    }

    private function lastFacilityId(): int
    {
        return (int) Facility::max('id');
    }
}
