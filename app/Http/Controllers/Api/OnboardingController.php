<?php

namespace App\Http\Controllers\Api;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Role;
use App\Services\ProfileCompletenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OnboardingController — registration completion flows.
 *
 * Doctors and facility admins first sign up (account + role) and then complete
 * their public profile here before moderation. Profiles start as
 * verification_status=pending so nothing is patient-facing until verified.
 */
class OnboardingController extends Controller
{
    // ─── Doctor ─────────────────────────────────────────────────────────────────

    public function doctorStatus(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        return response()->json(['data' => [
            'has_profile' => (bool) $doctor,
            'doctor' => $doctor ? [
                'id' => $doctor->id,
                'display_name' => $doctor->display_name,
                'verification_status' => $doctor->verification_status?->value,
                'is_verified' => (bool) $doctor->is_verified,
                'rejection_reason' => $doctor->rejection_reason,
                'registry_number' => $doctor->registry_number,
                'completeness' => ProfileCompletenessService::doctorCompleteness($doctor),
            ] : null,
        ]]);
    }

    public function createDoctorProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->doctor) {
            return response()->json(['error' => 'Doctor profile already exists.'], 409);
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'specialty_id' => 'required|integer|exists:specialties,id',
            'consultation_fee' => 'required|numeric|min:0',
            'qualifications' => 'nullable|string|max:1000',
            'biography' => 'nullable|string|max:5000',
            'license_number' => 'nullable|string|max:255',
            'registry_number' => 'nullable|string|max:191',
            'gender' => 'nullable|string|in:male,female,other',
            'years_of_experience' => 'nullable|integer|min:0|max:80',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:80',
            'areas_of_practice' => 'nullable|array',
            'areas_of_practice.*' => 'string|max:120',
        ]);

        // Phase 23: duplicate-identity detection on professional registry number.
        if (filled($validated['registry_number'] ?? null)) {
            $dupe = Doctor::where('registry_number', $validated['registry_number'])->first();
            if ($dupe) {
                return response()->json(['error' => 'This professional registration number is already associated with an account.'], 409);
            }
        }

        $doctor = DB::transaction(function () use ($user, $validated) {
            $doctor = Doctor::create([
                'user_id' => $user->id,
                'slug' => Str::slug(strtolower($validated['display_name'])).'-'.Str::lower(Str::random(5)),
                'display_name' => $validated['display_name'],
                'qualifications' => $validated['qualifications'] ?? null,
                'biography' => $validated['biography'] ?? null,
                'license_number' => $validated['license_number'] ?? null,
                'registry_number' => $validated['registry_number'] ?? null,
                'consultation_fee' => $validated['consultation_fee'],
                'gender' => $validated['gender'] ?? null,
                'years_of_experience' => $validated['years_of_experience'] ?? 0,
                'languages' => $validated['languages'] ?? null,
                'areas_of_practice' => $validated['areas_of_practice'] ?? null,
                'is_active' => true,
                'verification_status' => VerificationStatus::PENDING,
            ]);

            $doctor->specialties()->attach($validated['specialty_id'], ['is_primary' => true]);

            return $doctor;
        });

        return response()->json([
            'data' => [
                'id' => $doctor->id,
                'display_name' => $doctor->display_name,
                'verification_status' => $doctor->verification_status?->value,
            ],
            'message' => 'Doctor profile created. Awaiting verification.',
        ], 201);
    }

    // ─── Facility ───────────────────────────────────────────────────────────────

    public function facilityStatus(Request $request): JsonResponse
    {
        $facilities = $request->user()->facilities;

        return response()->json(['data' => [
            'has_facility' => $facilities->isNotEmpty(),
            'facility' => $facilities->first() ? [
                'id' => $facilities->first()->id,
                'name' => $facilities->first()->name,
                'verification_status' => $facilities->first()->verification_status?->value,
                'is_verified' => (bool) $facilities->first()->is_verified,
                'rejection_reason' => $facilities->first()->rejection_reason,
                'registry_number' => $facilities->first()->registry_number,
                'completeness' => ProfileCompletenessService::facilityCompleteness($facilities->first()),
            ] : null,
        ]]);
    }

    public function createFacility(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->facilities()->exists()) {
            return response()->json(['error' => 'You already manage a facility.'], 409);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'county_id' => 'required|integer|exists:counties,id',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'type' => 'nullable|string|in:hospital,clinic,diagnostic,pharmacy,other',
            'registry_number' => 'nullable|string|max:191',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Phase 23: duplicate-identity detection on facility registration.
        if (filled($validated['registry_number'] ?? null)) {
            $dupe = Facility::where('registry_number', $validated['registry_number'])->first();
            if ($dupe) {
                return response()->json(['error' => 'This facility registration number is already associated with an account.'], 409);
            }
        }

        $facility = DB::transaction(function () use ($user, $validated) {
            $facility = Facility::create([
                'name' => $validated['name'],
                'slug' => Str::slug(strtolower($validated['name'])).'-'.Str::lower(Str::random(5)),
                'description' => $validated['description'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'county_id' => $validated['county_id'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'type' => $validated['type'] ?? 'clinic',
                'registry_number' => $validated['registry_number'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'is_active' => true,
                'verification_status' => VerificationStatus::PENDING,
            ]);

            // Elevate to facility-admin and mark as the primary facility.
            $role = Role::where('slug', 'facility-admin')->first();
            if ($role && ! $user->hasRole('facility-admin')) {
                $user->roles()->attach($role->id);
            }

            $user->facilities()->attach($facility->id, ['is_primary' => true]);

            return $facility;
        });

        return response()->json([
            'data' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'verification_status' => $facility->verification_status?->value,
            ],
            'message' => 'Facility created. Awaiting verification.',
        ], 201);
    }
}
