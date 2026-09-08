<?php

namespace App\Http\Controllers\Api;

use App\Enums\DoctorRelationshipStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicSession;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();
        $query = Doctor::with(['specialties', 'facilities.county'])->where('is_active', true);

        if ($request->filled('name')) {
            $query->where('display_name', 'like', '%'.$request->name.'%');
        }
        if ($request->filled('specialty_id')) {
            $query->whereHas('specialties', fn ($q) => $q->where('specialties.id', $request->specialty_id));
        }
        if ($request->filled('county_id')) {
            $query->whereHas('facilities', fn ($q) => $q->whereHas('county', fn ($cq) => $cq->where('counties.id', $request->county_id)));
        }
        // Section 3: Search is session-driven. A doctor should only appear if they have
        // an actual confirmed session on the requested date. Section 27 business test.
        $query->whereHas('clinicSessions', fn ($q) => $q->where('session_date', $date->toDateString())->where('status', 'confirmed'));

        $doctors = $query->get();

        return response()->json([
            'data' => $doctors->map(fn ($d) => [
                'id' => $d->id, 'slug' => $d->slug, 'name' => $d->display_name,
                'avatar' => $d->avatar, 'is_verified' => $d->is_verified, 'is_featured' => $d->is_featured,
                'consultation_fee' => $d->consultation_fee, 'years_of_experience' => $d->years_of_experience,
                'specialties' => $d->specialties->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'icon' => $s->icon, 'is_primary' => (bool) $s->pivot->is_primary]),
                'session' => $this->doctorSessionForDate($d, $date),
            ]),
            'meta' => ['total' => $doctors->count(), 'date' => $date->toDateString()],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $doctor = Doctor::where('slug', $slug)->with(['specialties', 'facilities.county'])->firstOrFail();
        $today = today();
        $weekEnd = $today->copy()->addDays(14);
        $sessions = ClinicSession::where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$today->toDateString(), $weekEnd->toDateString()])
            ->where('status', 'confirmed')
            ->with('facility')->orderBy('session_date')->get();

        $sessionsForVue = $sessions->map(fn ($s) => [
            'id' => $s->id,
            'session_date' => $s->session_date->format('Y-m-d'),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'facility' => ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city],
            'max_appointments' => $s->max_appointments,
            'booked_appointments' => $s->booked_appointments,
            'is_active' => $s->status === 'confirmed',
        ]);

        return response()->json(['data' => [
            'id' => $doctor->id, 'name' => $doctor->display_name, 'slug' => $doctor->slug,
            'biography' => $doctor->biography, 'qualifications' => $doctor->qualifications,
            'years_of_experience' => $doctor->years_of_experience, 'avatar' => $doctor->avatar,
            'is_verified' => $doctor->is_verified, 'is_featured' => $doctor->is_featured,
            'consultation_fee' => $doctor->consultation_fee,
            'specialties' => $doctor->specialties->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'icon' => $s->icon, 'is_primary' => (bool) $s->pivot->is_primary]),
            'facilities' => $doctor->facilities->map(fn ($f) => ['name' => $f->name, 'city' => $f->city, 'county' => $f->county?->name]),
            'sessions' => $sessionsForVue,
        ]]);
    }

    public function sessions(Request $request, int $id): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->from) : today();
        $to = $request->filled('to') ? Carbon::parse($request->to) : $from->copy()->addDays(7);

        $sessions = ClinicSession::where('doctor_id', $id)
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'confirmed')->with('facility')->orderBy('session_date')->get();

        return response()->json(['data' => $sessions->map(fn ($s) => [
            'id' => $s->id, 'date' => $s->session_date->format('Y-m-d'), 'day' => $s->session_date->format('l'),
            'facility' => ['name' => $s->facility->name, 'city' => $s->facility->city],
            'start_time' => substr($s->start_time, 0, 5), 'end_time' => substr($s->end_time, 0, 5),
            'consultation_fee' => $s->consultation_fee, 'status' => $s->status,
            'is_confirmed' => $s->is_confirmed, 'available_slots' => $s->available_slots, 'is_bookable' => $s->is_bookable,
        ])]);
    }

    /**
     * Phase 23: GET /api/doctors/{slug}/today — "Where is my doctor today?"
     * Public. Returns today's confirmed clinics per facility, plus the next
     * clinic if none today. Relationship-driven (ACTIVE relationships only).
     */
    public function today(string $slug): JsonResponse
    {
        $doctor = Doctor::with(['specialties', 'user'])->where('slug', $slug)->firstOrFail();
        $date = today();

        $todaySessions = ClinicSession::with(['facility.county'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('session_date', $date)
            ->where('status', 'confirmed')
            ->orderBy('start_time')
            ->get();

        $next = null;
        if ($todaySessions->isEmpty()) {
            $next = ClinicSession::with(['facility'])
                ->where('doctor_id', $doctor->id)
                ->whereDate('session_date', '>', $date)
                ->where('status', 'confirmed')
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->first();
        }

        $licensing = $doctor->doctorFacilities()
            ->where('status', DoctorRelationshipStatus::ACTIVE)
            ->with('facility')
            ->get();

        return response()->json(['data' => [
            'doctor' => ['id' => $doctor->id, 'slug' => $doctor->slug, 'name' => $doctor->display_name, 'is_verified' => $doctor->is_verified],
            'date' => $date->format('Y-m-d'),
            'day' => $date->format('l, F j, Y'),
            'licensed_facilities' => $licensing->map(fn ($df) => $df->facility ? ['id' => $df->facility->id, 'name' => $df->facility->name, 'city' => $df->facility->city] : null)->values(),
            'today' => $todaySessions->map(fn ($s) => [
                'session_id' => $s->id,
                'facility' => ['id' => $s->facility_id, 'name' => $s->facility->name, 'city' => $s->facility->city, 'county' => $s->facility->county?->name],
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'consultation_fee' => $s->consultation_fee,
                'available_slots' => $s->available_slots,
                'is_bookable' => $s->is_bookable,
            ])->values(),
            'next_session' => $next ? [
                'date' => $next->session_date->format('Y-m-d'),
                'day' => $next->session_date->format('l, F j'),
                'facility' => $next->facility->name,
                'city' => $next->facility->city,
                'start_time' => substr($next->start_time, 0, 5),
                'end_time' => substr($next->end_time, 0, 5),
            ] : null,
        ]]);
    }

    private function doctorSessionForDate(Doctor $doctor, Carbon $date): ?array
    {
        $session = ClinicSession::where('doctor_id', $doctor->id)
            ->where('session_date', $date->toDateString())->where('status', 'confirmed')
            ->with('facility')->first();

        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id, 'date' => $session->session_date->format('Y-m-d'),
            'day' => $session->session_date->format('l'),
            'facility' => ['name' => $session->facility->name, 'city' => $session->facility->city, 'county' => $session->facility->county?->name],
            'start_time' => substr($session->start_time, 0, 5), 'end_time' => substr($session->end_time, 0, 5),
            'consultation_fee' => $session->consultation_fee, 'status' => $session->status,
            'is_confirmed' => $session->is_confirmed, 'available_slots' => $session->available_slots, 'is_bookable' => $session->is_bookable,
        ];
    }
}
