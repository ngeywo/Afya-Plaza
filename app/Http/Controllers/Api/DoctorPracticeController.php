<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\DoctorFacility;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorPracticeController extends Controller
{
    public function weeklyOverview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $weekStart = today()->startOfWeek();
        $weekEnd = today()->endOfWeek();

        $sessions = ClinicSession::where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereIn('status', ['pending', 'confirmed'])
            ->with(['facility'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $daySessions = $sessions->filter(fn ($s) => $s->session_date->format('Y-m-d') === $day->format('Y-m-d'));
            $weekDays[] = [
                'date' => $day->format('Y-m-d'),
                'day_name' => $day->format('l'),
                'day_short' => $day->format('D'),
                'is_today' => $day->isToday(),
                'is_past' => $day->isPast() && ! $day->isToday(),
                'sessions' => $daySessions->map(fn ($s) => $this->formatWeekSession($s))->values(),
                'total_sessions' => $daySessions->count(),
                'total_appointments' => $daySessions->sum('booked_appointments'),
            ];
        }

        $weekStats = [
            'total_sessions' => $sessions->count(),
            'confirmed_sessions' => $sessions->where('status', 'confirmed')->count(),
            'total_appointments' => $sessions->sum('booked_appointments'),
            'facilities_visited' => $sessions->pluck('facility_id')->unique()->count(),
        ];

        return response()->json(['data' => ['week_start' => $weekStart->format('Y-m-d'), 'week_end' => $weekEnd->format('Y-m-d'), 'days' => $weekDays, 'stats' => $weekStats]]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $period = (int) $request->input('period', 30);
        $from = now()->subDays($period);

        $totalAppointments = Appointment::where('doctor_id', $doctor->id)->where('created_at', '>=', $from)->count();
        $completedAppointments = Appointment::where('doctor_id', $doctor->id)->where('status', 'completed')->where('created_at', '>=', $from)->count();
        $cancelledAppointments = Appointment::where('doctor_id', $doctor->id)->where('status', 'cancelled')->where('created_at', '>=', $from)->count();
        $noShows = Appointment::where('doctor_id', $doctor->id)->where('status', 'no_show')->where('created_at', '>=', $from)->count();

        $revenue = Payment::where('doctor_id', $doctor->id)->where('status', 'paid')->where('confirmed_at', '>=', $from)->sum('net_amount');
        $grossRevenue = Payment::where('doctor_id', $doctor->id)->where('status', 'paid')->where('confirmed_at', '>=', $from)->sum('gross_amount');
        $commission = Payment::where('doctor_id', $doctor->id)->where('status', 'paid')->where('confirmed_at', '>=', $from)->sum('commission_amount');

        $totalSessions = ClinicSession::where('doctor_id', $doctor->id)->where('created_at', '>=', $from)->count();
        $confirmedSessions = ClinicSession::where('doctor_id', $doctor->id)->where('status', 'confirmed')->where('created_at', '>=', $from)->count();
        $cancelledSessions = ClinicSession::where('doctor_id', $doctor->id)->where('status', 'cancelled')->where('created_at', '>=', $from)->count();

        $completionRate = $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 1) : 0;
        $noShowRate = $totalAppointments > 0 ? round(($noShows / $totalAppointments) * 100, 1) : 0;

        $weeklyTrend = [];
        for ($w = 3; $w >= 0; $w--) {
            $weekStart = now()->subWeeks($w)->startOfWeek();
            $weekEnd = now()->subWeeks($w)->endOfWeek();
            $weeklyTrend[] = ['week' => $weekStart->format('M j'), 'appointments' => Appointment::where('doctor_id', $doctor->id)->whereBetween('created_at', [$weekStart, $weekEnd])->count(), 'revenue' => Payment::where('doctor_id', $doctor->id)->where('status', 'paid')->whereBetween('confirmed_at', [$weekStart, $weekEnd])->sum('net_amount')];
        }

        return response()->json(['data' => ['period_days' => $period, 'appointments' => ['total' => $totalAppointments, 'completed' => $completedAppointments, 'cancelled' => $cancelledAppointments, 'no_shows' => $noShows, 'completion_rate' => $completionRate, 'no_show_rate' => $noShowRate], 'revenue' => ['gross' => $grossRevenue, 'net' => $revenue, 'commission' => $commission, 'currency' => 'KES'], 'sessions' => ['total' => $totalSessions, 'confirmed' => $confirmedSessions, 'cancelled' => $cancelledSessions], 'weekly_trend' => $weeklyTrend]]);
    }

    public function facilityPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $from = now()->subDays(30);
        $facilities = DoctorFacility::where('doctor_id', $doctor->id)->where('status', 'active')->with(['facility'])->get();

        $performance = $facilities->map(function ($df) use ($from, $doctor) {
            $sessions = ClinicSession::where('doctor_id', $doctor->id)->where('facility_id', $df->facility_id)->where('created_at', '>=', $from);
            $totalSessions = $sessions->count();
            $totalAppointments = $sessions->sum('booked_appointments');
            $totalRevenue = Appointment::where('doctor_id', $doctor->id)->where('facility_id', $df->facility_id)->where('status', 'completed')->where('created_at', '>=', $from)->sum('amount_paid');

            return ['facility' => ['id' => $df->facility->id, 'name' => $df->facility->name, 'city' => $df->facility->city, 'type' => $df->facility->type], 'consultation_fee' => $df->consultation_fee ?? '0.00', 'stats' => ['sessions' => $totalSessions, 'appointments' => $totalAppointments, 'revenue' => $totalRevenue]];
        });

        return response()->json(['data' => $performance]);
    }

    public function detectConflicts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $date = $request->input('date', today()->toDateString());
        $sessions = ClinicSession::where('doctor_id', $doctor->id)->where('session_date', $date)->whereIn('status', ['pending', 'confirmed'])->with('facility')->orderBy('start_time')->get();

        $conflicts = [];
        $sessionsArray = $sessions->toArray();
        for ($i = 0; $i < count($sessionsArray); $i++) {
            for ($j = $i + 1; $j < count($sessionsArray); $j++) {
                $s1 = $sessionsArray[$i];
                $s2 = $sessionsArray[$j];
                if ($s1['end_time'] > $s2['start_time'] && $s1['start_time'] < $s2['end_time']) {
                    $conflicts[] = ['type' => 'time_overlap', 'session_1' => ['id' => $s1['id'], 'facility' => $s1['facility']['name'] ?? 'Unknown', 'start_time' => substr($s1['start_time'], 0, 5), 'end_time' => substr($s1['end_time'], 0, 5)], 'session_2' => ['id' => $s2['id'], 'facility' => $s2['facility']['name'] ?? 'Unknown', 'start_time' => substr($s2['start_time'], 0, 5), 'end_time' => substr($s2['end_time'], 0, 5)]];
                }
            }
        }

        return response()->json(['data' => ['date' => $date, 'total_sessions' => $sessions->count(), 'has_conflicts' => count($conflicts) > 0, 'conflicts' => $conflicts]]);
    }

    private function formatWeekSession($s): array
    {
        return ['id' => $s->id, 'facility' => ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city], 'start_time' => substr($s->start_time, 0, 5), 'end_time' => substr($s->end_time, 0, 5), 'status' => $s->status, 'is_confirmed' => $s->is_confirmed, 'booked_appointments' => $s->booked_appointments, 'available_slots' => $s->available_slots, 'consultation_fee' => $s->consultation_fee];
    }
}
