<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EntitlementException;
use App\Http\Controllers\Controller;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\DoctorFacilityService;
use App\Services\DoctorRelationshipService;
use App\Services\FacilityAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 23 (Facility Management): the facility-admin/staff surface for doctor
 * relationships — invite, approve, decline, suspend, end, configure schedules
 * and services per doctor at THIS facility only (IDOR-isolated).
 */
class FacilityDoctorController extends Controller
{
    public function __construct(
        private DoctorRelationshipService $relationships,
        private FacilityAccessService $facilityAccess,
    ) {}

    private function facilityIds(Request $request): array
    {
        return $this->facilityAccess->managedIds($request->user()) ?? [];
    }

    private function managedRelationship(Request $request, int $id): DoctorFacility
    {
        $query = DoctorFacility::with(['doctor', 'facility'])
            ->where('id', $id);
        if ($request->user()->isSuperAdmin() || $request->user()->hasRole('platform-admin')) {
            return $query->firstOrFail();
        }

        return $query->whereIn('facility_id', $this->facilityIds($request))->firstOrFail();
    }

    /** GET /api/facility/relationships */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->hasRole('platform-admin') || $user->hasAnyRole(['facility-admin', 'facility-staff']))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = DoctorFacility::with(['doctor', 'facility', 'schedules' => fn ($q) => $q->where('is_active', true), 'services']);
        if (! $user->isSuperAdmin() && ! $user->hasRole('platform-admin')) {
            $query->whereIn('facility_id', $this->facilityIds($request));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('doctor_name')) {
            $query->whereHas('doctor', fn ($q) => $q->where('display_name', 'like', '%'.$request->doctor_name.'%'));
        }
        if ($request->boolean('active')) {
            $query->where('is_active', true);
        }

        $data = $query->orderBy('created_at', 'desc')->get()->map(fn (DoctorFacility $df) => $this->formatRelationship($df));

        return response()->json(['data' => $data]);
    }

    /** GET /api/facility/doctors/search?q=&facility_id= */
    public function searchDoctors(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->hasRole('platform-admin') || $user->hasAnyRole(['facility-admin', 'facility-staff']))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['data' => [], 'meta' => ['message' => 'Provide a name, licence or registration number.']]);
        }

        $needle = strtoupper(preg_replace('/\s+/', '', $q));
        $needleLike = '%'.$q.'%';

        $doctors = Doctor::with('user:id,name')
            ->where(function ($query) use ($needleLike, $needle) {
                $query->where('display_name', 'like', $needleLike)
                    ->orWhere('license_number', $needle)
                    ->orWhere('registry_number', $needle);
            })
            ->limit(20)
            ->get()
            ->map(fn (Doctor $d) => [
                'id' => $d->id,
                'name' => $d->display_name,
                'slug' => $d->slug,
                'avatar' => $d->avatar,
                'license_number' => $d->license_number,
                'registry_number' => $d->registry_number,
                'is_verified' => $d->is_verified,
                'current_facilities' => $d->doctorFacilities()->with('facility')->where('is_active', true)->get()->map(fn ($df) => $df->facility->name)->values(),
            ]);

        return response()->json(['data' => $doctors]);
    }

    /** POST /api/facility/doctors/{doctor}/invite */
    public function invite(Request $request, int $doctorId): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->hasRole('platform-admin') || $user->hasRole('facility-admin'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $facilityId = (int) $request->input('facility_id');
        $facility = $this->requestedFacility($request, $facilityId);
        if (! $facility) {
            return response()->json(['error' => 'You are not authorized to manage this facility.'], 403);
        }
        $doctor = Doctor::find($doctorId) ?? Doctor::where('license_number', Doctor::normalizeIdentifier($request->input('license_number')))->first();
        if (! $doctor) {
            return response()->json(['error' => 'Doctor not found. Invite by ID or licence number.'], 404);
        }

        try {
            $relationship = $this->relationships->invite($doctor, $facility, $user);

            return response()->json(['data' => $this->formatRelationship($relationship), 'message' => 'Invitation sent to the doctor.'], 201);
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'RELATIONSHIP_EXISTS'], 409);
        }
    }

    /** POST /api/facility/relationships/{id}/approve */
    public function approve(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        try {
            $updated = $this->relationships->approveRequest($relationship, $request->user(), $request->input('note'));

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Doctor joined the facility.']);
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** POST /api/facility/relationships/{id}/decline */
    public function decline(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $reason = $request->input('reason');
        if (! $reason) {
            return response()->json(['error' => 'A decline reason is required.'], 422);
        }

        try {
            $updated = $this->relationships->decline($relationship, $request->user(), $reason);

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Doctor relationship declined.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** POST /api/facility/relationships/{id}/suspend */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $reason = $request->input('reason') ?? 'Suspended by facility';

        try {
            $updated = $this->relationships->suspend($relationship, $request->user(), $reason);

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Doctor relationship suspended.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** POST /api/facility/relationships/{id}/reactivate */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);

        try {
            $updated = $this->relationships->reactivate($relationship, $request->user(), $request->input('reason'));

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Doctor relationship reactivated.']);
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** POST /api/facility/relationships/{id}/end */
    public function end(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $reason = $request->input('reason');
        if (! $reason) {
            return response()->json(['error' => 'A reason is required to end the relationship.'], 422);
        }

        try {
            $updated = $this->relationships->end($relationship, $request->user(), $reason);

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Doctor relationship ended.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** PUT /api/facility/relationships/{id}/settings */
    public function updateSettings(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);

        try {
            $updated = $this->relationships->updateSettings($relationship, $request->user(), $request->only(['consultation_fee', 'accepts_appointments', 'notes']));

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Relationship settings updated.']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** GET /api/facility/relationships/{id}/schedules */
    public function schedules(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);

        return response()->json(['data' => $relationship->schedules()->orderBy('day_of_week')->orderBy('start_time')->get()->map(fn ($s) => $this->formatSchedule($s))]);
    }

    /** POST /api/facility/relationships/{id}/schedules */
    public function storeSchedule(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $validated = $request->validate([
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'slot_duration_minutes' => 'nullable|integer|min:10|max:120',
            'max_appointments' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
        ]);

        try {
            $schedule = $this->relationships->storeSchedule($relationship, $request->user(), $validated);

            return response()->json(['data' => $this->formatSchedule($schedule), 'message' => 'Schedule created.'], 201);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'SCHEDULE_CONFLICT'], 422);
        }
    }

    /** PUT /api/facility/relationships/{id}/schedules/{scheduleId} */
    public function updateSchedule(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $schedule = DoctorFacilitySchedule::findOrFail($scheduleId);
        $validated = $request->validate([
            'day_of_week' => 'sometimes|integer|between:0,6',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'slot_duration_minutes' => 'sometimes|integer|min:10|max:120',
            'max_appointments' => 'sometimes|nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'effective_from' => 'sometimes|nullable|date',
            'effective_until' => 'sometimes|nullable|date|after_or_equal:effective_from',
        ]);

        try {
            $updated = $this->relationships->updateSchedule($relationship, $schedule, $request->user(), $validated);

            return response()->json(['data' => $this->formatSchedule($updated), 'message' => 'Schedule updated.']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'SCHEDULE_CONFLICT'], 422);
        }
    }

    /** DELETE /api/facility/relationships/{id}/schedules/{scheduleId} */
    public function deleteSchedule(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $schedule = DoctorFacilitySchedule::findOrFail($scheduleId);

        try {
            $this->relationships->deleteSchedule($relationship, $schedule, $request->user());

            return response()->json(['message' => 'Schedule removed.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** GET /api/facility/relationships/{id}/services */
    public function services(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);

        return response()->json(['data' => $relationship->services()->orderBy('is_active', 'desc')->orderBy('id')->get()->map(fn ($s) => $this->formatService($s))]);
    }

    /** POST /api/facility/relationships/{id}/services */
    public function storeService(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $validated = $request->validate([
            'service_name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $service = $this->relationships->addService($relationship, $request->user(), $validated);

            return response()->json(['data' => $this->formatService($service), 'message' => 'Service added.'], 201);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'SERVICE_EXISTS'], 422);
        }
    }

    /** PUT /api/facility/relationships/{id}/services/{serviceId} */
    public function updateService(Request $request, int $id, int $serviceId): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $service = DoctorFacilityService::findOrFail($serviceId);
        $validated = $request->validate([
            'service_name' => 'sometimes|string|max:120',
            'price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:5|max:480',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $updated = $this->relationships->updateService($relationship, $service, $request->user(), $validated);

            return response()->json(['data' => $this->formatService($updated), 'message' => 'Service updated.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** DELETE /api/facility/relationships/{id}/services/{serviceId} */
    public function deleteService(Request $request, int $id, int $serviceId): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $service = DoctorFacilityService::findOrFail($serviceId);

        try {
            $this->relationships->deleteService($relationship, $service, $request->user());

            return response()->json(['message' => 'Service removed.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** GET /api/facility/relationships/{id}/calendar?from=&to= */
    public function calendar(Request $request, int $id): JsonResponse
    {
        $relationship = $this->managedRelationship($request, $id);
        $from = $request->filled('from') ? Carbon::parse($request->from)->toDateString() : today()->toDateString();
        $to = $request->filled('to') ? Carbon::parse($request->to)->toDateString() : today()->addDays(30)->toDateString();

        $sessions = ClinicSession::where('doctor_id', $relationship->doctor_id)
            ->whereBetween('session_date', [$from, $to])
            ->with(['facility'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => [
                'doctor' => ['id' => $relationship->doctor_id, 'name' => $relationship->doctor?->display_name],
                'sessions' => $sessions->map(fn ($s) => [
                    'id' => $s->id,
                    'facility' => ['id' => $s->facility_id, 'name' => $s->facility?->name],
                    'session_date' => $s->session_date->format('Y-m-d'),
                    'start_time' => substr($s->start_time, 0, 5),
                    'end_time' => substr($s->end_time, 0, 5),
                    'status' => $s->status,
                    'is_confirmed' => $s->is_confirmed,
                    'is_this_facility' => $s->facility_id === $relationship->facility_id,
                ])->values(),
            ],
        ]);
    }

    private function requestedFacility(Request $request, int $facilityId)
    {
        return $this->facilityAccess->resolveExplicit($request, $facilityId);
    }

    private function formatRelationship(DoctorFacility $df): array
    {
        return [
            'id' => $df->id,
            'doctor' => $df->doctor ? ['id' => $df->doctor->id, 'name' => $df->doctor->display_name, 'slug' => $df->doctor->slug, 'avatar' => $df->doctor->avatar, 'license_number' => $df->doctor->license_number, 'registry_number' => $df->doctor->registry_number, 'is_verified' => $df->doctor->is_verified] : null,
            'facility' => $df->facility ? ['id' => $df->facility->id, 'name' => $df->facility->name] : null,
            'status' => $df->status?->value,
            'status_label' => $df->statusLabel(),
            'is_active' => $df->is_active,
            'is_authoritative' => $df->isAuthoritative(),
            'consultation_fee' => $df->consultation_fee,
            'accepts_appointments' => $df->accepts_appointments,
            'requested_by' => $df->requested_by ? 'doctor' : 'facility',
            'started_at' => $df->started_at?->toIso8601String(),
            'ended_at' => $df->ended_at?->toIso8601String(),
            'decline_reason' => $df->decline_reason,
            'notes_governance' => $df->notes_governance,
            'schedules' => $df->relationLoaded('schedules') ? $df->schedules->map(fn ($s) => $this->formatSchedule($s))->values() : null,
            'services' => $df->relationLoaded('services') ? $df->services->map(fn ($s) => $this->formatService($s))->values() : null,
        ];
    }

    private function formatSchedule(DoctorFacilitySchedule $s): array
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return [
            'id' => $s->id,
            'doctor_facility_id' => $s->doctor_facility_id,
            'day_of_week' => $s->day_of_week,
            'day_name' => $days[$s->day_of_week] ?? 'Unknown',
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'slot_duration_minutes' => $s->slot_duration_minutes,
            'max_appointments' => $s->max_appointments,
            'is_active' => $s->is_active,
            'effective_from' => $s->effective_from?->toDateString(),
            'effective_until' => $s->effective_until?->toDateString(),
        ];
    }

    private function formatService(DoctorFacilityService $s): array
    {
        return [
            'id' => $s->id,
            'doctor_facility_id' => $s->doctor_facility_id,
            'service_name' => $s->service_name,
            'price' => $s->price,
            'duration_minutes' => $s->duration_minutes,
            'is_active' => $s->is_active,
        ];
    }
}
