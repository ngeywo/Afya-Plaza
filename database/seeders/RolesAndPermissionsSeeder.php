<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Platform owner with full control'],
            ['name' => 'Platform Admin', 'slug' => 'platform-admin', 'description' => 'Operational administrator'],
            ['name' => 'Facility Admin', 'slug' => 'facility-admin', 'description' => 'Manages a facility'],
            ['name' => 'Doctor', 'slug' => 'doctor', 'description' => 'Practicing doctor'],
            ['name' => 'Facility Staff', 'slug' => 'facility-staff', 'description' => 'Staff at a facility'],
            ['name' => 'Patient', 'slug' => 'patient', 'description' => 'Booker of appointments'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }

        $permissions = [
            ['name' => 'View Doctors', 'slug' => 'doctors.view', 'group' => 'doctors'],
            ['name' => 'Manage Doctors', 'slug' => 'doctors.manage', 'group' => 'doctors'],
            ['name' => 'Verify Doctors', 'slug' => 'doctors.verify', 'group' => 'doctors'],
            ['name' => 'View Facilities', 'slug' => 'facilities.view', 'group' => 'facilities'],
            ['name' => 'Manage Facilities', 'slug' => 'facilities.manage', 'group' => 'facilities'],
            ['name' => 'Verify Facilities', 'slug' => 'facilities.verify', 'group' => 'facilities'],
            ['name' => 'View Sessions', 'slug' => 'clinic_sessions.view', 'group' => 'clinic_sessions'],
            ['name' => 'Manage Sessions', 'slug' => 'clinic_sessions.manage', 'group' => 'clinic_sessions'],
            ['name' => 'Confirm Sessions', 'slug' => 'clinic_sessions.confirm', 'group' => 'clinic_sessions'],
            ['name' => 'View Appointments', 'slug' => 'appointments.view', 'group' => 'appointments'],
            ['name' => 'Book Appointments', 'slug' => 'appointments.book', 'group' => 'appointments'],
            ['name' => 'Manage Appointments', 'slug' => 'appointments.manage', 'group' => 'appointments'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'admin'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'group' => 'admin'],
            ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'group' => 'admin'],
        ];

        foreach ($permissions as $p) {
            Permission::create($p);
        }

        $superAdmin = Role::where('slug', 'super-admin')->first();
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        $facilityAdmin = Role::where('slug', 'facility-admin')->first();
        $facilityAdmin->permissions()->sync(Permission::whereIn('slug', [
            'facilities.view', 'facilities.manage', 'doctors.view',
            'clinic_sessions.view', 'clinic_sessions.manage', 'clinic_sessions.confirm',
            'appointments.view', 'appointments.manage',
        ])->pluck('id'));

        $doctorRole = Role::where('slug', 'doctor')->first();
        $doctorRole->permissions()->sync(Permission::whereIn('slug', [
            'doctors.view', 'clinic_sessions.view', 'clinic_sessions.manage',
            'appointments.view', 'appointments.manage',
        ])->pluck('id'));

        $patient = Role::where('slug', 'patient')->first();
        $patient->permissions()->sync(Permission::whereIn('slug', [
            'doctors.view', 'facilities.view', 'clinic_sessions.view',
            'appointments.view', 'appointments.book',
        ])->pluck('id'));
    }
}
