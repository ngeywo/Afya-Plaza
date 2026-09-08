<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Services\FacilityAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicDayController extends Controller
{
    public function __construct(private FacilityAccessService $facilityAccess) {}

    public function facilityBoard(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $date = $request->query('date', Carbon::today()->toDateString());

        $sessions = ClinicSession::with(['doctor.specialties', 'facilityLocation'])
            ->where('facility_id', $facility->id)
            ->whereDate('session_date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('start_time')
            ->get();

        $appointments = Appointment::with(['user:id,name,phone,avatar', 'doctor:id,display_name,slug,avatar', 'checkedInByUser:id,name'])
            ->where('facility_id', $facility->id)
            ->whereDate('appointment_date', $date)
            ->orderBy('start_time')
            ->get();

        $sessionsData = $sessions->map(function ($s) use ($appointments) {
            $sa = $appointments->where('clinic_session_id', $s->id);

            return [
                'id' => $s->id,
                'doctor' => $s->doctor ? ['id' => $s->doctor->id, 'name' => $s->doctor->display_name, 'avatar' => $s->doctor->avatar, 'specialty' => $s->doctor->specialties?->first()?->name] : null,
                'facility_location' => $s->facilityLocation ? ['id' => $s->facilityLocation->id, 'name' => $s->facilityLocation->name] : null,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'status' => $s->status,
                'is_confirmed' => $s->is_confirmed,
                'consultation_fee' => $s->consultation_fee,
                'metrics' => ['total' => $sa->count(), 'checked_in' => $sa->where('status', 'checked_in')->count(), 'in_progress' => $sa->where('status', 'in_progress')->count(), 'completed' => $sa->where('status', 'completed')->count(), 'remaining' => $sa->whereIn('status', ['pending', 'confirmed'])->count()],
            ];
        });

        $appointmentsData = $appointments->map(fn ($a) => [
            'id' => $a->id,
            'appointment_number' => $a->appointment_number,
            'status' => $a->status,
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'reason' => $a->reason,
            'checked_in_at' => $a->checked_in_at?->toIso8601String(),
            'consultation_started_at' => $a->consultation_started_at?->toIso8601String(),
            'completed_at' => $a->completed_at?->toIso8601String(),
            'clinic_session_id' => $a->clinic_session_id,
            'doctor' => $a->doctor ? ['id' => $a->doctor->id, 'name' => $a->doctor->display_name] : null,
            'patient' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'phone' => $a->user->phone] : null,
            'checked_in_by' => $a->checkedInByUser ? ['id' => $a->checkedInByUser->id, 'name' => $a->checkedInByUser->name] : null,
        ]);

        return response()->json(['data' => [
            'date' => $date,
            'facility' => ['id' => $facility->id, 'name' => $facility->name, 'city' => $facility->city],
            'sessions' => $sessionsData,
            'appointments' => $appointmentsData,
            'metrics' => ['total' => $appointments->count(), 'checked_in' => $appointments->where('status', 'checked_in')->count(), 'in_progress' => $appointments->where('status', 'in_progress')->count(), 'completed' => $appointments->where('status', 'completed')->count(), 'no_show' => $appointments->where('status', 'no_show')->count(), 'cancelled' => $appointments->where('status', 'cancelled')->count(), 'pending' => $appointments->whereIn('status', ['pending', 'confirmed'])->count()],
        ]]);
    }

    /**
     * GET /api/doctor/clinic-day
     * Phase 11: Doctor clinic-day board.
     */
    public function doctorBoard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Doctor access only'], 403);
        }
        $doctor = $user->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $date = $request->query('date', Carbon::today()->toDateString());

        $sessions = ClinicSession::with(['facility:id,name,city', 'facilityLocation'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('session_date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('start_time')
            ->get();

        $appointments = Appointment::with(['user:id,name,phone,avatar', 'facility:id,name,city', 'checkedInByUser:id,name'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->orderBy('start_time')
            ->get();

        $sessionsData = $sessions->map(function ($s) use ($appointments) {
            $sa = $appointments->where('clinic_session_id', $s->id);

            return [
                'id' => $s->id,
                'facility' => $s->facility ? ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city] : null,
                'facility_location' => $s->facilityLocation ? ['id' => $s->facilityLocation->id, 'name' => $s->facilityLocation->name] : null,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'status' => $s->status,
                'is_confirmed' => $s->is_confirmed,
                'consultation_fee' => $s->consultation_fee,
                'metrics' => ['total' => $sa->count(), 'checked_in' => $sa->where('status', 'checked_in')->count(), 'in_progress' => $sa->where('status', 'in_progress')->count(), 'completed' => $sa->where('status', 'completed')->count(), 'remaining' => $sa->whereIn('status', ['pending', 'confirmed'])->count()],
            ];
        });

        $appointmentsData = $appointments->map(fn ($a) => [
            'id' => $a->id,
            'appointment_number' => $a->appointment_number,
            'status' => $a->status,
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'reason' => $a->reason,
            'checked_in_at' => $a->checked_in_at?->toIso8601String(),
            'consultation_started_at' => $a->consultation_started_at?->toIso8601String(),
            'completed_at' => $a->completed_at?->toIso8601String(),
            'clinic_session_id' => $a->clinic_session_id,
            'facility' => $a->facility ? ['id' => $a->facility->id, 'name' => $a->facility->name, 'city' => $a->facility->city] : null,
            'patient' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'phone' => $a->user->phone] : null,
            'checked_in_by' => $a->checkedInByUser ? ['id' => $a->checkedInByUser->id, 'name' => $a->checkedInByUser->name] : null,
        ]);

        return response()->json(['data' => [
            'date' => $date,
            'doctor' => ['id' => $doctor->id, 'name' => $doctor->display_name],
            'sessions' => $sessionsData,
            'appointments' => $appointmentsData,
            'metrics' => ['total' => $appointments->count(), 'checked_in' => $appointments->where('status', 'checked_in')->count(), 'in_progress' => $appointments->where('status', 'in_progress')->count(), 'completed' => $appointments->where('status', 'completed')->count(), 'no_show' => $appointments->where('status', 'no_show')->count(), 'cancelled' => $appointments->where('status', 'cancelled')->count(), 'pending' => $appointments->whereIn('status', ['pending', 'confirmed'])->count()],
        ]]);
    }
}
