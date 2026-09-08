<?php

namespace Tests\Feature\Phase24;

use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\DoctorFacility;
use App\Models\Role;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 24: Super Admin marketplace + governance surfaces.
 *
 * Creates the platform-wide lists, governance actions (relationship lifecycle,
 * session/appointment cancels with notifications) and the platform settings and
 * reports surfaces. Every sensitive action is expected to be audited.
 */
class AdminMarketplaceFeatureTest extends Phase24TestCase
{
    use RefreshDatabase;

    // ─── Access control ───────────────────────────────────────────────────────

    public function test_platform_admin_cannot_access_marketplace_and_governance(): void
    {
        $this->actingAsUser($this->makeUser('platform-admin'));

        $this->getJson('/api/admin/marketplace/doctors')->assertStatus(403);
        $this->getJson('/api/admin/marketplace/facilities')->assertStatus(403);
        $this->getJson('/api/admin/specialties')->assertStatus(403);
        $this->getJson('/api/admin/services')->assertStatus(403);
        $this->getJson('/api/admin/relationships')->assertStatus(403);
        $this->getJson('/api/admin/sessions')->assertStatus(403);
        $this->getJson('/api/admin/appointments')->assertStatus(403);
        $this->getJson('/api/admin/settings')->assertStatus(403);
        $this->getJson('/api/admin/transactions')->assertStatus(403);
        $this->getJson('/api/admin/commissions')->assertStatus(403);
        $this->getJson('/api/admin/reports')->assertStatus(403);
        $this->getJson('/api/admin/verification-requests')->assertStatus(403);
        $this->getJson('/api/admin/accounts')->assertStatus(403);
    }

    public function test_super_admin_without_verify_permission_is_blocked_from_verification_queue(): void
    {
        $role = Role::where('slug', 'super-admin')->first();
        $role->permissions()->sync([]);

        $this->actingAsUser($this->makeUser('super-admin'));

        $this->getJson('/api/admin/verification-requests')->assertStatus(403);
        $this->getJson('/api/admin/accounts')->assertStatus(403);
    }

    public function test_super_admin_has_full_marketplace_access(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $this->getJson('/api/admin/marketplace/doctors')->assertOk();
        $this->getJson('/api/admin/marketplace/facilities')->assertOk();
    }

    // ─── Lists ────────────────────────────────────────────────────────────────

    public function test_marketplace_lists_return_presented_rows(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'), ['is_verified' => true, 'verification_status' => 'verified']);
        $facility = $this->makeCompleteFacility($this->makeVerifiedUser('facility-admin'));

        $doctorRes = $this->getJson('/api/admin/marketplace/doctors?verified=verified')->assertOk()->json('data');
        $this->assertCount(1, $doctorRes);
        $this->assertSame($doctor->id, $doctorRes[0]['id']);

        $facilityRes = $this->getJson('/api/admin/marketplace/facilities')->assertOk()->json('data');
        $this->assertCount(1, $facilityRes);
        $this->assertSame($facility->id, $facilityRes[0]['id']);

        $this->getJson('/api/admin/specialties')->assertOk()->json('data.0.name') === 'Cardiology';

        $services = $this->getJson('/api/admin/services')->assertOk()->json('data');
        $this->assertIsArray($services);
    }

    public function test_specialties_crud_and_delete_guard(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $created = $this->postJson('/api/admin/specialties', [
            'name' => 'Dermatology',
            'description' => 'Skin and hair.',
        ])->assertStatus(201)->json('data');
        $this->assertSame('dermatology', $created['slug']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.created', 'resource_type' => 'specialty']);

        $this->putJson("/api/admin/specialties/{$created['id']}", ['description' => 'Updated description'])
            ->assertOk()->assertJsonPath('data.description', 'Updated description');
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.updated']);

        // Assigned specialty cannot be deleted — deactivate instead.
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $specialty = Specialty::find($created['id']);
        $doctor->specialties()->attach($specialty->id, ['is_primary' => false]);

        $this->deleteJson("/api/admin/specialties/{$created['id']}")->assertStatus(422);
        $this->assertDatabaseHas('specialties', ['id' => $created['id']]);

        // Unassigned specialty can be deleted.
        $empty = $this->postJson('/api/admin/specialties', ['name' => 'Orthopedics'])->json('data');
        $this->deleteJson("/api/admin/specialties/{$empty['id']}")->assertOk();
        $this->assertDatabaseMissing('specialties', ['id' => $empty['id']]);
    }

    // ─── Relationship governance ──────────────────────────────────────────────

    private function makeRelationship(string $status): DoctorFacility
    {
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $facility = $this->makeCompleteFacility($this->makeVerifiedUser('facility-admin'));

        return DoctorFacility::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'consultation_fee' => 1500,
            'accepts_appointments' => true,
            'status' => $status,
        ]);
    }

    public function test_relationship_lifecycle_is_governed_and_audited(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $relationship = $this->makeRelationship('pending');

        $this->postJson("/api/admin/relationships/{$relationship->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('audit_logs', ['action' => 'relationship.approve']);

        $this->postJson("/api/admin/relationships/{$relationship->id}/suspend", ['reason' => 'Credential hold'])
            ->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseHas('audit_logs', ['action' => 'relationship.suspend', 'reason' => 'Credential hold']);

        $this->postJson("/api/admin/relationships/{$relationship->id}/reactivate")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson("/api/admin/relationships/{$relationship->id}/end")
            ->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('audit_logs', ['action' => 'relationship.end']);

        $fresh = $relationship->fresh();
        $this->assertNotNull($fresh->ended_at_governance);
        $this->assertNotNull($fresh->approved_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'relationship.end',
            'resource_type' => 'doctor_facility',
            'resource_id' => $relationship->id,
        ]);
    }

    public function test_relationship_reject_requires_reason_and_declined_cannot_resume(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $relationship = $this->makeRelationship('pending');

        $this->postJson("/api/admin/relationships/{$relationship->id}/reject")->assertStatus(422);

        $this->postJson("/api/admin/relationships/{$relationship->id}/reject", ['reason' => 'Out of region'])
            ->assertOk()->assertJsonPath('data.status', 'declined');
        $this->assertSame('Out of region', $relationship->fresh()->decline_reason);

        // DECLINED is a terminal state — no transition out.
        $this->postJson("/api/admin/relationships/{$relationship->id}/approve")->assertStatus(422);
        $this->postJson("/api/admin/relationships/{$relationship->id}/reactivate")->assertStatus(422);
    }

    public function test_relationship_illegal_transition_is_rejected(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        // PENDING -> end is not a legal transition.
        $relationship = $this->makeRelationship('pending');

        $this->postJson("/api/admin/relationships/{$relationship->id}/end")->assertStatus(422);
        $this->assertSame('pending', $relationship->fresh()->status?->value);
    }

    // ─── Session + appointment cancellation overrides ────────────────────────

    private function makeSessionAndBookedAppointment(): array
    {
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        $facility = $this->makeCompleteFacility($this->makeVerifiedUser('facility-admin'));
        $patient = $this->makeVerifiedUser('patient');

        $session = ClinicSession::create([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'session_date' => now()->addDays(2)->toDateString(),
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
            'facility_id' => $facility->id,
            'clinic_session_id' => $session->id,
            'appointment_date' => $session->session_date,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => 'confirmed',
            'amount_paid' => 1000,
            'payment_status' => 'paid',
        ]);

        return compact('session', 'appointment');
    }

    public function test_cancel_session_invalidates_bookings_notifies_and_is_audited(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $fixture = $this->makeSessionAndBookedAppointment();

        $response = $this->postJson("/api/admin/sessions/{$fixture['session']->id}/cancel", ['reason' => 'Facility maintenance'])
            ->assertOk();

        $this->assertSame('Session cancelled. 1 booking(s) affected.', $response->json('message'));

        $this->assertSame('cancelled', $fixture['session']->fresh()->status);
        $this->assertSame('cancelled', $fixture['appointment']->fresh()->status);
        $this->assertStringContainsString('Facility maintenance', $fixture['appointment']->fresh()->cancellation_reason);

        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic_session.cancelled_override']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.cancelled']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $fixture['appointment']->user_id]);
    }

    public function test_cancel_session_requires_reason_and_rejects_finished_sessions(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $fixture = $this->makeSessionAndBookedAppointment();

        $this->postJson("/api/admin/sessions/{$fixture['session']->id}/cancel")->assertStatus(422);

        $fixture['session']->update(['status' => 'completed']);
        $this->postJson("/api/admin/sessions/{$fixture['session']->id}/cancel", ['reason' => 'Late'])
            ->assertStatus(422);
    }

    public function test_exceptional_appointment_cancel_is_audited_and_notifies(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $fixture = $this->makeSessionAndBookedAppointment();

        $this->postJson("/api/admin/appointments/{$fixture['appointment']->id}/cancel", ['reason' => 'Duplicate booking'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment.cancelled_exceptionally',
            'resource_type' => 'appointment',
            'resource_id' => $fixture['appointment']->id,
            'reason' => 'Duplicate booking',
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $fixture['appointment']->user_id]);
    }

    // ─── Platform settings ────────────────────────────────────────────────────

    public function test_settings_round_trip_is_audited(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $this->getJson('/api/admin/settings')->assertOk()->assertJsonStructure(['data' => []]);

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'platform_name' => 'Afya Care',
                'registration_open' => false,
                'default_commission_rate' => '7.5',
                'default_currency' => 'KES',
            ],
            'reason' => 'Relaunch config',
        ])->assertOk()->assertJsonPath('data.platform_name', 'Afya Care');

        $this->assertDatabaseHas('platform_settings', ['key' => 'platform_name', 'value' => 'Afya Care']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'registration_open', 'value' => '']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'default_commission_rate', 'value' => '7.5']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.settings_updated', 'reason' => 'Relaunch config']);

        // Unknown keys are ignored.
        $this->putJson('/api/admin/settings', [
            'settings' => ['hack_key' => 'nope'],
            'reason' => 'Cleanup',
        ])->assertOk();
        $this->assertDatabaseMissing('platform_settings', ['key' => 'hack_key']);
    }

    // ─── Reports / analytics / finance surfaces ──────────────────────────────

    public function test_reports_surface(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $data = $this->getJson('/api/admin/reports?days=30')->assertOk()->json('data');

        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('daily_appointments', $data);
        $this->assertArrayHasKey('total_doctors', $data['counts']);
        $this->assertArrayHasKey('appointments_today', $data['counts']);
    }

    public function test_analytics_surface(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $data = $this->getJson('/api/admin/analytics?days=90')->assertOk()->json('data');

        $this->assertArrayHasKey('appointments', $data);
        $this->assertArrayHasKey('revenue', $data);
        $this->assertArrayHasKey('weekly_trend', $data);
        $this->assertCount(8, $data['weekly_trend']);
    }

    public function test_transactions_and_commissions_surfaces(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $tx = $this->getJson('/api/admin/transactions')->assertOk()->json();
        $this->assertArrayHasKey('data', $tx);
        $this->assertArrayHasKey('totals', $tx);

        $comm = $this->getJson('/api/admin/commissions?days=90')->assertOk()->json('data');
        $this->assertArrayHasKey('summary', $comm);
        $this->assertArrayHasKey('by_facility', $comm);
        $this->assertArrayHasKey('by_doctor', $comm);
    }

    // ─── Workspace context ────────────────────────────────────────────────────

    public function test_workspace_switch_is_read_only_context(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $doctor = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));

        $this->postJson('/api/admin/workspace/switch', ['type' => 'doctor', 'id' => $doctor->id])
            ->assertOk()->assertJsonPath('data.workspace_type', 'doctor');

        $this->getJson('/api/admin/workspace')->assertOk()->assertJsonPath('data.workspace_type', 'doctor');

        $this->postJson('/api/admin/workspace/exit')->assertOk()->assertJsonPath('data.workspace_type', 'platform');
    }
}
