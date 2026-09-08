<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EntitlementException;
use App\Http\Controllers\Controller;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\DoctorFacilityService;
use App\Models\Facility;
use App\Services\DoctorRelationshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 23 (Unified Doctor Identity): the doctor's own relationship surface —
 * view facilities, accept/decline invitations, join a facility, end a
 * relationship, and manage their schedules/services at each facility.
 */
class DoctorRelationshipController extends Controller
{
    public function __construct(private DoctorRelationshipService $relationships) {}

    private function ownedRelationship(Request $request, int $id): DoctorFacility
    {
        $user = $request->user();
        $query = DoctorFacility::with(['facility', 'doctor'])->where('id', $id);
        if (! $user->isSuperAdmin() && ! $user->hasRole('platform-admin')) {
            $query->where('doctor_id', $user->doctor?->id ?? -1);
        }

        return $query->firstOrFail();
    }

    /** GET /api/doctor/relationships */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('platform-admin')) {
            $query = DoctorFacility::with(['facility', 'doctor']);
        } else {
            $doctor = $user->doctor;
            if (! $doctor) {
                return response()->json(['error' => 'Doctor profile not found'], 404);
            }
            $query = DoctorFacility::with(['facility', 'doctor'])->where('doctor_id', $doctor->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $data = $query->orderBy('created_at', 'desc')->get()->map(fn ($df) => $this->formatRelationship($df));

        return response()->json(['data' => $data]);
    }

    /** POST /api/doctor/relationships/join {facility_id, note?} */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();
        $doctor = $user->doctor;
        if (! $doctor && ! $user->isSuperAdmin()) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }
        $facilityId = (int) $request->input('facility_id');
        if (! $facilityId) {
            return response()->json(['error' => 'facility_id is required.'], 422);
        }
        $facility = Facility::find($facilityId);
        if (! $facility) {
            return response()->json(['error' => 'Facility not found.'], 404);
        }

        try {
            $relationship = $this->relationships->requestToJoin($doctor, $facility, $user);

            return response()->json(['data' => $this->formatRelationship($relationship), 'message' => 'Join request sent to the facility.'], 201);
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'RELATIONSHIP_EXISTS'], 409);
        }
    }

    /** POST /api/doctor/relationships/{id}/accept */
    public function accept(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);

        try {
            $updated = $this->relationships->acceptInvite($relationship, $request->user(), $request->input('note'));

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Invitation accepted. Welcome aboard!']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** POST /api/doctor/relationships/{id}/decline */
    public function decline(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
        $reason = $request->input('reason');
        if (! $reason) {
            return response()->json(['error' => 'A decline reason is required.'], 422);
        }

        try {
            $updated = $this->relationships->decline($relationship, $request->user(), $reason);

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Invitation declined.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** POST /api/doctor/relationships/{id}/end */
    public function end(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
        $reason = $request->input('reason');
        if (! $reason) {
            return response()->json(['error' => 'A reason is required to end the relationship.'], 422);
        }

        try {
            $updated = $this->relationships->end($relationship, $request->user(), $reason);

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Relationship ended.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'BAD_TRANSITION'], 422);
        }
    }

    /** PUT /api/doctor/relationships/{id}/settings */
    public function updateSettings(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);

        try {
            $updated = $this->relationships->updateSettings($relationship, $request->user(), $request->only(['consultation_fee', 'accepts_appointments', 'notes']));

            return response()->json(['data' => $this->formatRelationship($updated), 'message' => 'Settings updated.']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** GET /api/doctor/relationships/{id}/schedules */
    public function schedules(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);

        return response()->json(['data' => $relationship->schedules()->orderBy('day_of_week')->orderBy('start_time')->get()->map(fn ($s) => $this->formatSchedule($s))]);
    }

    /** POST /api/doctor/relationships/{id}/schedules */
    public function storeSchedule(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
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

    /** PUT /api/doctor/relationships/{id}/schedules/{scheduleId} */
    public function updateSchedule(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
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

    /** DELETE /api/doctor/relationships/{id}/schedules/{scheduleId} */
    public function deleteSchedule(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
        $schedule = DoctorFacilitySchedule::findOrFail($scheduleId);

        try {
            $this->relationships->deleteSchedule($relationship, $schedule, $request->user());

            return response()->json(['message' => 'Schedule removed.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** GET /api/doctor/relationships/{id}/services */
    public function services(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);

        return response()->json(['data' => $relationship->services()->orderBy('is_active', 'desc')->orderBy('id')->get()->map(fn ($s) => $this->formatService($s))]);
    }

    /** POST /api/doctor/relationships/{id}/services */
    public function storeService(Request $request, int $id): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
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

    /** PUT /api/doctor/relationships/{id}/services/{serviceId} */
    public function updateService(Request $request, int $id, int $serviceId): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
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

    /** DELETE /api/doctor/relationships/{id}/services/{serviceId} */
    public function deleteService(Request $request, int $id, int $serviceId): JsonResponse
    {
        $relationship = $this->ownedRelationship($request, $id);
        $service = DoctorFacilityService::findOrFail($serviceId);

        try {
            $this->relationships->deleteService($relationship, $service, $request->user());

            return response()->json(['message' => 'Service removed.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function formatRelationship(DoctorFacility $df): array
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return [
            'id' => $df->id,
            'doctor' => $df->doctor ? ['id' => $df->doctor->id, 'name' => $df->doctor->display_name, 'slug' => $df->doctor->slug] : null,
            'facility' => $df->facility ? ['id' => $df->facility->id, 'name' => $df->facility->name, 'city' => $df->facility->city, 'county' => $df->facility->county?->name, 'type' => $df->facility->type] : null,
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
            'invited_by' => $df->invited_by ? 'facility' : null,
            'schedules' => $df->relationLoaded('schedules') ? $df->schedules->map(fn ($s) => $this->formatSchedule($s))->values() : null,
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
