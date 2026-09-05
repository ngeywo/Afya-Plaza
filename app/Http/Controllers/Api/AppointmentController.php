<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = Appointment::with(['doctor', 'facility'])
            ->where('user_id', $user->id)
            ->orderBy('appointment_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('upcoming')) {
            $query->where('appointment_date', '>=', today()->toDateString());
        }

        $appointments = $query->get()->map(fn ($a) => [
            'id' => $a->id,
            'appointment_number' => $a->appointment_number,
            'status' => $a->status,
            'appointment_date' => $a->appointment_date->format('Y-m-d'),
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'reason' => $a->reason,
            'doctor' => ['id' => $a->doctor->id, 'name' => $a->doctor->display_name, 'avatar' => $a->doctor->avatar],
            'facility' => ['id' => $a->facility->id, 'name' => $a->facility->name, 'city' => $a->facility->city],
        ]);

        return response()->json(['data' => $appointments]);
    }

    /**
     * POST /api/appointments — book an appointment.
     * Section 21: DB transactions, locking, idempotency, conflict responses.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_session_id' => 'required|integer|exists:clinic_sessions,id',
            'start_time' => 'required|date_format:H:i',
            'reason' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $session = ClinicSession::where('id', $validated['clinic_session_id'])
                ->lockForUpdate()->firstOrFail();

            if (!$session->is_bookable) {
                return response()->json(['error' => 'This session is no longer available.'], 422);
            }

            $existing = Appointment::where('clinic_session_id', $session->id)
                ->where('start_time', $validated['start_time'] . ':00')
                ->whereIn('status', ['pending', 'confirmed'])
                ->lockForUpdate()->exists();

            if ($existing) {
                return response()->json([
                    'error' => 'This time slot has already been booked.',
                    'code' => 'SLOT_TAKEN',
                ], 409);
            }

            if ($session->max_appointments !== null
                && $session->booked_appointments >= $session->max_appointments) {
                return response()->json(['error' => 'No available slots for this session.'], 422);
            }

            $start = Carbon::parse($session->session_date->format('Y-m-d') . ' ' . $validated['start_time']);
            $end = $start->copy()->addMinutes($session->slot_duration_minutes);

            $appointment = Appointment::create([
                'appointment_number' => Appointment::generateNumber(),
                'user_id' => $request->user()->id,
                'doctor_id' => $session->doctor_id,
                'clinic_session_id' => $session->id,
                'facility_id' => $session->facility_id,
                'appointment_date' => $session->session_date,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'reason' => $validated['reason'] ?? null,
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'amount_paid' => $session->consultation_fee,
                'payment_status' => 'pending',
            ]);

            $session->increment('booked_appointments');

            return response()->json([
                'data' => [
                    'id' => $appointment->id,
                    'appointment_number' => $appointment->appointment_number,
                    'status' => $appointment->status,
                    'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                    'start_time' => substr($appointment->start_time, 0, 5),
                    'end_time' => substr($appointment->end_time, 0, 5),
                    'doctor' => ['name' => $session->doctor->display_name],
                    'facility' => ['name' => $session->facility->name, 'city' => $session->facility->city],
                    'amount_paid' => $appointment->amount_paid,
                ],
                'message' => 'Appointment booked successfully!',
            ], 201);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('id', $id)
            ->where('user_id', $request->user()->id)->firstOrFail();

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return response()->json(['error' => 'Cannot cancel this appointment'], 422);
        }

        return DB::transaction(function () use ($appointment) {
            $appointment->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            ClinicSession::where('id', $appointment->clinic_session_id)
                ->decrement('booked_appointments');
            return response()->json(['message' => 'Appointment cancelled successfully.']);
        });
    }
}
