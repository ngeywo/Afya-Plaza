<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtiesAndCountiesSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['name' => 'Cardiology', 'slug' => 'cardiology', 'icon' => 'mdi-heart-pulse'],
            ['name' => 'Orthopaedics', 'slug' => 'orthopaedics', 'icon' => 'mdi-bone'],
            ['name' => 'Dermatology', 'slug' => 'dermatology', 'icon' => 'mdi-face-woman-shimmer'],
            ['name' => 'Pediatrics', 'slug' => 'pediatrics', 'icon' => 'mdi-baby-face-outline'],
            ['name' => 'Neurology', 'slug' => 'neurology', 'icon' => 'mdi-brain'],
            ['name' => 'Gynecology', 'slug' => 'gynecology', 'icon' => 'mdi-medical-bag'],
            ['name' => 'General Practice', 'slug' => 'general-practice', 'icon' => 'mdi-stethoscope'],
            ['name' => 'Ophthalmology', 'slug' => 'ophthalmology', 'icon' => 'mdi-eye-outline'],
            ['name' => 'ENT', 'slug' => 'ent', 'icon' => 'mdi-ear-hearing'],
            ['name' => 'Dentistry', 'slug' => 'dentistry', 'icon' => 'mdi-tooth-outline'],
        ];

        foreach ($specialties as $s) {
            Specialty::create($s);
        }

        $counties = [
            ['name' => 'Nairobi', 'slug' => 'nairobi', 'code' => 'NRB'],
            ['name' => 'Mombasa', 'slug' => 'mombasa', 'code' => 'MSA'],
            ['name' => 'Kisumu', 'slug' => 'kisumu', 'code' => 'KSM'],
            ['name' => 'Busia', 'slug' => 'busia', 'code' => 'BUS'],
            ['name' => 'Bungoma', 'slug' => 'bungoma', 'code' => 'BGM'],
            ['name' => 'Kakamega', 'slug' => 'kakamega', 'code' => 'KKG'],
            ['name' => 'Uasin Gishu', 'slug' => 'uasin-gishu', 'code' => 'UGS'],
            ['name' => 'Nakuru', 'slug' => 'nakuru', 'code' => 'NKR'],
        ];

        foreach ($counties as $c) {
            County::create($c);
        }
    }
}
