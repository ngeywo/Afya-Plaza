<?php

namespace Database\Seeders;

use App\Enums\VerificationStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Phase13DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create a sample pending doctor
        $user = User::create([
            'name' => 'Dr. Test Pending',
            'email' => 'dr.test.pending@afya-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254700000099',
            'is_active' => true,
        ]);
        $user->roles()->sync([Role::where('slug', 'doctor')->first()->id]);

        Doctor::create([
            'user_id' => $user->id,
            'display_name' => 'Dr. Test Pending',
            'slug' => 'dr-test-pending',
            'biography' => 'Sample doctor pending verification.',
            'qualifications' => 'MBChB, MMed (Internal Medicine)',
            'license_number' => 'KMPDB-2024-99999',
            'consultation_fee' => 2500,
            'years_of_experience' => 8,
            'is_verified' => false,
            'verification_status' => VerificationStatus::PENDING->value,
            'is_active' => true,
            'gender' => 'male',
        ]);

        // Create a sample pending facility
        Facility::create([
            'name' => 'Busia Community Clinic',
            'slug' => 'busia-community-clinic',
            'description' => 'New community clinic pending verification.',
            'address' => 'Hospital Road, Busia',
            'city' => 'Busia',
            'phone' => '+254700000098',
            'email' => 'info@busia-clinic.test',
            'type' => 'clinic',
            'is_verified' => false,
            'verification_status' => VerificationStatus::PENDING->value,
            'is_active' => true,
        ]);

        $this->command->info('Phase 13 demo data seeded: 1 pending doctor, 1 pending facility.');
    }
}
