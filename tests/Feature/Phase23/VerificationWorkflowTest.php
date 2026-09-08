<?php

namespace Tests\Feature\Phase23;

use App\Models\Doctor;
use App\Models\Permission;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Testing\TestResponse;

/**
 * Phase 23 Section 7/9/10: evidence-driven doctor & facility verification.
 */
class VerificationWorkflowTest extends Phase23TestCase
{
    private function submitDoctorAs(User $doctorUser, array $overrides = []): TestResponse
    {
        return $this->actingAsUser($doctorUser)->postJson('/api/doctor/verification', array_merge([
            'registry_number' => 'KMPDC-'.uniqid(),
            'evidence' => ['https://cdn.example.com/license.pdf'],
            'verification_source' => 'KMPDC',
            'notes' => 'License uploaded for review.',
        ], $overrides));
    }

    private function submitFacilityAs($facilityUser, array $overrides = []): TestResponse
    {
        return $this->actingAsUser($facilityUser)->postJson('/api/facility/verification', array_merge([
            'registry_number' => 'FRS-'.uniqid(),
            'evidence' => ['https://cdn.example.com/registration.pdf'],
            'verification_source' => 'Facility Registry',
        ], $overrides));
    }

    // ─── Submission ────────────────────────────────────────────────────────────

    public function test_doctor_submission_opens_a_pending_request(): void
    {
        $user = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($user);

        $this->submitDoctorAs($user)->assertStatus(201)->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('verification_requests', [
            'verifiable_type' => Doctor::class,
            'verifiable_id' => $doctor->id,
            'type' => 'doctor',
            'status' => 'pending',
        ]);
        $this->assertEquals('pending', $doctor->fresh()->verification_status->value);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'verification.submitted']);
    }

    public function test_registry_number_is_required_when_missing(): void
    {
        $user = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($user);

        $this->submitDoctorAs($user, ['registry_number' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registry_number');
    }

    public function test_duplicate_registry_number_is_blocked_privacy_safe(): void
    {
        $userA = $this->makeVerifiedUser('doctor');
        $userB = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($userA, ['registry_number' => 'DUP-REG-1']);
        $this->makeDoctor($userB);

        $this->submitDoctorAs($userB, ['registry_number' => 'DUP-REG-1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registry_number');

        $error = $this->submitDoctorAs($userB, ['registry_number' => 'DUP-REG-1'])->json('errors.registry_number')[0];
        $this->assertStringContainsString('already associated', $error);
        $this->assertStringNotContainsString($userA->name, $error);
    }

    public function test_facility_submission_and_status(): void
    {
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $this->makeFacility($facilityUser);

        $this->submitFacilityAs($facilityUser)->assertStatus(201)->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/facility/verification')
            ->assertStatus(200)
            ->assertJsonPath('data.has_facility', true)
            ->assertJsonPath('data.verification_status', 'pending');
    }

    // ─── Admin review ──────────────────────────────────────────────────────────

    private function reviewAs(User $admin, VerificationRequest $request, array $payload)
    {
        return $this->actingAsUser($admin)->postJson("/api/admin/verification-requests/{$request->id}/review", $payload);
    }

    public function test_super_admin_approval_drives_doctor_to_verified(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($doctorUser);
        $this->submitDoctorAs($doctorUser);
        $verificationRequest = $doctor->verificationRequests()->first();
        $admin = $this->makeVerifiedUser('super-admin');

        $this->reviewAs($admin, $verificationRequest, ['decision' => 'approve'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $doctor->refresh();
        $this->assertTrue((bool) $doctor->is_verified);
        $this->assertEquals('verified', $doctor->verification_status->value);
        $this->assertEquals('active', $doctor->user->fresh()->account_state->value);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'verification.approved',
        ]);
        $this->assertEquals($admin->id, $verificationRequest->fresh()->reviewer_id);
        $this->assertNotNull($verificationRequest->fresh()->reviewed_at);
    }

    public function test_rejection_requires_a_reason_and_sets_rejected_state(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $this->submitDoctorAs($doctorUser);
        $verificationRequest = $doctor->verificationRequests()->first();
        $admin = $this->makeVerifiedUser('super-admin');

        // Without reason
        $this->reviewAs($admin, $verificationRequest, ['decision' => 'reject'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        // With reason
        $this->reviewAs($admin, $verificationRequest, [
            'decision' => 'reject',
            'reason' => 'Unable to confirm registration in KMPDC.',
            'notes' => 'Please re-upload a clearer license.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'rejected');

        $doctor->refresh();
        $this->assertFalse((bool) $doctor->is_verified);
        $this->assertEquals('rejected', $doctor->verification_status->value);
        $this->assertEquals('Unable to confirm registration in KMPDC.', $doctor->rejection_reason);
        $this->assertEquals($verificationRequest->fresh()->status->value, 'rejected');
    }

    public function test_request_more_info_keeps_subject_pending(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $this->submitDoctorAs($doctorUser);
        $verificationRequest = $doctor->verificationRequests()->first();
        $admin = $this->makeVerifiedUser('super-admin');

        $this->reviewAs($admin, $verificationRequest, ['decision' => 'request_more_info', 'reason' => 'Send a photo of your license'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'more_info')
            ->assertJsonPath('data.requested_changes', 'Send a photo of your license');

        $doctor->refresh();
        $this->assertEquals('pending', $doctor->verification_status->value);
    }

    public function test_suspend_requires_reason_and_deverifies(): void
    {
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeFacility($facilityUser, ['is_verified' => true, 'verification_status' => 'verified']);
        $this->submitFacilityAs($facilityUser);
        $verificationRequest = $facility->verificationRequests()->first();
        $admin = $this->makeVerifiedUser('super-admin');

        $this->reviewAs($admin, $verificationRequest, ['decision' => 'suspend'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->reviewAs($admin, $verificationRequest, ['decision' => 'suspend', 'reason' => 'Regulatory compliance concern.'])
            ->assertStatus(200)->assertJsonPath('data.status', 'suspended');

        $facility->refresh();
        $this->assertEquals('suspended', $facility->verification_status->value);
        $this->assertFalse((bool) $facility->is_verified);
        $this->assertEquals('Regulatory compliance concern.', $facility->suspension_reason);
    }

    public function test_already_reviewed_request_is_conflict_409(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $this->submitDoctorAs($doctorUser);
        $verificationRequest = $doctor->verificationRequests()->first();
        $admin = $this->makeVerifiedUser('super-admin');

        $this->reviewAs($admin, $verificationRequest, ['decision' => 'approve'])->assertStatus(200);

        // A second reviewer arrives at the (now approved) request.
        $secondAdmin = $this->makeVerifiedUser('super-admin');
        $this->reviewAs($secondAdmin, $verificationRequest, ['decision' => 'reject', 'reason' => 'Changed my mind.'])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'already been reviewed'));

        $this->assertEquals('approved', $verificationRequest->fresh()->status->value);
        $this->assertEquals($admin->id, $verificationRequest->fresh()->reviewer_id);
    }

    // ─── Authorization: admin queue scope ──────────────────────────────────────

    public function test_facility_admin_cannot_review_verification_requests(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $this->submitDoctorAs($doctorUser);
        $verificationRequest = $doctor->verificationRequests()->first();
        $facilityAdmin = $this->makeVerifiedUser('facility-admin');

        $this->actingAsUser($facilityAdmin)
            ->getJson('/api/admin/verification-requests')
            ->assertStatus(403);
        $this->reviewAs($facilityAdmin, $verificationRequest, ['decision' => 'approve'])
            ->assertStatus(403);
    }

    public function test_verification_queue_is_permission_gated_beyond_role(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $this->submitDoctorAs($doctorUser);
        $verificationRequest = $doctor->verificationRequests()->first();

        // A super-admin stripped of doctors.verify/facilities.verify still has the
        // role, but the permission middleware must deny the queue.
        $limited = $this->makeVerifiedUser('super-admin');
        $limited->roles()->first()->permissions()->detach(
            Permission::whereIn('slug', ['doctors.verify', 'facilities.verify'])->pluck('id')
        );

        $this->actingAsUser($limited)
            ->getJson('/api/admin/verification-requests')
            ->assertStatus(403);
        $this->reviewAs($limited, $verificationRequest, ['decision' => 'approve'])
            ->assertStatus(403);
        // users.manage is still held → account controls remain available.
        $this->actingAsUser($limited)->getJson('/api/admin/accounts')->assertStatus(200);

        // Restore the global role so a full super-admin below is unrestricted.
        $limited->roles()->first()->permissions()->sync(Permission::pluck('id'));

        // platform-admin (role) is also denied — the admin domain is super-admin only.
        $platform = $this->makeVerifiedUser('platform-admin');
        $this->actingAsUser($platform)->getJson('/api/admin/verification-requests')->assertStatus(403);

        // A full super-admin succeeds.
        $super = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($super)
            ->getJson('/api/admin/verification-requests')->assertStatus(200);
        $this->reviewAs($super, $verificationRequest, ['decision' => 'approve'])->assertStatus(200);
    }

    // ─── Re-verification (Section 10) ──────────────────────────────────────────

    public function test_verified_doctor_changing_registry_number_is_reverted_to_pending(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser, [
            'is_verified' => true,
            'verification_status' => 'verified',
            'verified_at' => now()->subDay(),
        ]);
        $doctor->specialties()->attach($this->seedSpecialty(), ['is_primary' => true]);

        $this->actingAsUser($doctorUser)->putJson('/api/doctor/profile', [
            'registry_number' => 'NEW-KMPDC-9999',
        ])->assertStatus(200);

        $doctor->refresh();
        $this->assertFalse((bool) $doctor->is_verified);
        $this->assertEquals('pending', $doctor->verification_status->value);

        $profileChange = $doctor->verificationRequests()->where('type', 'profile_change')->first();
        $this->assertNotNull($profileChange);
        $this->assertEquals('pending', $profileChange->status->value);
        $this->assertEquals(['changed_fields' => ['registry_number']], $profileChange->submitted_data);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $doctorUser->id,
            'action' => 'verification.reverification_triggered',
        ]);
    }

    public function test_verified_facility_changing_registry_number_is_reverted_to_pending(): void
    {
        $facilityUser = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeFacility($facilityUser, [
            'is_verified' => true,
            'verification_status' => 'verified',
            'verified_at' => now()->subDay(),
        ]);

        $this->actingAsUser($facilityUser)->putJson('/api/facility/profile', [
            'registry_number' => 'NEW-FRS-12345',
        ])->assertStatus(200);

        $facility->refresh();
        $this->assertFalse((bool) $facility->is_verified);
        $this->assertEquals('pending', $facility->verification_status->value);

        $profileChange = $facility->verificationRequests()->where('type', 'profile_change')->first();
        $this->assertNotNull($profileChange);
        $this->assertEquals('pending', $profileChange->status->value);
    }

    public function test_unverified_doctor_changing_registry_number_does_not_spawn_review(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($doctorUser, ['verification_status' => 'pending']);

        $this->actingAsUser($doctorUser)->putJson('/api/doctor/profile', ['registry_number' => 'X-1'])->assertStatus(200);
        $this->assertCount(0, $doctorUser->doctor->verificationRequests);
    }

    // ─── Evidence is never fabricated ──────────────────────────────────────────

    public function test_admin_approval_only_happens_through_evidenced_request(): void
    {
        // There is no controller path that flips is_verified without a request
        // record; the admin review endpoint itself refuses non-open requests.
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $orphan = VerificationRequest::create([
            'verifiable_type' => Doctor::class,
            'verifiable_id' => $doctor->id,
            'user_id' => $doctorUser->id,
            'type' => 'doctor',
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
        ]);
        $admin = $this->makeVerifiedUser('super-admin');

        $this->reviewAs($admin, $orphan, ['decision' => 'approve'])->assertStatus(409);
        $this->assertFalse((bool) $doctor->fresh()->is_verified);
    }
}
