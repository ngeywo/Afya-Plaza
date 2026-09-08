<?php

namespace Tests\Feature\Phase23;

use App\Models\County;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 23 base: seeded roles + permissions + helpers.
 */
abstract class Phase23TestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'platform-admin', 'facility-admin', 'doctor', 'facility-staff', 'patient'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        }

        $permissions = [
            'doctors.view', 'doctors.manage', 'doctors.verify',
            'facilities.view', 'facilities.manage', 'facilities.verify',
            'clinic_sessions.view', 'clinic_sessions.manage', 'clinic_sessions.confirm',
            'appointments.view', 'appointments.book', 'appointments.manage',
            'users.manage', 'roles.manage', 'audit.view',
        ];

        foreach ($permissions as $slug) {
            Permission::create(['name' => ucfirst(str_replace('.', ' ', $slug)), 'slug' => $slug, 'group' => 'test']);
        }

        Role::where('slug', 'super-admin')->first()->permissions()->sync(Permission::pluck('id'));

        Role::where('slug', 'platform-admin')->first()->permissions()->sync(Permission::whereIn('slug', [
            'doctors.view', 'doctors.verify', 'facilities.view', 'facilities.verify',
            'appointments.view', 'users.manage', 'audit.view',
        ])->pluck('id'));

        Role::where('slug', 'facility-admin')->first()->permissions()->sync(Permission::whereIn('slug', [
            'facilities.view', 'facilities.manage', 'doctors.view',
            'clinic_sessions.view', 'clinic_sessions.manage', 'clinic_sessions.confirm',
            'appointments.view', 'appointments.manage',
        ])->pluck('id'));

        Role::where('slug', 'doctor')->first()->permissions()->sync(Permission::whereIn('slug', [
            'doctors.view', 'clinic_sessions.view', 'clinic_sessions.manage',
            'appointments.view', 'appointments.manage',
        ])->pluck('id'));

        Role::where('slug', 'patient')->first()->permissions()->sync(Permission::whereIn('slug', [
            'doctors.view', 'facilities.view', 'clinic_sessions.view',
            'appointments.view', 'appointments.book',
        ])->pluck('id'));
    }

    protected function makeUser(string $roleSlug, array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        $user->roles()->attach(Role::where('slug', $roleSlug)->first());

        return $user;
    }

    protected function actingAsUser(User $user)
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function makeDoctor(User $user, array $overrides = []): Doctor
    {
        $doctor = Doctor::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Dr. '.$user->name,
            'slug' => 'dr-'.uniqid(),
            'registry_number' => 'REG-'.uniqid(),
            'qualifications' => 'MD',
            'consultation_fee' => 1000,
            'is_verified' => false,
            'is_active' => true,
            'verification_status' => 'pending',
        ], $overrides));

        return $doctor;
    }

    protected function makeVerifiedUser(string $roleSlug): User
    {
        return $this->makeUser($roleSlug, [
            'email_verified_at' => now(),
            'phone' => '0722'.random_int(100000, 999999),
            'phone_verified_at' => now(),
        ]);
    }

    protected function makeFacility(User $user, array $overrides = []): Facility
    {
        $facility = Facility::create(array_merge([
            'name' => 'Facility '.uniqid(),
            'slug' => 'facility-'.uniqid(),
            'address' => '123 Test Street',
            'city' => 'Nairobi',
            'type' => 'clinic',
            'phone' => '0722'.random_int(100000, 999999),
            'email' => 'f'.uniqid().'@example.com',
            'registry_number' => 'FR-'.uniqid(),
            'is_verified' => false,
            'is_active' => true,
            'verification_status' => 'pending',
        ], $overrides));

        $facility->admins()->attach($user->id, ['is_primary' => true]);

        return $facility;
    }

    protected function seedSpecialty(): int
    {
        $specialty = Specialty::firstOrCreate(
            ['slug' => 'cardiology'],
            ['name' => 'Cardiology']
        );

        return $specialty->id;
    }

    protected function makeCompleteDoctor(User $user, array $overrides = []): Doctor
    {
        $doctor = $this->makeDoctor($user, array_merge([
            'registry_number' => 'KMPDC-'.uniqid(),
            'biography' => 'Experienced cardiologist.',
            'languages' => ['English', 'Swahili'],
            'areas_of_practice' => ['Cardiology'],
        ], $overrides));
        $doctor->specialties()->attach($this->seedSpecialty(), ['is_primary' => true]);

        return $doctor;
    }

    protected function makeCompleteFacility(User $user, array $overrides = []): Facility
    {
        $facility = $this->makeFacility($user, array_merge([
            'county_id' => County::firstOrCreate(
                ['slug' => 'nairobi'],
                ['name' => 'Nairobi', 'is_active' => true]
            )->id,
            'registry_number' => 'FRS-'.uniqid(),
            'description' => 'A verified clinic.',
            'latitude' => '-1.2921',
            'longitude' => '36.8219',
        ], $overrides));

        return $facility;
    }
}
