<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FacilitySubscription;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide financial and operational insight surfaces for the Super Admin.
 */
class AdminReportsController extends Controller
{
    // ─── Reports (the whole marketplace) ──────────────────────────────────────

    public function reports(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();
        $today = now()->toDateString();

        $revenue = Payment::paid()->whereBetween('created_at', [$from, $to]);

        $bookingsToday = Appointment::whereDate('appointment_date', $today);
        $bookingsMonth = Appointment::whereBetween('appointment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

        $daily = Appointment::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(appointment_date) as d, COUNT(*) as c')
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn ($r) => ['date' => $r->d, 'count' => (int) $r->c]);

        return response()->json(['data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'counts' => [
                'total_facilities' => Facility::count(),
                'total_doctors' => Doctor::count(),
                'total_patients' => User::whereHas('roles', fn ($q) => $q->where('slug', 'patient'))->count(),
                'appointments_today' => $bookingsToday->count(),
                'appointments_this_month' => $bookingsMonth->count(),
                'active_subscriptions' => FacilitySubscription::whereIn('status', ['active', 'trial', 'grace_period'])->count(),
                'revenue' => (float) $revenue->sum('gross_amount'),
                'commissions' => (float) $revenue->sum('commission_amount'),
                'pending_doctor_verifications' => Doctor::where('verification_status', 'pending')->count(),
                'pending_facility_verifications' => Facility::where('verification_status', 'pending')->count(),
                'cancelled_clinics' => ClinicSession::where('status', 'cancelled')->whereDate('session_date', $today)->count(),
                'problem_appointments' => Appointment::whereDate('appointment_date', $today)->whereIn('status', ['cancelled', 'no_show'])->count(),
            ],
            'daily_appointments' => $daily,
        ]]);
    }

    // ─── Analytics ────────────────────────────────────────────────────────────

    public function analytics(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 90);
        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();

        $apptQuery = Appointment::whereBetween('created_at', [$from, $to]);
        $completed = (clone $apptQuery)->where('status', 'completed');
        $cancelled = (clone $apptQuery)->where('status', 'cancelled');
        $noShows = (clone $apptQuery)->where('status', 'no_show');
        $total = $apptQuery->count();

        $revenue = Payment::paid()->whereBetween('created_at', [$from, $to]);

        $weekly = [];
        for ($w = 7; $w >= 0; $w--) {
            $ws = now()->startOfWeek()->subWeeks($w);
            $we = now()->endOfWeek()->subWeeks($w);
            $weekly[] = [
                'week' => $ws->format('M j'),
                'appointments' => Appointment::whereBetween('appointment_date', [$ws->toDateString(), $we->toDateString()])->count(),
                'revenue' => (float) Payment::paid()->whereBetween('confirmed_at', [$ws, $we])->sum('net_amount'),
            ];
        }

        $topDoctors = Appointment::where('status', 'completed')->whereBetween('created_at', [$from, $to])
            ->select('doctor_id', DB::raw('COUNT(*) as bookings'), DB::raw('COALESCE(SUM(amount_paid),0) as revenue'))
            ->groupBy('doctor_id')->with('doctor:id,display_name')->orderByDesc('bookings')->limit(8)->get()
            ->map(fn ($a) => ['doctor' => $a->doctor?->display_name, 'bookings' => (int) $a->bookings, 'revenue' => (float) $a->revenue]);

        $topFacilities = Appointment::where('status', 'completed')->whereBetween('created_at', [$from, $to])
            ->select('facility_id', DB::raw('COUNT(*) as bookings'), DB::raw('COALESCE(SUM(amount_paid),0) as revenue'))
            ->groupBy('facility_id')->with('facility:id,name')->orderByDesc('bookings')->limit(8)->get()
            ->map(fn ($a) => ['facility' => $a->facility?->name, 'bookings' => (int) $a->bookings, 'revenue' => (float) $a->revenue]);

        return response()->json(['data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'appointments' => [
                'total' => $total, 'completed' => $completed->count(), 'cancelled' => $cancelled->count(), 'no_shows' => $noShows->count(),
                'completion_rate' => $this->percent($completed->count(), $total),
                'no_show_rate' => $this->percent($noShows->count(), $total),
            ],
            'revenue' => [
                'gross' => (float) $revenue->sum('gross_amount'),
                'commission' => (float) $revenue->sum('commission_amount'),
                'net' => (float) $revenue->sum('net_amount'),
                'currency' => 'KES',
            ],
            'weekly_trend' => $weekly,
            'top_doctors' => $topDoctors,
            'top_facilities' => $topFacilities,
        ]]);
    }

    // ─── Transactions (platform ledger) ───────────────────────────────────────

    public function transactions(Request $request): JsonResponse
    {
        $request->validate(['type' => 'nullable|in:payment,settlement,payout']);
        $rows = [];

        $payments = Payment::with(['appointment.doctor', 'facility:id,name', 'doctor:id,display_name'])->orderByDesc('created_at')->limit(500)->get();
        foreach ($payments as $p) {
            $rows[] = [
                'id' => 'pay-'.$p->id,
                'type' => 'payment',
                'reference' => $p->reference,
                'description' => 'Patient payment — '.($p->appointment?->doctor?->display_name ?? $p->doctor?->display_name ?? 'Booking'),
                'direction' => 'in',
                'gross' => (float) $p->gross_amount,
                'commission' => (float) $p->commission_amount,
                'net' => (float) $p->net_amount,
                'status' => $p->status,
                'facility' => $p->facility?->name,
                'created_at' => $p->created_at?->toIso8601String(),
            ];
        }

        $settlements = Settlement::with(['doctor:id,display_name', 'facility:id,name'])->orderByDesc('created_at')->limit(500)->get();
        foreach ($settlements as $s) {
            $rows[] = [
                'id' => 'stl-'.$s->id,
                'type' => 'settlement',
                'reference' => $s->reference,
                'description' => 'Facility settlement — '.($s->doctor?->display_name ?? 'Split'),
                'direction' => 'in',
                'gross' => (float) $s->facility_amount,
                'commission' => (float) $s->platform_commission,
                'net' => (float) ($s->facility_amount - (float) $s->platform_commission),
                'status' => $s->status?->value,
                'facility' => $s->facility?->name,
                'created_at' => $s->created_at?->toIso8601String(),
            ];
        }

        $payouts = Payout::with(['doctor:id,display_name'])->orderByDesc('created_at')->limit(500)->get();
        foreach ($payouts as $p) {
            $rows[] = [
                'id' => 'payto-'.$p->id,
                'type' => 'payout',
                'reference' => $p->reference,
                'description' => 'Doctor payout — '.($p->doctor?->display_name ?? 'N/A'),
                'direction' => 'out',
                'gross' => (float) $p->amount,
                'commission' => 0.0,
                'net' => (float) $p->amount,
                'status' => $p->status,
                'facility' => null,
                'created_at' => $p->created_at?->toIso8601String(),
            ];
        }

        if ($request->filled('type')) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['type'] === $request->input('type')));
        }

        usort($rows, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        $rows = array_slice($rows, 0, 300);

        $totals = [
            'in' => collect($rows)->where('direction', 'in')->sum('net'),
            'out' => collect($rows)->where('direction', 'out')->sum('net'),
            'commission' => collect($rows)->sum('commission'),
            'count' => count($rows),
        ];

        return response()->json(['data' => $rows, 'totals' => $totals]);
    }

    // ─── Commissions ──────────────────────────────────────────────────────────

    public function commissions(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 90);
        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();

        $base = Payment::paid()->whereBetween('created_at', [$from, $to]);

        $summary = [
            'commission' => (float) $base->sum('commission_amount'),
            'gross_volume' => (float) $base->sum('gross_amount'),
            'count' => $base->count(),
            'effective_rate' => $base->sum('gross_amount') > 0
                ? round(($base->sum('commission_amount') / $base->sum('gross_amount')) * 100, 2)
                : 0.0,
        ];

        $byFacility = Payment::paid()->whereBetween('created_at', [$from, $to])
            ->select('facility_id', DB::raw('SUM(commission_amount) as commission'), DB::raw('SUM(gross_amount) as volume'), DB::raw('COUNT(*) as count'))
            ->groupBy('facility_id')->with('facility:id,name')->orderByDesc('commission')->limit(10)->get()
            ->map(fn ($r) => [
                'facility' => $r->facility?->name ?? 'Direct',
                'commission' => (float) $r->commission,
                'volume' => (float) $r->volume,
                'count' => (int) $r->count,
            ]);

        $byDoctor = Payment::paid()->whereBetween('created_at', [$from, $to])
            ->select('doctor_id', DB::raw('SUM(commission_amount) as commission'), DB::raw('SUM(gross_amount) as volume'), DB::raw('COUNT(*) as count'))
            ->groupBy('doctor_id')->with('doctor:id,display_name')->orderByDesc('commission')->limit(10)->get()
            ->map(fn ($r) => [
                'doctor' => $r->doctor?->display_name ?? 'N/A',
                'commission' => (float) $r->commission,
                'volume' => (float) $r->volume,
                'count' => (int) $r->count,
            ]);

        return response()->json(['data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'summary' => $summary,
            'by_facility' => $byFacility,
            'by_doctor' => $byDoctor,
        ]]);
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }
}
