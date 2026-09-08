<?php

namespace Tests\Feature\Phase24;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Platform Control: Users / Roles / Privileges / Permissions CRUD.
 * Every admin route is guarded by ensure.super.admin (non-super-admin => 403).
 */
class PlatformControlFeatureTest extends Phase24TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Permissions

    public function test_super_admin_can_create_list_update_and_delete_permissions(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $created = $this->postJson('/api/admin/permissions', [
            'name' => 'Export Reports',
            'slug' => 'reports.export',
            'group' => 'reports',
            'description' => 'Allows exporting platform reports.',
        ])->assertStatus(201)->json('data');

        $this->assertSame('reports.export', $created['slug']);

        $listed = $this->getJson('/api/admin/permissions')->assertOk()->json('data');
        $this->assertTrue(in_array('reports.export', array_column($listed['all'], 'slug'), true));

        $this->patchJson("/api/admin/permissions/{$created['id']}", ['name' => 'Export Financial Reports'])
            ->assertOk()->assertJsonPath('data.name', 'Export Financial Reports');

        $this->deleteJson("/api/admin/permissions/{$created['id']}")->assertOk();

        $after = $this->getJson('/api/admin/permissions')->json('data');
        $this->assertFalse(in_array('reports.export', array_column($after['all'], 'slug'), true));
    }

    public function test_permission_in_use_cannot_be_deleted(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $role = Role::where('slug', 'doctor')->first();
        $permission = $role->permissions()->first();

        $this->deleteJson("/api/admin/permissions/{$permission->id}")->assertStatus(422);
    }

    public function test_non_super_admin_cannot_manage_permissions(): void
    {
        $this->actingAsUser($this->makeUser('facility-admin'));

        $this->postJson('/api/admin/permissions', ['name' => 'X', 'slug' => 'x.y', 'group' => 'test'])->assertStatus(403);
        $this->getJson('/api/admin/permissions')->assertStatus(403);
    }

    public function test_duplicate_permission_slug_is_rejected(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $this->postJson('/api/admin/permissions', [
            'name' => 'Duplicate',
            'slug' => 'doctors.view',
            'group' => 'test',
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------- Roles

    public function test_super_admin_can_create_update_delete_role_and_assign_permissions(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $role = $this->postJson('/api/admin/roles', [
            'name' => 'Billing Officer',
            'slug' => 'billing-officer',
            'description' => 'Handles facility billing.',
        ])->assertStatus(201)->json('data');

        $perm = Permission::where('slug', 'appointments.view')->first();

        $this->postJson("/api/admin/roles/{$role['id']}/permissions", ['permission_ids' => [$perm->id]])
            ->assertOk()
            ->assertJsonPath('data.permissions.0.slug', 'appointments.view');

        $this->patchJson("/api/admin/roles/{$role['id']}", ['description' => 'Updated'])->assertOk();

        $this->deleteJson("/api/admin/roles/{$role['id']}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role['id']]);
    }

    public function test_protected_roles_cannot_be_modified_or_deleted(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));

        $super = Role::where('slug', 'super-admin')->first();

        $this->patchJson("/api/admin/roles/{$super->id}", ['description' => 'x'])->assertStatus(403);
        $this->deleteJson("/api/admin/roles/{$super->id}")->assertStatus(403);
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $this->actingAsUser($this->makeUser('super-admin'));
        $role = Role::where('slug', 'doctor')->first();
        $this->makeUser('doctor');

        $this->deleteJson("/api/admin/roles/{$role->id}")->assertStatus(422);
    }

    // ---------------------------------------------------------------- Users / Privileges

    public function test_users_list_includes_roles_and_effective_permissions(): void
    {
        $super = $this->makeUser('super-admin', ['name' => 'Prime Admin', 'email' => 'prime@example.com']);

        $target = $this->makeVerifiedUser('doctor');

        $payload = $this->actingAsUser($super)->getJson('/api/admin/users')
            ->assertOk()
            ->json('data');

        $row = collect($payload)->firstWhere('id', $target->id);
        $this->assertNotNull($row);
        $this->assertSame('doctor', $row['roles'][0]['slug'] ?? null);
        $this->assertTrue(collect($row['effective_permissions'])->contains('slug', 'doctors.view'));
    }

    public function test_super_admin_can_assign_and_sync_user_roles(): void
    {
        $super = $this->makeUser('super-admin');

        $target = $this->makeVerifiedUser('patient');
        $doctorRole = Role::where('slug', 'doctor')->first();

        $this->actingAsUser($super)->postJson("/api/admin/users/{$target->id}/roles", [
            'role_ids' => [$doctorRole->id],
        ])->assertOk()->assertJsonPath('data.roles.0.slug', 'doctor');

        $refresh = $target->fresh()->roles()->pluck('slug');
        $this->assertEquals(['doctor'], $refresh->all());
    }

    public function test_super_admin_can_change_user_status(): void
    {
        $super = $this->makeUser('super-admin');
        $target = $this->makeVerifiedUser('patient');

        $this->actingAsUser($super)->postJson("/api/admin/users/{$target->id}/status", [
            'status' => 'suspended',
        ])->assertOk()->assertJsonPath('data.account_state', 'suspended');
    }

    public function test_admin_cannot_modify_own_account(): void
    {
        $super = $this->makeUser('super-admin');

        $this->actingAsUser($super)->patchJson("/api/admin/users/{$super->id}", ['name' => 'Hacked'])->assertStatus(403);
        $this->actingAsUser($super)->deleteJson("/api/admin/users/{$super->id}")->assertStatus(403);
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        $this->actingAsUser($this->makeUser('facility-admin'));

        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/roles')->assertStatus(403);
    }

    // ---------------------------------------------------------------- Facility staff CRUD

    public function test_facility_admin_can_add_update_and_remove_staff(): void
    {
        $owner = $this->makeUser('facility-admin');
        $facility = $this->makeFacility($owner);
        $staffMember = $this->makeVerifiedUser('facility-staff');

        $this->actingAsUser($owner);

        // Add (attaches to facility + assigns role)
        $added = $this->postJson('/api/facility/staff', [
            'email' => $staffMember->email,
            'role' => 'facility-staff',
        ])->assertStatus(201)->json('data');
        $this->assertSame($staffMember->email, $added['email']);
        $this->assertTrue($facility->admins()->where('users.id', $staffMember->id)->exists());

        // Update (role change)
        $this->patchJson('/api/facility/staff/'.$staffMember->id, ['role' => 'facility-admin'])
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'Facility-admin');
        $this->assertTrue($staffMember->fresh()->hasRole('facility-admin'));

        // Remove
        $this->deleteJson('/api/facility/staff/'.$staffMember->id)->assertOk();
        $this->assertFalse($facility->fresh()->admins()->where('users.id', $staffMember->id)->exists());
    }

    public function test_primary_facility_admin_cannot_be_modified_or_removed(): void
    {
        $owner = $this->makeUser('facility-admin');
        $this->makeFacility($owner);

        $this->actingAsUser($owner);

        $this->patchJson('/api/facility/staff/'.$owner->id, ['role' => 'facility-staff'])->assertStatus(422);
        $this->deleteJson('/api/facility/staff/'.$owner->id)->assertStatus(422);
    }

    public function test_facility_admin_cannot_assign_platform_admin_role(): void
    {
        $owner = $this->makeUser('facility-admin');
        $facility = $this->makeFacility($owner);
        $staffMember = $this->makeVerifiedUser('facility-staff');

        $this->actingAsUser($owner);

        $this->postJson('/api/facility/staff', [
            'email' => $staffMember->email,
            'role' => 'admin',
        ])->assertStatus(422);
        $this->postJson('/api/facility/staff', [
            'email' => $staffMember->email,
            'role' => 'platform-admin',
        ])->assertStatus(422);
        $this->assertFalse($facility->admins()->where('users.id', $staffMember->id)->exists());

        // A platform-admin member attached as staff can be managed, but the
        // facility-admin surface never grants nor strips the global role.
        $platformAdmin = Role::where('slug', 'platform-admin')->firstOrFail();
        $facility->admins()->attach($staffMember->id, ['is_primary' => false]);
        $staffMember->roles()->attach($platformAdmin->id);

        $this->patchJson('/api/facility/staff/'.$staffMember->id, ['role' => 'facility-admin'])->assertOk();
        $this->assertTrue($staffMember->fresh()->hasRole('facility-admin'));
        $this->assertTrue($staffMember->fresh()->hasRole('platform-admin'));

        $this->patchJson('/api/facility/staff/'.$staffMember->id, ['role' => 'platform-admin'])->assertStatus(422);
        $this->assertTrue($staffMember->fresh()->hasRole('platform-admin'));
    }
}
