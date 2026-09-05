<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Role;
use App\Models\User;

/**
 * Phase 17: Appointment booking safety + cancellation authorization.
 *
 * Sections 9, 10, 11, 12, 45, 46.
 */
abstract class AppointmentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $patient;
    protected User $otherPatient;
    protected User $doctorUser;
    protected Doctor $doctor;
    protected Facility $facilityA;
    protected Facility $facilityB;
    protected ClinicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['patient', 'doctor', 'facility-admin', 'facility-staff', 'super-admin'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        }

        $this->patient = $this->makeUser('patient');
        $this->otherPatient = $this->makeUser('patient');

        $this->doctorUser = $this->makeUser('doctor');
        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'display_name' => 'Dr. Test',
            'slug' => 'dr-test',
            'is_verified' => true,
            'is_active' => true,
            'consultation_fee' => 1000,
        ]);

        $this->facilityA = Facility::create(['name' => 'Facility A', 'slug' => 'facility-a', 'is_active' => true, 'is_verified' => true]);
        $this->facilityB = Facility::create(['name' => 'Facility B', 'slug' => 'facility-b', 'is_active' => true, 'is_verified' => true]);

        $this->session = $this->makeSession($this->doctor, $this->facilityA);
    }

    protected function makeUser(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->first());
        return $user;
    }

    protected function makeSession(Doctor $doctor, Facility $facility, array $overrides = []): ClinicSession
    {
        return ClinicSession::create(array_merge([
            'doctor_id' => $doctor->id,
            'facility_id' => $facility->id,
            'session_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'slot_duration_minutes' => 30,
            'max_appointments' => 6,
            'booked_appointments' => 0,
            'consultation_fee' => 1000,
            'status' => 'confirmed',
            'doctor_confirmation' => 'confirmed',
            'facility_confirmation' => 'confirmed',
        ], $overrides));
    }

    protected function book(User $patient, string $slot = '09:00', array $extra = [])
    {
        Sanctum::actingAs($patient);
        return $this->postJson('/api/appointments', array_merge([
            'clinic_session_id' => $this->session->id,
            'start_time' => $slot,
        ], $extra));
    }

    protected function actingFacilityStaff(User $user, Facility $facility): User
    {
        $user->roles()->attach(Role::where('slug', 'facility-staff')->first());
        $user->facilities()->attach($facility->id);
        return $user;
    }
}