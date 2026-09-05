<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('slug', 'super-admin')->first();
        $patientRole = Role::where('slug', 'patient')->first();

        $admin = User::create([
            'name' => 'Docta Plaza Admin',
            'email' => 'admin@docta-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254700000001',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $admin->roles()->sync([$superAdmin->id]);

        $patient = User::create([
            'name' => 'Test Patient',
            'email' => 'patient@docta-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254700000002',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $patient->roles()->sync([$patientRole->id]);
    }
}
