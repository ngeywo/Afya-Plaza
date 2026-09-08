<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentOperationsController extends Controller
{
    public function __construct(private AppointmentService $service) {}

    public function checkIn(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $updated = $this->service->checkIn($appointment, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Patient checked in.']);
    }

    public function start(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        $doctor = $user->doctor;
        if (! $doctor && ! $user->isSuperAdmin()) {
            return response()->json(['error' => 'Only the doctor can start a consultation.'], 403);
        }
        if ($user->isSuperAdmin() && ! $doctor) {
            $doctor = $appointment->doctor;
        }
        try {
            $updated = $this->service->startConsultation($appointment, $doctor);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Consultation started.']);
    }

    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        $doctor = $user->doctor;
        if (! $doctor && ! $user->isSuperAdmin()) {
            return response()->json(['error' => 'Only the doctor can complete a consultation.'], 403);
        }
        if ($user->isSuperAdmin() && ! $doctor) {
            $doctor = $appointment->doctor;
        }
        try {
            $updated = $this->service->completeConsultation($appointment, $doctor);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Consultation completed.']);
    }

    public function noShow(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $updated = $this->service->markNoShow($appointment, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Marked as no-show.']);
    }

    public function facilityCancel(Request $request, Appointment $appointment): JsonResponse
    {
        $request->validate(['cancellation_reason' => 'nullable|string|max:500']);
        try {
            $updated = $this->service->facilityCancel($appointment, $request->user(), $request->input('cancellation_reason'));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Appointment cancelled.']);
    }

    /**
     * Phase 17: Doctor-initiated cancellation of one of their own appointments.
     * Section 14: doctor cannot attend clinic -> patients must be informed.
     * The service verifies doctor ownership, releases the slot, audits and
     * dispatches AppointmentCancelled (single notification path).
     */
    public function doctorCancel(Request $request, Appointment $appointment): JsonResponse
    {
        $request->validate(['cancellation_reason' => 'nullable|string|max:500']);
        try {
            $updated = $this->service->doctorCancel($appointment, $request->user(), $request->input('cancellation_reason'));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Appointment cancelled.']);
    }

    /**
     * Phase 18: Patient-initiated cancellation.
     * Only the patient who booked the appointment may cancel it.
     * The backend validates ownership, state, and issues a refund per policy.
     */
    public function patientCancel(Request $request, Appointment $appointment): JsonResponse
    {
        $request->validate(['cancellation_reason' => 'nullable|string|max:500']);
        $user = $request->user();

        // IDOR protection: patient can only cancel their own appointment
        if ($appointment->user_id !== $user->id) {
            return response()->json(['error' => 'You can only cancel your own appointments.'], 403);
        }

        try {
            $updated = $this->service->patientCancel($appointment, $user, $request->input('cancellation_reason'));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($updated), 'message' => 'Appointment cancelled.']);
    }

    private function format(Appointment $a): array
    {
        $a->loadMissing(['doctor', 'facility', 'clinicSession', 'checkedInByUser']);

        return [
            'id' => $a->id,
            'appointment_number' => $a->appointment_number,
            'status' => $a->status,
            'appointment_date' => $a->appointment_date->format('Y-m-d'),
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'confirmed_at' => $a->confirmed_at?->toIso8601String(),
            'checked_in_at' => $a->checked_in_at?->toIso8601String(),
            'checked_in_by' => $a->checked_in_by,
            'checked_in_by_name' => $a->checkedInByUser?->name,
            'consultation_started_at' => $a->consultation_started_at?->toIso8601String(),
            'completed_at' => $a->completed_at?->toIso8601String(),
            'cancelled_at' => $a->cancelled_at?->toIso8601String(),
            'no_show_at' => $a->no_show_at?->toIso8601String(),
            'reason' => $a->reason,
            'doctor' => $a->doctor ? ['id' => $a->doctor->id, 'name' => $a->doctor->display_name, 'slug' => $a->doctor->slug] : null,
            'facility' => $a->facility ? ['id' => $a->facility->id, 'name' => $a->facility->name, 'city' => $a->facility->city] : null,
            'clinic_session' => $a->clinicSession ? ['id' => $a->clinicSession->id, 'session_date' => $a->clinicSession->session_date->format('Y-m-d')] : null,
        ];
    }
}
