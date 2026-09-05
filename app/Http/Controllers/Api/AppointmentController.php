<?php

namespace App\Http\Controllers\Api;

use App\Events\AppointmentBooked;
use App\Exceptions\SlotConflictException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $service) {}

    /**
     * GET /api/appointments — List the authenticated patient's appointments.
     * Phase 17: supports ?filter=upcoming|past|cancelled in addition to legacy params.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = Appointment::with(['doctor.specialties', 'facility', 'clinicSession', 'payments'])
            ->where('user_id', $user->id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time');

        if ($request->filled('filter')) {
            match ($request->filter) {
                'upcoming' => $query->upcoming(),
                'past' => $query->past(),
                'cancelled' => $query->cancelled(),
                default => null,
            };
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('upcoming')) {
            $query->where('appointment_date', '>=', today()->toDateString());
        }

        $appointments = $query->limit(200)->get()->map(fn ($a) => $this->formatAppointment($a));

        return response()->json(['data' => $appointments]);
    }

    /**
     * GET /api/appointments/{id} — Get a single appointment.
     * IDOR protection: patient can only see their own appointments.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $appointment = Appointment::with(['doctor.specialties', 'facility', 'facilityLocation', 'clinicSession', 'payments'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $appointment) {
            return response()->json(['error' => 'Appointment not found.'], 404);
        }

        return response()->json(['data' => $this->formatAppointment($appointment)]);
    }

    /**
     * POST /api/appointments — Book an appointment.
     * Concurrency: DB transaction + lockForUpdate on session row.
     * Security: Validates session is confirmed, future, and bookable.
     * Backend fee is authoritative (Section 17).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_session_id' => 'required|integer|exists:clinic_sessions,id',
            'start_time' => 'required|date_format:H:i',
            'reason' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $session = ClinicSession::where('id', $validated['clinic_session_id'])
                ->lockForUpdate()->first();

            if (! $session) {
                return response()->json([
                    'error' => 'Session not found.',
                    'code' => 'SESSION_NOT_FOUND',
                ], 422);
            }

            // Phase 17 idempotency: replay of an identical request returns the
            // original appointment instead of creating a duplicate.
            if (! empty($validated['idempotency_key'])) {
                $existing = Appointment::where('user_id', $request->user()->id)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();
                if ($existing) {
                    return response()->json([
                        'data' => $this->formatAppointment($existing),
                        'message' => 'Appointment already booked.',
                        'existing' => true,
                    ], 200);
                }
            }

            if ($session->status === 'cancelled') {
                return response()->json([
                    'error' => 'This clinic session has been cancelled by the doctor or facility.',
                    'code' => 'SESSION_CANCELLED',
                ], 422);
            }
            if ($session->status !== 'confirmed') {
                return response()->json([
                    'error' => 'This clinic session is not available for booking.',
                    'code' => 'SESSION_NOT_AVAILABLE',
                ], 422);
            }

            if ($session->session_date->lt(today())) {
                return response()->json([
                    'error' => 'Cannot book appointments for past dates.',
                    'code' => 'SESSION_PAST',
                ], 422);
            }

            if ($session->max_appointments !== null
                && $session->booked_appointments >= $session->max_appointments) {
                return response()->json([
                    'error' => 'No available slots remain for this session.',
                    'code' => 'NO_CAPACITY',
                ], 422);
            }

            if (! $session->is_bookable) {
                return response()->json([
                    'error' => 'This session is no longer available for booking.',
                    'code' => 'SESSION_NOT_BOOKABLE',
                ], 422);
            }

            $existing = Appointment::where('clinic_session_id', $session->id)
                ->where('start_time', $validated['start_time'].':00')
                ->whereIn('status', ['pending', 'confirmed'])
                ->lockForUpdate()->exists();

            if ($existing) {
                return response()->json([
                    'error' => 'This time slot has already been booked by another patient. Please choose a different time.',
                    'code' => 'SLOT_TAKEN',
                ], 409);
            }

            $start = Carbon::parse($session->session_date->format('Y-m-d').' '.$validated['start_time']);
            $end = $start->copy()->addMinutes($session->slot_duration_minutes);

            $appointment = Appointment::create([
                'appointment_number' => Appointment::generateNumber(),
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'user_id' => $request->user()->id,
                'doctor_id' => $session->doctor_id,
                'clinic_session_id' => $session->id,
                'facility_id' => $session->facility_id,
                'facility_location_id' => $session->facility_location_id,
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
            $appointment->load(['doctor.specialties', 'facility', 'clinicSession', 'payments']);

            // Phase 10: notify the patient (queued via listener)
            AppointmentBooked::dispatch($appointment);

            return response()->json([
                'data' => $this->formatAppointment($appointment),
                'message' => 'Appointment booked successfully!',
            ], 201);
        });
    }

    /**
     * PATCH /api/appointments/{id} — Update an appointment (reschedule or cancel via PATCH).
     *
     * Reschedule: patient moves to a new slot. Old context is preserved
     * via a note (Section 17/19). New slot is validated server-side.
     *
     * IDOR: patient can only modify their own appointments.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $apt = Appointment::where('id', $id)->where('user_id', $user->id)->first();

        if (! $apt) {
            return response()->json(['error' => 'Appointment not found.'], 404);
        }

        if (in_array($apt->status, ['completed', 'cancelled'])) {
            return response()->json([
                'error' => 'Cannot modify a '.$apt->status.' appointment.',
            ], 422);
        }

        $validated = $request->validate([
            'new_session_id' => 'nullable|integer|exists:clinic_sessions,id',
            'new_start_time' => 'nullable|date_format:H:i',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        // ── RESCHEDULING ────────────────────────────────────────────
        if (! empty($validated['new_session_id']) && ! empty($validated['new_start_time'])) {
            return $this->reschedule($apt, $validated, $request);
        }

        // ── CANCELLATION (via PATCH) ────────────────────────────────
        if (! empty($validated['cancellation_reason'])) {
            return $this->cancelViaUpdate($apt, $validated, $request);
        }

        return response()->json(['error' => 'Nothing to update.'], 422);
    }

    /**
     * Phase 17: rescheduling is delegated to AppointmentService which enforces
     * same-doctor, bookability, capacity and slot conflicts, records an audit
     * trail and notifies the patient.
     */
    private function reschedule(Appointment $apt, array $validated, Request $request)
    {
        $newSession = ClinicSession::find($validated['new_session_id']);
        if (! $newSession) {
            return response()->json([
                'error' => 'The selected clinic session is not available.',
                'code' => 'SESSION_NOT_AVAILABLE',
            ], 422);
        }

        try {
            $updated = $this->service->reschedule(
                $apt,
                $request->user(),
                $newSession,
                $validated['new_start_time'],
                $validated['cancellation_reason'] ?? null,
            );
        } catch (SlotConflictException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 'SLOT_TAKEN',
            ], 409);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $updated->load(['doctor.specialties', 'facility', 'clinicSession', 'payments']);

        return response()->json([
            'data' => $this->formatAppointment($updated),
            'message' => 'Appointment rescheduled successfully.',
        ]);
    }

    private function cancelViaUpdate(Appointment $apt, array $validated, Request $request)
    {
        try {
            $updated = $this->service->patientCancel($apt, $request->user(), $validated['cancellation_reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->formatAppointment($updated),
            'message' => 'Appointment cancelled.',
        ]);
    }

    /**
     * DELETE /api/appointments/{id} — Cancel an appointment.
     * Phase 17: delegated to AppointmentService::patientCancel which is
     * IDOR-safe (owner or super admin only), releases the slot, audits and
     * notifies patient + doctor via the single AppointmentCancelled event.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment = Appointment::where('id', $id)->first();
        if (! $appointment) {
            return response()->json(['error' => 'Appointment not found.'], 404);
        }

        try {
            $this->service->patientCancel($appointment, $request->user(), $validated['cancellation_reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Appointment cancelled successfully.']);
    }

    /**
     * Format an appointment for API response.
     * Includes clinic session context for historical accuracy (Section 12).
     */
    private function formatAppointment(Appointment $a): array
    {
        $doctorSpecialties = $a->doctor?->specialties ?? collect();
        $primarySpec = $doctorSpecialties->first(fn ($s) => $s->pivot?->is_primary)
            ?? $doctorSpecialties->first();

        return [
            'id' => $a->id,
            'appointment_number' => $a->appointment_number,
            'status' => $a->status,
            'appointment_date' => $a->appointment_date->format('Y-m-d'),
            'day' => $a->appointment_date->format('l, F j, Y'),
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'reason' => $a->reason,
            'notes' => $a->notes,
            'cancellation_reason' => $a->cancellation_reason,
            'amount_paid' => $a->amount_paid,
            'payment_status' => $a->payment_status,
            'allowed_actions' => $a->allowedPatientActions(),
            'confirmed_at' => $a->confirmed_at?->toIso8601String(),
            'cancelled_at' => $a->cancelled_at?->toIso8601String(),
            'checked_in_at' => $a->checked_in_at?->toIso8601String(),
            'consultation_started_at' => $a->consultation_started_at?->toIso8601String(),
            'no_show_at' => $a->no_show_at?->toIso8601String(),
            'completed_at' => $a->completed_at?->toIso8601String(),
            'created_at' => $a->created_at?->toIso8601String(),
            'doctor' => $a->doctor ? [
                'id' => $a->doctor->id,
                'name' => $a->doctor->display_name,
                'slug' => $a->doctor->slug,
                'avatar' => $a->doctor->avatar,
                'specialty' => $primarySpec?->name,
            ] : null,
            'facility' => $a->facility ? [
                'id' => $a->facility->id,
                'name' => $a->facility->name,
                'city' => $a->facility->city,
                'county' => $a->facility->county?->name,
                'type' => $a->facility->type,
                'address' => $a->facility->address,
            ] : null,
            // Phase 17: the exact location booked (historical truth)
            'facility_location' => $a->facilityLocation ? [
                'id' => $a->facilityLocation->id,
                'name' => $a->facilityLocation->name,
                'address' => $a->facilityLocation->address,
                'city' => $a->facilityLocation->city,
            ] : null,
            'clinic_session' => $a->clinicSession ? [
                'id' => $a->clinicSession->id,
                'session_date' => $a->clinicSession->session_date->format('Y-m-d'),
                'consultation_fee' => $a->clinicSession->consultation_fee,
            ] : null,
            // Phase 17: payment transparency (financial history is never deleted)
            'payment' => $this->paymentSummary($a),
        ];
    }

    private function paymentSummary(Appointment $a): array
    {
        $last = $a->payments instanceof Collection
            ? $a->payments->sortByDesc('id')->first()
            : null;

        return [
            'status' => $a->payment_status,
            'amount' => $a->amount_paid,
            'reference' => $last?->reference,
            'refunded_amount' => $last && in_array($last->status, ['refunded', 'partially_refunded'])
                ? $last->refunded_amount
                : null,
        ];
    }
}
