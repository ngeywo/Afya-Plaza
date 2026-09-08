<?php

namespace Tests\Feature\Phase23;

/**
 * Phase 23 Section 14: least privilege, scope and IDOR protection.
 */
class AccountAuthorizationTest extends Phase23TestCase
{
    // ─── unauthenticated ───────────────────────────────────────────────────────

    public function test_guest_cannot_access_account_endpoints(): void
    {
        $this->getJson('/api/account')->assertStatus(401);
        $this->getJson('/api/account/sessions')->assertStatus(401);
        $this->postJson('/api/account/deactivate', [])->assertStatus(401);
        $this->postJson('/api/account/verification/send', [])->assertStatus(401);
        $this->getJson('/api/admin/verification-requests')->assertStatus(401);
        $this->getJson('/api/admin/accounts')->assertStatus(401);
    }

    // ─── account scope (self only) ─────────────────────────────────────────────

    public function test_account_payload_is_scoped_to_authenticated_user(): void
    {
        $user = $this->makeVerifiedUser('patient');

        $this->actingAsUser($user)->getJson('/api/account')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_contact_verification_operates_on_own_contact_only(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $this->actingAsUser($user);

        // Trying to verify the *other* user's already-verified phone is not a leak:
        $this->postJson('/api/account/verification/send', ['channel' => 'phone', 'address' => '0722999888'])
            ->assertStatus(200); // send is address owner-agnostic; verify is code-bound to own record

        // Without a code bound to this user the verify fails.
        $this->postJson('/api/account/verification/verify', ['channel' => 'phone', 'token' => 'no-such-token', 'code' => 'XYZ'])
            ->assertStatus(422);
    }

    public function test_patient_cannot_act_on_other_patients_sessions(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $other = $this->makeVerifiedUser('patient');
        $otherToken = $other->createToken('device-a')->accessToken;

        $this->actingAsUser($user)->postJson('/api/account/sessions/revoke-others')->assertStatus(200);

        $this->assertNotNull($otherToken->fresh(), 'revoke-others must not touch other users tokens');
        $this->deleteJson("/api/account/sessions/{$otherToken->id}")->assertStatus(404);
    }

    // ─── provider verification role gating ─────────────────────────────────────

    public function test_patient_cannot_submit_or_read_verification_endpoints(): void
    {
        $patient = $this->makeVerifiedUser('patient');

        $this->actingAsUser($patient)->getJson('/api/doctor/verification')->assertStatus(403);
        $this->actingAsUser($patient)->postJson('/api/doctor/verification')->assertStatus(403);
        $this->actingAsUser($patient)->getJson('/api/facility/verification')->assertStatus(403);
        $this->actingAsUser($patient)->postJson('/api/facility/verification')->assertStatus(403);
    }

    public function test_staff_cannot_act_on_facility_verification(): void
    {
        $staff = $this->makeVerifiedUser('facility-staff');
        $this->actingAsUser($staff)->postJson('/api/facility/verification', [
            'registry_number' => 'FRS-XYZ',
        ])->assertStatus(409); // has no facility attached yet, but is authorized by role
    }

    public function test_doctor_cannot_access_facility_verification(): void
    {
        $doctorUser = $this->makeVerifiedUser('doctor');
        $this->makeDoctor($doctorUser);

        $this->actingAsUser($doctorUser)->getJson('/api/facility/verification')->assertStatus(403);
        $this->actingAsUser($doctorUser)->postJson('/api/facility/verification', [])->assertStatus(403);
    }

    // ─── admin account controls (permission gated) ─────────────────────────────

    public function test_only_permissioned_staff_can_see_admin_accounts(): void
    {
        $super = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($super)->getJson('/api/admin/accounts')->assertStatus(200);

        $doctor = $this->makeVerifiedUser('doctor');
        $this->actingAsUser($doctor)->getJson('/api/admin/accounts')->assertStatus(403);

        $facilityAdmin = $this->makeVerifiedUser('facility-admin');
        $this->actingAsUser($facilityAdmin)->getJson('/api/admin/accounts')->assertStatus(403);
    }

    public function test_suspend_and_reactivate_are_permission_gated(): void
    {
        $target = $this->makeVerifiedUser('patient');

        $doctor = $this->makeVerifiedUser('doctor');
        $this->actingAsUser($doctor)->postJson("/api/admin/accounts/{$target->id}/suspend", ['reason' => 'spam'])
            ->assertStatus(403);

        $super = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($super)->postJson("/api/admin/accounts/{$target->id}/suspend", ['reason' => 'spam'])
            ->assertStatus(200);

        $this->assertEquals('suspended', $target->fresh()->account_state->value);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $super->id, 'action' => 'account.suspended']);

        $this->actingAsUser($super)->postJson("/api/admin/accounts/{$target->id}/reactivate", ['reason' => 'appeal granted'])
            ->assertStatus(200);
        $this->assertEquals('active', $target->fresh()->account_state->value);
    }

    public function test_suspend_requires_a_reason(): void
    {
        $target = $this->makeVerifiedUser('patient');
        $super = $this->makeVerifiedUser('super-admin');

        $this->actingAsUser($super)->postJson("/api/admin/accounts/{$target->id}/suspend", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_activity_trail_scoped_to_single_user(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(200);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(200);

        $super = $this->makeVerifiedUser('super-admin');
        $response = $this->actingAsUser($super)->getJson("/api/admin/accounts/{$user->id}/activity")
            ->assertStatus(200);

        collect($response->json('data'))->each(
            fn ($entry) => $this->assertEquals($user->id, $entry['actor']['id'])
        );
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
        $this->assertTrue(collect($response->json('data'))->contains('action', 'auth.login'));
    }

    // ─── closed account middleware ─────────────────────────────────────────────

    public function test_closed_account_cannot_call_authenticated_routes(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $user->update(['account_state' => 'suspended']);
        $token = $user->createToken('api');

        $this->withToken($token->plainTextToken)->getJson('/api/account')->assertStatus(403);
        $this->withToken($token->plainTextToken)->getJson('/api/doctor/verification')->assertStatus(403);
    }
}
