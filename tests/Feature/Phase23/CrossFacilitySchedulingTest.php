<?php

namespace Tests\Feature\Phase23;

use App\Enums\DoctorRelationshipStatus;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\Facility;

/**
 * Phase 23 (Cross-Facility Scheduling): a doctor is one person — they cannot
 * hold clinics at two facilities at the same time. Every session must belong to
 * an ACTIVE relationship, and a configurable transition buffer protects the gap
 * between clinics, across facilities AND across weekly schedules.
 */
class CrossFacilitySchedulingTest extends Phase23TestCase
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

    private function createSession(Doctor $doctor, Facility $facility, string $start, string $end)
    {
        return $this->actingAsUser($doctor->user)
            ->postJson('/api/sessions', [
                'facility_id' => $facility->id,
                'session_date' => now()->addDays(3)->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
            ]);
    }

    public function test_session_creation_requires_an_active_relationship(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);
        // No relationship yet.

        $this->createSession($doctor, $facility, '09:00', '10:00')
            ->assertStatus(403)
            ->assertJsonPath('code', 'RELATIONSHIP_REQUIRED');
    }

    public function test_buffer_conflict_blocked_across_facilities(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);
        $this->activeRelationship($doctor, $facilityA);
        $this->activeRelationship($doctor, $facilityB);

        // First clinic at Facility A, 09:00–10:00.
        $this->createSession($doctor, $facilityA, '09:00', '10:00')->assertStatus(201);

        // 10:15–11:00 at Facility B is only 15 minutes after — inside the
        // 30-minute transition buffer → collision.
        $this->createSession($doctor, $facilityB, '10:15', '11:00')
            ->assertStatus(409)
            ->assertJsonPath('code', 'DOCTOR_COLLISION')
            ->assertJsonPath('collision.facility', $facilityA->name)
            ->assertJsonPath('collision.buffer_minutes', 30);

        // 10:45–11:30 clears the buffer → allowed.
        $this->createSession($doctor, $facilityB, '10:45', '11:30')->assertStatus(201);
    }

    public function test_sessions_that_do_not_overlap_are_allowed(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);
        $this->activeRelationship($doctor, $facilityA);
        $this->activeRelationship($doctor, $facilityB);

        $this->createSession($doctor, $facilityA, '09:00', '10:00')->assertStatus(201);
        $this->createSession($doctor, $facilityB, '10:45', '11:30')->assertStatus(201);
    }

    public function test_weekly_schedule_buffer_applies_across_facilities(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);
        $idA = $this->activeRelationship($doctor, $facilityA)->id;
        $idB = $this->activeRelationship($doctor, $facilityB)->id;

        // Weekly Monday clinic at Facility A, 09:00–12:00.
        $this->actingAsUser($adminA)->postJson("/api/facility/relationships/{$idA}/schedules", [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 30,
        ])->assertStatus(201);

        // 12:15 start at Facility B conflicts within the 30-minute buffer.
        $this->actingAsUser($adminB)->postJson("/api/facility/relationships/{$idB}/schedules", [
            'day_of_week' => 1,
            'start_time' => '12:15',
            'end_time' => '14:00',
        ])->assertStatus(422)->assertJsonPath('code', 'SCHEDULE_CONFLICT');

        // 12:45 start clears the buffer.
        $this->actingAsUser($adminB)->postJson("/api/facility/relationships/{$idB}/schedules", [
            'day_of_week' => 1,
            'start_time' => '12:45',
            'end_time' => '14:00',
        ])->assertStatus(201);
    }

    public function test_public_doctor_today_and_facility_doctors_endpoints(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $this->activeRelationship($doctor, $facilityA)->id;

        ClinicSession::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facilityA->id,
            'session_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'confirmed',
            'doctor_confirmation' => 'confirmed',
            'facility_confirmation' => 'confirmed',
            'consultation_fee' => 1000,
        ]);

        $this->getJson("/api/doctors/{$doctor->slug}/today")
            ->assertStatus(200)
            ->assertJsonPath('data.licensed_facilities.0.id', $facilityA->id)
            ->assertJsonPath('data.today.0.facility.id', $facilityA->id);

        $this->getJson("/api/facilities/{$facilityA->slug}/doctors")
            ->assertStatus(200)
            ->assertJsonPath('data.0.slug', $doctor->slug);
    }
}
