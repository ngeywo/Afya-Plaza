<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('slug', 'super-admin')->first();
        if (! $role) {
            $this->command->warn('super-admin role not found — ensure RolesAndPermissionsSeeder runs first.');

            return;
        }

        User::updateOrCreate(
            ['email' => 'superadmin@afya-plaza.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('superadmin123'),
                'phone' => '+254700000000',
                'is_active' => true,
                'is_verified' => true,
            ]
        )->roles()->sync([$role->id]);

        $this->command->info('Super Admin seeded: superadmin@afya-plaza.test / superadmin123');
    }
}
