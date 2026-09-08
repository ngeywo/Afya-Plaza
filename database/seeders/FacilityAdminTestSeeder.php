<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilityLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 6 test data: facility admin users, multi-location Busia facility,
 * and one facility staff for the section 25 resource-scope test.
 */
class FacilityAdminTestSeeder extends Seeder
{
    public function run(): void
    {
        $facilityAdminRole = Role::where('slug', 'facility-admin')->first();
        $facilityStaffRole = Role::where('slug', 'facility-staff')->first();

        $busia = Facility::where('slug', 'busia-medical-centre')->first();
        $bungoma = Facility::where('slug', 'bungoma-specialist-centre')->first();
        $busia = Facility::firstOrCreate(['slug' => 'busia-medical-centre'], ['name' => 'Busia Medical Centre']);
        $bungoma = Facility::firstOrCreate(['slug' => 'bungoma-specialist-centre'], ['name' => 'Bungoma Specialist Centre']);

        if (! $busia || ! $bungoma) {
            return; // base seeders must run first
        }

        // Facility Admin for Busia (primary)
        $adminA = User::create([
            'name' => 'Busia Facility Admin',
            'email' => 'busia-admin@afya-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254722000001',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $adminA->roles()->sync([$facilityAdminRole->id]);
        $adminA->facilities()->attach($busia->id, ['is_primary' => true]);

        // Facility Admin for Bungoma — must NOT see Busia
        $adminB = User::create([
            'name' => 'Bungoma Facility Admin',
            'email' => 'bungoma-admin@afya-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254722000002',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $adminB->roles()->sync([$facilityAdminRole->id]);
        $adminB->facilities()->attach($bungoma->id, ['is_primary' => true]);

        // Facility Staff for Busia (resource-scope test)
        $staffA = User::create([
            'name' => 'Busia Reception',
            'email' => 'busia-staff@afya-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254722000003',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $staffA->roles()->sync([$facilityStaffRole->id]);
        $staffA->facilities()->attach($busia->id, ['is_primary' => false]);

        // Multi-location: Busia branches (Section 39)
        FacilityLocation::create([
            'facility_id' => $busia->id,
            'name' => 'Main Branch',
            'address' => 'Busia Town Centre',
            'city' => 'Busia',
            'phone' => '+254711000001',
            'is_primary' => true,
            'is_active' => true,
        ]);
        FacilityLocation::create([
            'facility_id' => $busia->id,
            'name' => 'Malaba Branch',
            'address' => 'Malaba Town',
            'city' => 'Malaba',
            'phone' => '+254711000010',
            'is_primary' => false,
            'is_active' => true,
        ]);
    }
}
