<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\DoctorFacilityService;
use App\Models\Facility;
use App\Models\FacilityOperatingHour;
use App\Models\FacilitySetting;
use App\Models\FacilitySubscription;
use App\Models\Payment;
use App\Models\Settlement;
use App\Services\FacilityAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 28: expanded facility workspace surfaces.
 *
 * All methods funnel through FacilityAccessService->authorizedFacility() so the
 * golden scope rule holds regardless of which facility the actor addresses.
 */
class FacilityWorkspaceExtensionsController extends Controller
{
    public function __construct(
        private FacilityAccessService $facilityAccess,
    ) {}

    // ─── Operating Hours ──────────────────────────────────────────────────────

    public function operatingHours(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $hours = $facility->operatingHours()->orderBy('day_of_week')->get();

        return response()->json(['data' => $hours->map(fn ($h) => $this->formatHour($h))]);
    }

    public function saveOperatingHours(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $v = Validator::make($request->all(), ['hours' => 'required|array|size:7', 'hours.*.day_of_week' => 'required|integer|between:0,6', 'hours.*.status' => 'required|in:open,closed', 'hours.*.open_time' => 'nullable|date_format:H:i', 'hours.*.close_time' => 'nullable|date_format:H:i']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $facility->operatingHours()->delete();
        foreach ($v->validated()['hours'] as $row) {
            FacilityOperatingHour::create([
                'facility_id' => $facility->id,
                'day_of_week' => $row['day_of_week'],
                'status' => $row['status'],
                'open_time' => $row['status'] === 'open' ? $row['open_time'] : null,
                'close_time' => $row['status'] === 'open' ? $row['close_time'] : null,
            ]);
        }

        return response()->json(['data' => $facility->operatingHours()->orderBy('day_of_week')->get()->map(fn ($h) => $this->formatHour($h))]);
    }

    // ─── Schedules (doctor weekly patterns at this facility) ──────────────────

    public function schedules(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $schedules = DoctorFacilitySchedule::with(['doctorFacility.doctor'])
            ->whereHas('doctorFacility', fn ($q) => $q->where('facility_id', $facility->id))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $schedules->map(fn ($s) => [
            'id' => $s->id,
            'doctor' => $s->doctorFacility?->doctor ? ['id' => $s->doctorFacility->doctor->id, 'name' => $s->doctorFacility->doctor->display_name, 'slug' => $s->doctorFacility->doctor->slug] : null,
            'day_of_week' => $s->day_of_week,
            'day_name' => FacilityOperatingHour::DAYS[$s->day_of_week] ?? 'Unknown',
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'slot_duration_minutes' => $s->slot_duration_minutes,
            'max_appointments' => $s->max_appointments,
            'is_active' => (bool) $s->is_active,
        ])]);
    }

    // ─── Services (doctor × facility priced services, daily view) ─────────────

    public function services(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $services = DoctorFacilityService::with(['doctorFacility.doctor'])
            ->whereHas('doctorFacility', fn ($q) => $q->where('facility_id', $facility->id))
            ->orderBy('service_name')
            ->get();

        return response()->json(['data' => $services->map(fn ($svc) => $this->formatService($svc))]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $v = Validator::make($request->all(), ['doctor_id' => 'required|integer', 'service_name' => 'required|string|max:255', 'price' => 'required|numeric|min:0', 'duration_minutes' => 'nullable|integer|min:5', 'is_active' => 'nullable|boolean']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $relationship = DoctorFacility::where('facility_id', $facility->id)->where('doctor_id', $v->validated()['doctor_id'])->where('is_active', true)->first();
        if (! $relationship) {
            return response()->json(['error' => 'Active doctor relationship not found at this facility'], 422);
        }

        $service = DoctorFacilityService::create([
            'doctor_facility_id' => $relationship->id,
            'service_name' => $v->validated()['service_name'],
            'price' => $v->validated()['price'],
            'duration_minutes' => $v->validated()['duration_minutes'] ?? 30,
            'is_active' => $v->validated()['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->formatService($service->fresh(['doctorFacility.doctor']))], 201);
    }

    public function updateService(Request $request, int $id): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $service = DoctorFacilityService::whereHas('doctorFacility', fn ($q) => $q->where('facility_id', $facility->id))->find($id);
        if (! $service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $v = Validator::make($request->all(), ['service_name' => 'sometimes|string|max:255', 'price' => 'sometimes|numeric|min:0', 'duration_minutes' => 'sometimes|integer|min:5', 'is_active' => 'sometimes|boolean']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $service->update($v->validated());

        return response()->json(['data' => $this->formatService($service->fresh(['doctorFacility.doctor']))]);
    }

    public function destroyService(Request $request, int $id): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $service = DoctorFacilityService::whereHas('doctorFacility', fn ($q) => $q->where('facility_id', $facility->id))->find($id);
        if (! $service) {
            return response()->json(['error' => 'Service not found'], 404);
        }
        if ($service->bookings()->exists()) {
            return response()->json(['error' => 'Service has bookings and cannot be deleted; deactivate it instead.'], 422);
        }
        $service->delete();

        return response()->json(['message' => 'Service deleted.']);
    }

    // ─── Payments (money received by the facility) ────────────────────────────

    public function payments(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $q = Payment::with(['appointment.doctor', 'doctor'])->where('facility_id', $facility->id)->where('recipient_type', Payment::RECIPIENT_FACILITY);

        $this->applyDateFilter($q, $request);

        $payments = $q->orderByDesc('created_at')->limit(200)->get();

        return response()->json(['data' => $payments->map(fn ($p) => $this->formatPayment($p))]);
    }

    // ─── Transactions (all facility financial activity) ───────────────────────

    public function transactions(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $rows = [];

        $payments = Payment::with(['appointment.doctor'])->where('facility_id', $facility->id)->where('recipient_type', Payment::RECIPIENT_FACILITY)->orderByDesc('created_at')->get();
        foreach ($payments as $p) {
            $rows[] = [
                'id' => 'pay-'.$p->id,
                'type' => 'payment',
                'reference' => $p->reference,
                'description' => 'Appointment payment — '.($p->appointment?->doctor?->display_name ?? 'Booking'),
                'direction' => 'in',
                'gross' => $p->gross_amount,
                'fee' => $p->commission_amount,
                'net' => $p->net_amount,
                'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(),
            ];
        }

        $settlements = Settlement::with(['doctor'])->where('facility_id', $facility->id)->where('status', '!=', 'pending')->orderByDesc('created_at')->get();
        foreach ($settlements as $s) {
            $rows[] = [
                'id' => 'stl-'.$s->id,
                'type' => 'settlement',
                'reference' => $s->reference,
                'description' => 'Facility settlement — '.($s->doctor?->display_name ?? 'Split'),
                'direction' => 'in',
                'gross' => $s->facility_amount,
                'fee' => $s->platform_commission,
                'net' => $s->facility_amount,
                'status' => $s->status->value,
                'created_at' => $s->created_at?->toIso8601String(),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $rows = array_slice($rows, 0, 200);

        $totals = [
            'in' => collect($rows)->where('direction', 'in')->sum('net'),
            'count' => count($rows),
        ];

        return response()->json(['data' => $rows, 'totals' => $totals]);
    }

    // ─── Reports (analytics + export) ─────────────────────────────────────────

    public function reports(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $days = (int) $request->query('days', 30);

        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();

        $completed = Appointment::where('facility_id', $facility->id)->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to]);
        $cancelled = Appointment::where('facility_id', $facility->id)->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to]);
        $noShows = Appointment::where('facility_id', $facility->id)->where('status', 'no_show')
            ->whereBetween('created_at', [$from, $to]);

        $revenue = Payment::where('facility_id', $facility->id)->where('recipient_type', Payment::RECIPIENT_FACILITY)->where('status', Payment::STATUS_PAID)
            ->whereBetween('created_at', [$from, $to]);

        $doctorStats = Appointment::where('facility_id', $facility->id)->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->select('doctor_id', DB::raw('COUNT(*) as bookings'), DB::raw('COALESCE(SUM(amount_paid),0) as revenue'))
            ->groupBy('doctor_id')->with('doctor')
            ->orderByDesc('bookings')->limit(10)->get()
            ->map(fn ($a) => ['doctor' => $a->doctor?->display_name, 'bookings' => (int) $a->bookings, 'revenue' => (float) $a->revenue]);

        $daily = Appointment::where('facility_id', $facility->id)->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn ($r) => ['date' => $r->d, 'count' => (int) $r->c]);

        return response()->json(['data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'appointments' => [
                'completed' => $completed->count(),
                'cancelled' => $cancelled->count(),
                'no_shows' => $noShows->count(),
                'total' => $completed->count() + $cancelled->count() + $noShows->count(),
                'completion_rate' => $this->percent($completed->count(), $completed->count() + $cancelled->count() + $noShows->count()),
            ],
            'revenue' => [
                'gross' => (float) $revenue->sum('gross_amount'),
                'commission' => (float) $revenue->sum('commission_amount'),
                'net' => (float) $revenue->sum('net_amount'),
                'count' => $revenue->count(),
            ],
            'by_doctor' => $doctorStats,
            'daily' => $daily,
        ]]);
    }

    public function exportReport(Request $request): Response
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $payments = Payment::with(['appointment.doctor', 'doctor'])->where('facility_id', $facility->id)->where('recipient_type', Payment::RECIPIENT_FACILITY)->orderByDesc('created_at')->get();

        $lines = ['Reference,Doctor,Amount,Commission,Net,Status,Date'];
        foreach ($payments as $p) {
            $lines[] = implode(',', [
                '"'.$p->reference.'"',
                '"'.($p->appointment?->doctor?->display_name ?? $p->doctor?->display_name ?? 'N/A').'"',
                $p->gross_amount, $p->commission_amount, $p->net_amount,
                '"'.$p->status.'"',
                '"'.($p->created_at?->format('Y-m-d H:i') ?? '').'"',
            ]);
        }
        $csv = implode("\n", $lines);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="facility-payments-'.now()->format('Ymd').'.csv"',
        ]);
    }

    // ─── Billing (subscription charges + statement events) ────────────────────

    public function billing(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $subscription = FacilitySubscription::where('facility_id', $facility->id)->with('plan')->latest('id')->first();
        $events = $subscription?->events()->latest()->limit(30)->get() ?? collect();

        return response()->json(['data' => [
            'subscription' => $subscription ? ['id' => $subscription->id, 'status' => $subscription->status?->value, 'plan_name' => $subscription->plan?->name ?? ($subscription->plan_snapshot['name'] ?? 'N/A'), 'billing_cycle' => $subscription->billing_cycle?->value, 'monthly_price' => (float) $subscription->monthly_price, 'annual_price' => (float) $subscription->annual_price, 'effective_amount' => (float) $subscription->effective_amount, 'last_amount_paid' => (float) $subscription->last_amount_paid, 'payment_reference' => $subscription->payment_reference, 'next_billing_date' => $subscription->next_billing_date?->toDateString(), 'last_billing_date' => $subscription->last_billing_date?->toDateString(), 'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end] : null,
            'events' => $events->map(fn ($e) => ['id' => $e->id, 'event' => $e->event, 'reason' => $e->reason, 'from_status' => $e->from_status, 'to_status' => $e->to_status, 'payload' => $e->payload, 'created_at' => $e->created_at?->toIso8601String()])->values(),
        ]]);
    }

    // ─── Settings ─────────────────────────────────────────────────────────────

    public function settings(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        return response()->json(['data' => $facility->settings()->pluck('value', 'key')->toArray()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $allowed = [FacilitySetting::KEY_CURRENCY, FacilitySetting::KEY_SLOT_DURATION, FacilitySetting::KEY_BOOKING_NOTICE, FacilitySetting::KEY_CANCEL_HORIZON, FacilitySetting::KEY_INSTANT_BOOKING, FacilitySetting::KEY_TIMEZONE];
        $data = array_intersect_key($request->all(), array_flip($allowed));

        foreach ($data as $key => $value) {
            FacilitySetting::updateOrCreate(['facility_id' => $facility->id, 'key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
        }

        return response()->json(['data' => $facility->settings()->pluck('value', 'key')->toArray()]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function applyDateFilter($query, Request $request): void
    {
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from)->toDateString());
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to)->toDateString());
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    private function formatHour(FacilityOperatingHour $h): array
    {
        return ['id' => $h->id, 'day_of_week' => $h->day_of_week, 'day_name' => $h->dayName(), 'status' => $h->status, 'open_time' => $h->open_time ? substr($h->open_time, 0, 5) : null, 'close_time' => $h->close_time ? substr($h->close_time, 0, 5) : null];
    }

    private function formatService(DoctorFacilityService $svc): array
    {
        return ['id' => $svc->id, 'doctor' => $svc->doctorFacility?->doctor ? ['id' => $svc->doctorFacility->doctor->id, 'name' => $svc->doctorFacility->doctor->display_name, 'slug' => $svc->doctorFacility->doctor->slug] : null, 'service_name' => $svc->service_name, 'price' => (float) $svc->price, 'duration_minutes' => $svc->duration_minutes, 'is_active' => (bool) $svc->is_active];
    }

    private function formatPayment(Payment $p): array
    {
        return ['id' => $p->id, 'reference' => $p->reference, 'doctor' => ($p->appointment?->doctor?->display_name ?? $p->doctor?->display_name) ?: 'Booking', 'gross_amount' => (float) $p->gross_amount, 'commission_amount' => (float) $p->commission_amount, 'net_amount' => (float) $p->net_amount, 'status' => $p->status, 'method' => $p->method, 'created_at' => $p->created_at?->toIso8601String()];
    }

    private function percent(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
