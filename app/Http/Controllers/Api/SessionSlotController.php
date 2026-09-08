<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class SessionSlotController extends Controller
{
    /**
     * GET /api/sessions/{id}/slots
     *
     * Calculate available time slots for a clinic session.
     * Availability is real — derived from session time + booked appointments.
     * Section 19 of product vision.
     */
    public function index(int $sessionId): JsonResponse
    {
        $session = ClinicSession::with(['appointments' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->findOrFail($sessionId);

        if ($session->session_date->lt(today())) {
            return response()->json(['error' => 'Session date has passed'], 422);
        }

        $slots = $this->calculateSlots($session);
        $bookedTimes = $session->appointments->pluck('start_time')
            ->map(fn ($t) => substr($t, 0, 5))->toArray();

        $available = collect($slots)->map(fn ($slot) => [
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'is_available' => ! in_array($slot['start_time'], $bookedTimes),
        ]);

        return response()->json([
            'data' => $available,
            'session' => [
                'id' => $session->id,
                'date' => $session->session_date->format('Y-m-d'),
                'facility' => $session->facility->name,
                'consultation_fee' => $session->consultation_fee,
                'status' => $session->status,
                'is_confirmed' => $session->is_confirmed,
            ],
        ]);
    }

    private function calculateSlots(ClinicSession $session): array
    {
        $slots = [];
        $start = Carbon::parse($session->session_date->format('Y-m-d').' '.$session->start_time);
        $end = Carbon::parse($session->session_date->format('Y-m-d').' '.$session->end_time);
        $duration = $session->slot_duration_minutes;

        while ($start->lt($end)) {
            $slotEnd = $start->copy()->addMinutes($duration);
            if ($slotEnd->gt($end)) {
                break;
            }
            $slots[] = ['start_time' => $start->format('H:i'), 'end_time' => $slotEnd->format('H:i')];
            $start->addMinutes($duration);
        }

        return $slots;
    }
}
