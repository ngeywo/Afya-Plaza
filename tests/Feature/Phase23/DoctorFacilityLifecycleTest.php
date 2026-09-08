<?php

namespace Tests\Feature\Phase23;

use App\Enums\DoctorRelationshipStatus;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilityService;
use App\Models\Facility;
use Illuminate\Testing\TestResponse;

/**
 * Phase 23 (Facility Management): the doctor-facility relationship is a
 * first-class, audited contract. INVITATION vs JOIN REQUEST; accept → ACTIVE;
 * decline → REJECTED; suspend; reactivate; end → ENDED. Both parties may
 * manage their schedules and services — always resource-scoped.
 */
class DoctorFacilityLifecycleTest extends Phase23TestCase
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

    private function facilityInvite(Doctor $doctor, $facilityAdmin, Facility $facility): TestResponse
    {
        return $this->actingAsUser($facilityAdmin)
            ->postJson("/api/facility/doctors/{$doctor->id}/invite", ['facility_id' => $facility->id]);
    }

    public function test_facility_invites_doctor_then_doctor_accepts(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);

        $this->facilityInvite($doctor, $facilityUser, $facility)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'invited');

        $relationshipId = DoctorFacility::where('doctor_id', $doctor->id)->firstOrFail()->id;

        $this->actingAsUser($doctorUser)
            ->postJson("/api/doctor/relationships/{$relationshipId}/accept")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_authoritative', true);

        $this->assertDatabaseHas('doctor_facilities', [
            'id' => $relationshipId,
            'status' => 'active',
            'is_active' => 1,
        ]);
        // Accepting a relationship seeds its default service.
        $this->assertDatabaseHas('doctor_facility_services', [
            'doctor_facility_id' => $relationshipId,
            'service_name' => 'Consultation',
        ]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $facilityUser->id, 'action' => 'doctor_facility.invited']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $doctorUser->id, 'action' => 'doctor_facility.accepted_invite']);
    }

    public function test_duplicate_active_relationship_is_rejected(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);
        $this->activeRelationship($doctor, $facility);

        $this->facilityInvite($doctor, $facilityUser, $facility)
            ->assertStatus(409)
            ->assertJsonPath('code', 'RELATIONSHIP_EXISTS');
    }

    public function test_doctor_join_request_then_facility_approval(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);

        $requested = $this->actingAsUser($doctorUser)
            ->postJson('/api/doctor/relationships/join', ['facility_id' => $facility->id])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $relationshipId = $requested->json('data.id');
        $this->assertDatabaseHas('doctor_facilities', ['id' => $relationshipId, 'status' => 'pending', 'requested_by' => $doctorUser->id]);

        $this->actingAsUser($facilityUser)
            ->postJson("/api/facility/relationships/{$relationshipId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $doctorUser->id, 'action' => 'doctor_facility.join_requested']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $facilityUser->id, 'action' => 'doctor_facility.request_approved']);
    }

    public function test_doctor_declines_invitation_records_rejected_alias(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);

        $relationshipId = $this->facilityInvite($doctor, $facilityUser, $facility)->json('data.id');

        $this->actingAsUser($doctorUser)
            ->postJson("/api/doctor/relationships/{$relationshipId}/decline", ['reason' => 'Not interested at this time.'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.status_label', 'Rejected');

        $this->assertTrue(DoctorFacility::findOrFail($relationshipId)->isRejected());
    }

    public function test_suspend_reactivate_and_end_lifecycle(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);
        $id = $this->activeRelationship($doctor, $facility)->id;

        // Suspended → not active, no bookings possible.
        $this->actingAsUser($facilityUser)
            ->postJson("/api/facility/relationships/{$id}/suspend", ['reason' => 'Compliance review.'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', ['resource_id' => $id, 'action' => 'doctor_facility.suspended']);

        // Reactivated → active again.
        $this->actingAsUser($facilityUser)
            ->postJson("/api/facility/relationships/{$id}/reactivate")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_active', true);

        // Ended → spec alias ENDED, active false.
        $this->actingAsUser($doctorUser)
            ->postJson("/api/doctor/relationships/{$id}/end", ['reason' => 'Accepting a position elsewhere.'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.status_label', 'Ended')
            ->assertJsonPath('data.is_active', false);

        $this->assertTrue(DoctorFacility::findOrFail($id)->isEnded());
        $this->assertDatabaseHas('audit_logs', ['resource_id' => $id, 'action' => 'doctor_facility.ended']);
    }

    public function test_invalid_transition_from_invited_to_ended_is_rejected(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);

        $id = $this->facilityInvite($doctor, $facilityUser, $facility)->json('data.id');

        $this->actingAsUser($doctorUser)
            ->postJson("/api/doctor/relationships/{$id}/end", ['reason' => 'Wants out.'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BAD_TRANSITION');
    }

    public function test_both_parties_can_update_settings(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);
        $id = $this->activeRelationship($doctor, $facility)->id;

        $this->actingAsUser($facilityUser)
            ->putJson("/api/facility/relationships/{$id}/settings", ['consultation_fee' => 1500, 'accepts_appointments' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.consultation_fee', '1500.00');

        $this->actingAsUser($doctorUser)
            ->putJson("/api/doctor/relationships/{$id}/settings", ['consultation_fee' => 1750])
            ->assertStatus(200)
            ->assertJsonPath('data.consultation_fee', '1750.00');

        $this->assertDatabaseHas('audit_logs', ['resource_id' => $id, 'action' => 'doctor_facility.settings_updated']);
    }

    public function test_default_consultation_service_is_seeded_once(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);

        $id = $this->facilityInvite($doctor, $facilityUser, $facility)->json('data.id');
        $this->actingAsUser($doctorUser)->postJson("/api/doctor/relationships/{$id}/accept")->assertStatus(200);

        $service = DoctorFacilityService::where('doctor_facility_id', $id);
        $this->assertEquals(1, $service->count());

        $this->actingAsUser($facilityUser)
            ->putJson("/api/facility/relationships/{$id}/settings", ['consultation_fee' => 1200])
            ->assertStatus(200)
            ->assertJsonPath('data.consultation_fee', '1200.00');

        $this->assertEquals(1, DoctorFacilityService::where('doctor_facility_id', $id)->count());
    }

    public function test_services_are_relationship_scoped(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($facilityUser);
        $idA = $this->facilityInvite($doctor, $facilityUser, $facilityA)->json('data.id');
        $this->actingAsUser($doctorUser)->postJson("/api/doctor/relationships/{$idA}/accept")->assertStatus(200);

        $this->actingAsUser($facilityUser)
            ->postJson("/api/facility/relationships/{$idA}/services", [
                'service_name' => 'Minor Surgery',
                'price' => 5000,
                'duration_minutes' => 60,
            ])->assertStatus(201);

        $this->actingAsUser($facilityUser)
            ->postJson("/api/facility/relationships/{$idA}/services", [
                'service_name' => 'Minor Surgery',
                'price' => 7000,
            ])->assertStatus(422)->assertJsonPath('code', 'SERVICE_EXISTS');

        $this->actingAsUser($doctorUser)
            ->getJson("/api/doctor/relationships/{$idA}/services")
            ->assertStatus(200)
            ->assertJsonPath('data.0.service_name', 'Consultation')
            ->assertJsonPath('data.1.service_name', 'Minor Surgery');
    }

    public function test_facility_admin_cannot_manage_another_facilitys_relationship(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $adminA = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityB = $this->makeCompleteFacility($adminB);
        $idA = $this->activeRelationship($doctor, $facilityA)->id;

        $this->actingAsUser($adminB)
            ->getJson("/api/facility/relationships/{$idA}/schedules")
            ->assertStatus(404);

        $this->actingAsUser($adminB)
            ->postJson("/api/facility/relationships/{$idA}/end", ['reason' => 'No access.'])
            ->assertStatus(404);
    }

    public function test_doctor_cannot_manage_another_doctors_relationship(): void
    {
        $doctorAUser = $this->makeVerifiedUser('doctor');
        $doctorA = $this->makeCompleteDoctor($doctorAUser);
        $doctorBUser = $this->makeVerifiedUser('doctor');
        $this->makeCompleteDoctor($doctorBUser);
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityUser);
        $idA = $this->activeRelationship($doctorA, $facility)->id;

        $this->actingAsUser($doctorBUser)
            ->postJson("/api/doctor/relationships/{$idA}/accept")
            ->assertStatus(404);
    }
}
