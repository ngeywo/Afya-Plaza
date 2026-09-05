<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $busia = County::where('slug', 'busia')->first();
        $bungoma = County::where('slug', 'bungoma')->first();
        $kakamega = County::where('slug', 'kakamega')->first();

        Facility::create([
            'name' => 'Busia Medical Centre',
            'slug' => 'busia-medical-centre',
            'description' => 'A leading healthcare facility in Busia County',
            'address' => 'Busia Town, Busia',
            'city' => 'Busia',
            'county_id' => $busia->id,
            'phone' => '+254711000001',
            'email' => 'info@busiamedical.test',
            'is_verified' => true,
            'is_active' => true,
            'type' => 'hospital',
        ]);

        Facility::create([
            'name' => 'Bungoma Specialist Centre',
            'slug' => 'bungoma-specialist-centre',
            'description' => 'Specialist healthcare services in Bungoma',
            'address' => 'Bungoma Town, Bungoma',
            'city' => 'Bungoma',
            'county_id' => $bungoma->id,
            'phone' => '+254711000002',
            'email' => 'info@bungomaspecialist.test',
            'is_verified' => true,
            'is_active' => true,
            'type' => 'clinic',
        ]);

        Facility::create([
            'name' => 'Kakamega General Hospital',
            'slug' => 'kakamega-general-hospital',
            'description' => 'Premier healthcare institution in Kakamega',
            'address' => 'Kakamega Town, Kakamega',
            'city' => 'Kakamega',
            'county_id' => $kakamega->id,
            'phone' => '+254711000003',
            'email' => 'info@kkgh.test',
            'is_verified' => true,
            'is_active' => true,
            'type' => 'hospital',
        ]);
    }
}
