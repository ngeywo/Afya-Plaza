<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\Facility;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DoctorsSeeder extends Seeder
{
    public function run(): void
    {
        $doctorRole = Role::where('slug', 'doctor')->first();
        $busiaMC = Facility::where('slug', 'busia-medical-centre')->first();
        $bungomaSC = Facility::where('slug', 'bungoma-specialist-centre')->first();
        $kakamegaGH = Facility::where('slug', 'kakamega-general-hospital')->first();
        $ortho = Specialty::where('slug', 'orthopaedics')->first();

        // Dr. Wanyonyi — the canonical example from the product vision
        $wUser = User::create([
            'name' => 'Dr. Wanyonyi Wekesa',
            'email' => 'wanyonyi@docta-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254722000001',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $wUser->roles()->sync([$doctorRole->id]);

        $wanyonyi = Doctor::create([
            'user_id' => $wUser->id,
            'display_name' => 'Dr. Wanyonyi',
            'slug' => 'dr-wanyonyi',
            'biography' => 'Experienced Orthopaedic Surgeon with 15 years of practice in Western Kenya.',
            'qualifications' => 'MBChB (UoN), MMed Surgery (UoN), FCS (Ortho)',
            'license_number' => 'KMPDB-12345',
            'consultation_fee' => 3000,
            'gender' => 'male',
            'years_of_experience' => 15,
            'is_verified' => true,
            'is_active' => true,
            'is_featured' => true,
            'verified_at' => Carbon::now(),
        ]);
        $wanyonyi->specialties()->attach($ortho->id, ['is_primary' => true]);

        // Dr. Wanyonyi practices at 3 facilities — Monday/Busia, Tuesday/Bungoma,
        // Wednesday/Busia, Thursday/Kakamega, Friday/Bungoma
        $dfBusia = DoctorFacility::create([
            'doctor_id' => $wanyonyi->id, 'facility_id' => $busiaMC->id,
            'consultation_fee' => 3000, 'accepts_appointments' => true, 'is_active' => true,
            'started_at' => Carbon::now()->subYears(2),
        ]);
        $dfBungoma = DoctorFacility::create([
            'doctor_id' => $wanyonyi->id, 'facility_id' => $bungomaSC->id,
            'consultation_fee' => 3500, 'accepts_appointments' => true, 'is_active' => true,
            'started_at' => Carbon::now()->subYears(2),
        ]);
        $dfKakamega = DoctorFacility::create([
            'doctor_id' => $wanyonyi->id, 'facility_id' => $kakamegaGH->id,
            'consultation_fee' => 2500, 'accepts_appointments' => true, 'is_active' => true,
            'started_at' => Carbon::now()->subYears(2),
        ]);

        // Schedules
        foreach ([[1, '10:00', '16:00'], [3, '10:00', '16:00']] as $s) {
            DoctorFacilitySchedule::create([
                'doctor_facility_id' => $dfBusia->id, 'day_of_week' => $s[0],
                'start_time' => $s[1], 'end_time' => $s[2],
                'slot_duration_minutes' => 30, 'max_appointments' => 12, 'is_active' => true,
            ]);
        }
        foreach ([[2, '09:00', '15:00'], [5, '09:00', '15:00']] as $s) {
            DoctorFacilitySchedule::create([
                'doctor_facility_id' => $dfBungoma->id, 'day_of_week' => $s[0],
                'start_time' => $s[1], 'end_time' => $s[2],
                'slot_duration_minutes' => 30, 'max_appointments' => 12, 'is_active' => true,
            ]);
        }
        DoctorFacilitySchedule::create([
            'doctor_facility_id' => $dfKakamega->id, 'day_of_week' => 4,
            'start_time' => '10:00', 'end_time' => '16:00',
            'slot_duration_minutes' => 30, 'max_appointments' => 12, 'is_active' => true,
        ]);

        // Dr. Achieng — Dermatology
        $aUser = User::create([
            'name' => 'Dr. Achieng Otieno',
            'email' => 'achieng@docta-plaza.test',
            'password' => Hash::make('password'),
            'phone' => '+254722000002',
            'is_active' => true, 'is_verified' => true,
        ]);
        $aUser->roles()->sync([$doctorRole->id]);

        $achieng = Doctor::create([
            'user_id' => $aUser->id, 'display_name' => 'Dr. Achieng',
            'slug' => 'dr-achieng',
            'biography' => 'Renowned Dermatologist with expertise in skin conditions.',
            'qualifications' => 'MBChB (UoN), MMed (Dermatology)',
            'license_number' => 'KMPDB-12346', 'consultation_fee' => 2500,
            'gender' => 'female', 'years_of_experience' => 10,
            'is_verified' => true, 'is_active' => true,
            'verified_at' => Carbon::now(),
        ]);
        $derm = Specialty::where('slug', 'dermatology')->first();
        $achieng->specialties()->attach($derm->id, ['is_primary' => true]);

        $dfAchieng = DoctorFacility::create([
            'doctor_id' => $achieng->id, 'facility_id' => $busiaMC->id,
            'consultation_fee' => 2500, 'accepts_appointments' => true, 'is_active' => true,
        ]);
        DoctorFacilitySchedule::create([
            'doctor_facility_id' => $dfAchieng->id, 'day_of_week' => 3,
            'start_time' => '14:00', 'end_time' => '18:00',
            'slot_duration_minutes' => 30, 'max_appointments' => 8, 'is_active' => true,
        ]);
    }
}
