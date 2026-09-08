<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Follow;
use App\Services\DiscoveryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 8: Patient Discovery Engine.
 * Patient-facing doctor search and profile.
 * Session-driven: only doctors with real confirmed sessions appear.
 */
class PatientDiscoveryController extends Controller
{
    /**
     * GET /api/doctors/search  |  GET /api/discovery/doctors
     * Phase 15: Delegated to DiscoveryService (N+1-free, suggestions on empty results).
     */
    public function search(Request $request): JsonResponse
    {
        $params = $request->only([
            'date', 'q', 'specialty_id', 'county_id',
            'city', 'facility_id', 'verified_only', 'per_page', 'page',
        ]);
        $result = (new DiscoveryService)->search($params);

        return response()->json($result);
    }

    /**
     * GET /api/doctors/{slug}/profile
     * Phase 8: Doctor profile with session context.
     */
    public function profile(Request $request, string $slug): JsonResponse
    {
        $doctor = Doctor::where('slug', $slug)
            ->with(['specialties', 'facilities.county'])
            ->firstOrFail();

        $isFollowing = false;
        if ($request->user()) {
            $isFollowing = Follow::where('user_id', $request->user()->id)
                ->where('doctor_id', $doctor->id)
                ->exists();
        }
        $today = today();
        $end = $today->copy()->addDays(14);

        $sessions = ClinicSession::where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$today->toDateString(), $end->toDateString()])
            ->where('status', 'confirmed')
            ->where('doctor_confirmation', 'confirmed')
            ->where('facility_confirmation', 'confirmed')
            ->with(['facility.county'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $todaySessions = $sessions->filter(fn ($s) => $s->session_date->toDateString() === $today->toDateString());
        $nextClinic = $sessions->first(fn ($s) => $s->session_date->gt($today));

        $upcoming = $sessions->map(fn ($s) => [
            'id' => $s->id,
            'session_date' => $s->session_date->format('Y-m-d'),
            'day' => $s->session_date->format('l'),
            'day_short' => $s->session_date->format('D, M j'),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'facility' => ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city, 'county' => $s->facility->county?->name],
            'available_slots' => $s->available_slots,
            'consultation_fee' => $s->consultation_fee,
            'is_bookable' => $s->is_bookable,
            'is_confirmed' => $s->is_confirmed,
            'max_appointments' => $s->max_appointments,
        ])->values();

        return response()->json([
            'data' => [
                'id' => $doctor->id,
                'slug' => $doctor->slug,
                'name' => $doctor->display_name,
                'is_following' => $isFollowing,
                'biography' => $doctor->biography,
                'qualifications' => $doctor->qualifications,
                'years_of_experience' => $doctor->years_of_experience,
                'avatar' => $doctor->avatar,
                'is_verified' => $doctor->is_verified,
                'is_featured' => $doctor->is_featured,
                'consultation_fee' => $doctor->consultation_fee,
                'specialties' => $doctor->specialties->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'icon' => $s->icon,
                    'is_primary' => (bool) $s->pivot->is_primary,
                ]),
                'facilities' => $doctor->facilities->map(fn ($f) => [
                    'id' => $f->id, 'name' => $f->name, 'city' => $f->city,
                    'county' => $f->county?->name, 'type' => $f->type,
                ]),
                'upcoming_sessions' => $upcoming,
                'next_clinic' => $nextClinic ? [
                    'id' => $nextClinic->id,
                    'session_date' => $nextClinic->session_date->format('Y-m-d'),
                    'day' => $nextClinic->session_date->format('l'),
                    'day_short' => $nextClinic->session_date->format('D, M j'),
                    'start_time' => substr($nextClinic->start_time, 0, 5),
                    'end_time' => substr($nextClinic->end_time, 0, 5),
                    'facility' => ['id' => $nextClinic->facility->id, 'name' => $nextClinic->facility->name, 'city' => $nextClinic->facility->city],
                    'available_slots' => $nextClinic->available_slots,
                    'consultation_fee' => $nextClinic->consultation_fee,
                    'is_bookable' => $nextClinic->is_bookable,
                ] : null,
                'today_clinics' => $todaySessions->map(fn ($s) => [
                    'id' => $s->id,
                    'facility' => ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city],
                    'start_time' => substr($s->start_time, 0, 5),
                    'end_time' => substr($s->end_time, 0, 5),
                    'available_slots' => $s->available_slots,
                    'consultation_fee' => $s->consultation_fee,
                    'is_bookable' => $s->is_bookable,
                    'is_confirmed' => $s->is_confirmed,
                    'max_appointments' => $s->max_appointments,
                ])->values(),
            ],
        ]);
    }

    /**
     * GET /api/doctors/{slug}/sessions
     */
    public function sessions(Request $request, string $slug): JsonResponse
    {
        $doctor = Doctor::where('slug', $slug)->firstOrFail();
        $from = $request->filled('from') ? Carbon::parse($request->from) : today();
        $to = $request->filled('to') ? Carbon::parse($request->to) : $from->copy()->addDays(30);

        $sessions = ClinicSession::where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'confirmed')
            ->where('doctor_confirmation', 'confirmed')
            ->where('facility_confirmation', 'confirmed')
            ->with(['facility'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $sessions->map(fn ($s) => [
            'id' => $s->id,
            'session_date' => $s->session_date->format('Y-m-d'),
            'day' => $s->session_date->format('l'),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'facility' => ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city],
            'available_slots' => $s->available_slots,
            'consultation_fee' => $s->consultation_fee,
            'is_bookable' => $s->is_bookable,
            'is_confirmed' => $s->is_confirmed,
            'max_appointments' => $s->max_appointments,
        ])]);
    }

    /**
     * GET /api/sessions/{id} — Single session for booking.
     */
    public function showSession(int $id): JsonResponse
    {
        $session = ClinicSession::with(['doctor.specialties', 'facility.county', 'facilityLocation'])->findOrFail($id);

        return response()->json(['data' => [
            'id' => $session->id,
            'session_date' => $session->session_date->format('Y-m-d'),
            'day' => $session->session_date->format('l, F j, Y'),
            'start_time' => substr($session->start_time, 0, 5),
            'end_time' => substr($session->end_time, 0, 5),
            'slot_duration_minutes' => $session->slot_duration_minutes,
            'max_appointments' => $session->max_appointments,
            'booked_appointments' => $session->booked_appointments,
            'available_slots' => $session->available_slots,
            'consultation_fee' => $session->consultation_fee,
            'status' => $session->status,
            'is_confirmed' => $session->is_confirmed,
            'is_bookable' => $session->is_bookable,
            'doctor' => [
                'id' => $session->doctor->id,
                'slug' => $session->doctor->slug,
                'name' => $session->doctor->display_name,
                'avatar' => $session->doctor->avatar,
                'is_verified' => $session->doctor->is_verified,
                'consultation_fee' => $session->doctor->consultation_fee,
                'specialties' => $session->doctor->specialties->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'icon' => $s->icon]),
            ],
            'facility' => [
                'id' => $session->facility->id,
                'name' => $session->facility->name,
                'city' => $session->facility->city,
                'county' => $session->facility->county?->name,
                'address' => $session->facility->address,
                'phone' => $session->facility->phone,
                'type' => $session->facility->type,
                'is_verified' => $session->facility->is_verified,
            ],
            'location' => $session->facilityLocation ? [
                'id' => $session->facilityLocation->id,
                'name' => $session->facilityLocation->name,
                'address' => $session->facilityLocation->address,
                'city' => $session->facilityLocation->city,
            ] : null,
        ]]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function formatDoctor($doctor): array
    {
        if (! $doctor) {
            return [];
        }
        $primary = $doctor->specialties->first(fn ($s) => $s->pivot?->is_primary) ?? $doctor->specialties->first();

        return [
            'id' => $doctor->id,
            'slug' => $doctor->slug,
            'name' => $doctor->display_name,
            'avatar' => $doctor->avatar,
            'is_verified' => $doctor->is_verified,
            'is_featured' => $doctor->is_featured,
            'consultation_fee' => $doctor->consultation_fee,
            'years_of_experience' => $doctor->years_of_experience,
            'primary_specialty' => $primary ? ['id' => $primary->id, 'name' => $primary->name, 'icon' => $primary->icon] : null,
            'specialties' => $doctor->specialties->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'icon' => $s->icon, 'is_primary' => (bool) $s->pivot->is_primary]),
        ];
    }

    private function formatSession($session): array
    {
        if (! $session) {
            return [];
        }

        return [
            'id' => $session->id,
            'session_date' => $session->session_date->format('Y-m-d'),
            'start_time' => substr($session->start_time, 0, 5),
            'end_time' => substr($session->end_time, 0, 5),
            'facility' => ['id' => $session->facility->id, 'name' => $session->facility->name, 'city' => $session->facility->city, 'county' => $session->facility->county?->name],
            'available_slots' => $session->available_slots,
            'consultation_fee' => $session->consultation_fee,
            'is_bookable' => $session->is_bookable,
            'is_confirmed' => $session->is_confirmed,
            'max_appointments' => $session->max_appointments,
        ];
    }

    private function emptySearchResponse(Carbon $date, int $perPage, int $page): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 0,
                'date' => $date->toDateString(),
                'day' => $date->format('l, F j, Y'),
            ],
        ]);
    }
}
