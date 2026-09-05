<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SpecialtiesAndCountiesSeeder::class,
            UsersSeeder::class,
            FacilitiesSeeder::class,
            DoctorsSeeder::class,
            ClinicSessionsSeeder::class,
        ]);
    }
}
