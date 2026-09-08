<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\Facility;
use App\Models\FacilityLocation;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Reseed-safe demo reset for manually exercising the clinic session flow:
 *
 *   1. A facility-admin who manages TWO facilities (Riverline Medical Centre +
 *      Afya Point Clinic) — demos the multi-facility picker on the Clinic
 *      Sessions "New Clinic Session" dialog.
 *   2. A doctor with an active relationship + recurring schedule at both
 *      facilities, ready to confirm sessions the facility schedules.
 *   3. A patient who can book once a session becomes confirmed.
 *
 * Hospitals create clinic sessions; the doctor confirms them. This seeder only
 * provides the actors — it does NOT create clinic sessions itself.
 */
class SessionFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $facilityAdminRole = Role::where('slug', 'facility-admin')->first();
        $doctorRole = Role::where('slug', 'doctor')->first();
        $patientRole = Role::where('slug', 'patient')->first();
        $generalPractice = Specialty::where('slug', 'general-practice')->first();
        $kisumu = County::where('slug', 'kisumu')->first();
        $nairobi = County::where('slug', 'nairobi')->first();

        // ─── Facilities ────────────────────────────────────────────────────────
        $riverline = Facility::updateOrCreate(['slug' => 'riverline-medical-centre'], [
            'name' => 'Riverline Medical Centre',
            'description' => 'Demo hospital for exercising the clinic session flow.',
            'address' => 'Kisumu Waterfront, Kisumu',
            'city' => 'Kisumu',
            'county_id' => $kisumu?->id,
            'phone' => '+254711000020',
            'email' => 'info@riverlinemedical.test',
            'is_verified' => true,
            'is_active' => true,
            'type' => 'hospital',
        ]);

        $afyaPoint = Facility::updateOrCreate(['slug' => 'afya-point-clinic'], [
            'name' => 'Afya Point Clinic',
            'description' => 'Demo clinic for exercising the clinic session flow.',
            'address' => 'Westlands, Nairobi',
            'city' => 'Nairobi',
            'county_id' => $nairobi?->id,
            'phone' => '+254711000021',
            'email' => 'info@afyapoint.test',
            'is_verified' => true,
            'is_active' => true,
            'type' => 'clinic',
        ]);

        FacilityLocation::firstOrCreate(['facility_id' => $riverline->id, 'name' => 'Main Branch'], [
            'address' => 'Kisumu Waterfront',
            'city' => 'Kisumu',
            'phone' => '+254711000020',
            'is_primary' => true,
            'is_active' => true,
        ]);
        FacilityLocation::firstOrCreate(['facility_id' => $afyaPoint->id, 'name' => 'Westlands Branch'], [
            'address' => 'Westlands, Nairobi',
            'city' => 'Nairobi',
            'phone' => '+254711000021',
            'is_primary' => true,
            'is_active' => true,
        ]);

        // ─── Facility admin (manages BOTH facilities) ──────────────────────────
        $admin = User::updateOrCreate(['email' => 'flow-admin@afya-plaza.test'], [
            'name' => 'Flow Facility Admin',
            'password' => Hash::make('password'),
            'phone' => '+254722000020',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $admin->roles()->sync([$facilityAdminRole->id]);
        $admin->facilities()->syncWithoutDetaching([
            $riverline->id => ['is_primary' => true],
            $afyaPoint->id => ['is_primary' => false],
        ]);

        // ─── Doctor with active relationships + recurring schedules ────────────
        $doctorUser = User::updateOrCreate(['email' => 'amara@afya-plaza.test'], [
            'name' => 'Dr. Amara Njeri',
            'password' => Hash::make('password'),
            'phone' => '+254722000021',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $doctorUser->roles()->sync([$doctorRole->id]);

        $doctor = Doctor::updateOrCreate(['slug' => 'dr-amara-njeri'], [
            'user_id' => $doctorUser->id,
            'display_name' => 'Dr. Amara',
            'biography' => 'General practitioner available across the demo facilities.',
            'qualifications' => 'MBChB (UoN)',
            'license_number' => 'KMPDB-55443',
            'consultation_fee' => 3000,
            'gender' => 'female',
            'years_of_experience' => 8,
            'is_verified' => true,
            'is_active' => true,
            'verified_at' => Carbon::now(),
        ]);
        if ($generalPractice) {
            $doctor->specialties()->syncWithoutDetaching([$generalPractice->id => ['is_primary' => true]]);
        }

        $dfRiverline = DoctorFacility::updateOrCreate(
            ['doctor_id' => $doctor->id, 'facility_id' => $riverline->id],
            ['consultation_fee' => 3500, 'accepts_appointments' => true, 'is_active' => true, 'started_at' => Carbon::now()->subYear()]
        );
        $dfAfyaPoint = DoctorFacility::updateOrCreate(
            ['doctor_id' => $doctor->id, 'facility_id' => $afyaPoint->id],
            ['consultation_fee' => 2800, 'accepts_appointments' => true, 'is_active' => true, 'started_at' => Carbon::now()->subMonths(6)]
        );

        DoctorFacilitySchedule::updateOrCreate(
            ['doctor_facility_id' => $dfRiverline->id, 'day_of_week' => 3],
            ['start_time' => '09:00', 'end_time' => '13:00', 'slot_duration_minutes' => 30, 'max_appointments' => 10, 'is_active' => true]
        );
        DoctorFacilitySchedule::updateOrCreate(
            ['doctor_facility_id' => $dfAfyaPoint->id, 'day_of_week' => 5],
            ['start_time' => '14:00', 'end_time' => '17:00', 'slot_duration_minutes' => 30, 'max_appointments' => 6, 'is_active' => true]
        );

        // ─── Patient for booking confirmed sessions ────────────────────────────
        $patient = User::updateOrCreate(['email' => 'flow-patient@afya-plaza.test'], [
            'name' => 'Flow Patient',
            'password' => Hash::make('password'),
            'phone' => '+254722000022',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $patient->roles()->sync([$patientRole->id]);
    }
}
