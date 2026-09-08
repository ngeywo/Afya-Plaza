<?php

namespace Tests\Feature\Phase23;

use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\VerificationCode;
use App\Services\AccountStateMachine;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 23 Section 3/4/6/17/21/22: authentication lifecycle, contact
 * verification, duplicate detection, password security and deactivation.
 */
class AccountLifecycleTest extends Phase23TestCase
{
    // ─── Registration + duplicate detection ────────────────────────────────────

    public function test_registration_creates_contact_unverified_account(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Patient',
            'email' => 'new@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'patient',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.account_state', 'contact_unverified');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'account_state' => 'contact_unverified']);
    }

    public function test_registration_duplicate_verified_phone_is_blocked_privacy_safe(): void
    {
        $existing = $this->makeUser('patient', ['phone' => '0722111222', 'phone_verified_at' => now()]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate',
            'email' => 'dupe@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'phone' => '0722111222',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $error = $response->json('errors.phone')[0];
        $this->assertStringContainsString('already associated with an account', $error);
        // Privacy-safe: must not reveal who owns the phone.
        $this->assertStringNotContainsString($existing->name, $error);
        $this->assertStringNotContainsString($existing->email, $error);

        $this->assertDatabaseMissing('users', ['email' => 'dupe@example.com']);
    }

    public function test_registration_duplicate_email_is_blocked_by_database_unique(): void
    {
        $this->makeUser('patient', ['email' => 'same@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Dupe',
            'email' => 'same@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    // ─── Login + closed accounts ───────────────────────────────────────────────

    public function test_suspended_account_cannot_login(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $user->update(['account_state' => 'suspended']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'closed'));
    }

    public function test_deactivated_account_cannot_login(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $user->update(['account_state' => 'deactivated']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_active_user_can_login_and_receives_lifecycle_payload(): void
    {
        $user = $this->makeVerifiedUser('patient');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(200)
            ->assertJsonPath('user.account_state', 'active')
            ->assertJsonPath('user.contact_verified', true);
    }

    public function test_login_records_audit_and_updates_last_login(): void
    {
        $user = $this->makeVerifiedUser('patient');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.login',
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    // ─── Account state derivation (lifecycle) ──────────────────────────────────

    public function test_verified_doctor_account_reaches_pending_once_profile_complete(): void
    {
        $user = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeCompleteDoctor($user);

        $state = app(AccountStateMachine::class)->derive($user);

        // Contact verified + complete profile + pending verification → verification_pending.
        $this->assertEquals('verification_pending', $state->value);
    }

    public function test_facility_admin_derives_pending_once_complete(): void
    {
        $user = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($user);

        $state = app(AccountStateMachine::class)->derive($user);

        $this->assertEquals('verification_pending', $state->value);
    }

    // ─── Contact verification (email/phone) ────────────────────────────────────

    public function test_phone_verification_flow(): void
    {
        $user = $this->makeUser('patient', ['phone' => '0722999888']);
        $this->actingAsUser($user);

        $send = $this->postJson('/api/account/verification/send', ['channel' => 'phone', 'address' => '0722999888'])
            ->assertStatus(200);
        $token = $send->json('data.token');
        $this->assertNotNull($token);

        VerificationCode::where('token', $token)->update(['code' => Hash::make('ABCDEF')]);

        $this->postJson('/api/account/verification/verify', [
            'channel' => 'phone',
            'token' => $token,
            'code' => 'ABCDEF',
        ])->assertStatus(200)
            ->assertJsonPath('data.phone_verified_at', fn ($v) => $v !== null);
    }

    public function test_wrong_code_consumes_attempt(): void
    {
        $user = $this->makeUser('patient', ['phone' => '0722777666']);
        $this->actingAsUser($user);

        $send = $this->postJson('/api/account/verification/send', ['channel' => 'phone', 'address' => '0722777666']);
        $token = $send->json('data.token');
        VerificationCode::where('token', $token)->update(['code' => Hash::make('ABCDEF')]);

        $this->postJson('/api/account/verification/verify', ['channel' => 'phone', 'token' => $token, 'code' => 'WRONG1'])
            ->assertStatus(422);

        $record = VerificationCode::where('token', $token)->first();
        $this->assertEquals(1, $record->attempts);
        $this->assertFalse($record->used);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->makeUser('patient', ['phone' => '0722555444']);
        $this->actingAsUser($user);

        $send = $this->postJson('/api/account/verification/send', ['channel' => 'phone', 'address' => '0722555444']);
        $token = $send->json('data.token');

        VerificationCode::where('token', $token)->update([
            'code' => Hash::make('ABCDEF'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/account/verification/verify', ['channel' => 'phone', 'token' => $token, 'code' => 'ABCDEF'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', fn ($m) => str_contains($m, 'expired'));
    }

    public function test_code_resend_is_cooldown_limited(): void
    {
        $user = $this->makeUser('patient', ['phone' => '0722444333']);
        $this->actingAsUser($user);

        $this->postJson('/api/account/verification/send', ['channel' => 'phone', 'address' => '0722444333'])
            ->assertStatus(200);

        // Immediate second request hits the 60s cooldown.
        $this->postJson('/api/account/verification/send', ['channel' => 'phone', 'address' => '0722444333'])
            ->assertStatus(422);
    }

    // ─── Sensitive contact change (Section 17) ─────────────────────────────────

    public function test_new_email_requires_code_and_current_password_to_apply(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $this->actingAsUser($user);

        // Request code for the NEW address.
        $send = $this->postJson('/api/account/verification/send', [
            'channel' => 'email',
            'address' => 'newcontact@example.com',
        ]);
        $token = $send->json('data.token');
        VerificationCode::where('token', $token)->update(['code' => Hash::make('ABCDEF')]);

        // Without the current password → rejected.
        $this->postJson('/api/account/verification/verify', [
            'channel' => 'email',
            'token' => $token,
            'code' => 'ABCDEF',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        // With the password → applied + verified.
        $this->postJson('/api/account/verification/verify', [
            'channel' => 'email',
            'token' => $token,
            'code' => 'ABCDEF',
            'current_password' => 'password',
        ])->assertStatus(200)
            ->assertJsonPath('data.contact_changed', true);

        $this->assertEquals('newcontact@example.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'account.contact_changed']);
    }

    // ─── Password security ─────────────────────────────────────────────────────

    public function test_change_password_requires_current_and_revokes_other_sessions(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $currentToken = $user->createToken('current-session');
        $user->createToken('other-device')->accessToken;

        $this->withToken($currentToken->plainTextToken)->postJson('/api/account/password/change', [
            'current_password' => 'password',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'auth.password_changed']);
        $this->assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
        $this->assertEquals(1, $user->fresh()->tokens()->count());
    }

    public function test_change_password_rejects_wrong_current(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $this->actingAsUser($user)->postJson('/api/account/password/change', [
            'current_password' => 'not-the-password',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    public function test_forgot_password_never_enumerates_users(): void
    {
        // Unknown email → generic message.
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'If an account exists'));

        // Known email → same surface message (token in data, but code is not).
        $user = $this->makeVerifiedUser('patient');
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'If an account exists'));
    }

    public function test_reset_password_revokes_all_sessions(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $user->createToken('one');
        $user->createToken('two');

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $token = $response->json('data.token');

        VerificationCode::where('token', $token)->update(['code' => Hash::make('ABCDEF')]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'code' => 'ABCDEF',
            'password' => 'BrandNewPass!1',
            'password_confirmation' => 'BrandNewPass!1',
        ])->assertStatus(200);

        $this->assertEquals(0, $user->fresh()->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'auth.password_reset']);
    }

    // ─── Sessions ──────────────────────────────────────────────────────────────

    public function test_user_can_list_and_revoke_sessions(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $user->createToken('mobile')->accessToken;
        $threatened = $user->createToken('threatened-device')->accessToken;

        $this->actingAsUser($user);
        $this->getJson('/api/account/sessions')->assertStatus(200);

        $this->deleteJson("/api/account/sessions/{$threatened->id}")->assertStatus(200);
        $this->assertNull($threatened->fresh());
    }

    public function test_user_cannot_revoke_foreign_session(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $other = $this->makeVerifiedUser('patient');
        $foreignToken = $other->createToken('secret-session')->accessToken;

        $this->actingAsUser($user);
        $this->deleteJson("/api/account/sessions/{$foreignToken->id}")->assertStatus(404);
        $this->assertNotNull($foreignToken->fresh());
    }

    // ─── Deactivation (Section 22) ─────────────────────────────────────────────

    public function test_deactivation_cancels_future_appointments_and_keeps_history(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $doctorUser = $this->makeVerifiedUser('doctor');
        $doctor = $this->makeDoctor($doctorUser);
        $facility = $this->makeFacility($this->makeVerifiedUser('facility-admin'));

        $session = ClinicSession::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'session_date' => today()->addDays(3)->toDateString(),
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

        $appointment = $session->appointments()->create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'clinic_session_id' => $session->id,
            'appointment_number' => Appointment::generateNumber(),
            'appointment_date' => today()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => 'confirmed',
            'amount_paid' => 1000,
            'payment_status' => 'paid',
        ]);

        $this->actingAsUser($user)->postJson('/api/account/deactivate', [
            'current_password' => 'password',
            'reason' => 'Moving abroad',
        ])->assertStatus(200);

        $this->assertEquals('deactivated', $user->fresh()->account_state->value);
        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->assertEquals(0, $user->fresh()->tokens()->count());
        $this->assertEquals('cancelled', $appointment->fresh()->status);
        // data preserved
        $this->assertNotNull($appointment->fresh());
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'account.deactivated']);
    }

    public function test_deactivation_requires_correct_password(): void
    {
        $user = $this->makeVerifiedUser('patient');
        $this->actingAsUser($user)->postJson('/api/account/deactivate', [
            'current_password' => 'wrong',
            'reason' => 'x',
        ])->assertStatus(422);
    }
}
