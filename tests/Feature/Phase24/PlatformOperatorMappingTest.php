<?php

namespace Tests\Feature\Phase24;

/**
 * Phase 24: role -> dashboard mapping.
 *
 * The /admin prefix is operator-gated (super-admin || platform-admin) while
 * finance/plans/roles/permissions/subscriptions remain super-admin-only.
 */
class PlatformOperatorMappingTest extends Phase24TestCase
{
    private function makeOperatorAccessible(): array
    {
        return [
            ['super-admin', 200],
            ['platform-admin', 200],
        ];
    }

    public function test_operator_can_access_control_centre_dashboard(): void
    {
        foreach ($this->makeOperatorAccessible() as [$slug, $status]) {
            $this->actingAsUser($this->makeUser($slug))
                ->getJson('/api/admin/dashboard')
                ->assertStatus($status);
        }
    }

    public function test_operator_can_access_audit_log(): void
    {
        foreach ($this->makeOperatorAccessible() as [$slug, $status]) {
            $this->actingAsUser($this->makeUser($slug))
                ->getJson('/api/admin/audit/logs')
                ->assertStatus($status);
        }
    }

    public function test_operator_can_manage_users(): void
    {
        foreach ($this->makeOperatorAccessible() as [$slug, $status]) {
            $this->actingAsUser($this->makeUser($slug))
                ->getJson('/api/admin/users')
                ->assertStatus($status);
        }
    }

    public function test_platform_admin_cannot_access_super_admin_only_routes(): void
    {
        $this->actingAsUser($this->makeUser('platform-admin'))
            ->getJson('/api/admin/plans')
            ->assertStatus(403);
    }

    public function test_super_admin_can_access_super_admin_only_routes(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'))
            ->getJson('/api/admin/plans')
            ->assertStatus(200);
    }

    public function test_non_operator_cannot_access_control_centre(): void
    {
        foreach (['facility-admin', 'doctor', 'patient', 'facility-staff'] as $slug) {
            $this->actingAsUser($this->makeUser($slug))
                ->getJson('/api/admin/dashboard')
                ->assertStatus(403);
        }
    }

    public function test_unauthenticated_control_centre_is_forbidden(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }
}