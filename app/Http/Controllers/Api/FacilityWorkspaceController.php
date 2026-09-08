<?php

namespace App\Http\Controllers\Api;

use App\Enums\VerificationRequestStatus;
use App\Enums\VerificationStatus;
use App\Events\ClinicSessionCancelled;
use App\Events\ClinicSessionConfirmed;
use App\Exceptions\EntitlementException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSession;
use App\Models\DoctorFacility;
use App\Models\Facility;
use App\Models\FacilityLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\AppointmentService;
use App\Services\EntitlementService;
use App\Services\FacilityAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FacilityWorkspaceController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
        private EntitlementService $entitlements,
        private FacilityAccessService $facilityAccess,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $today = Carbon::today();
        $todays = ClinicSession::with(['doctor.specialties', 'facilityLocation'])->where('facility_id', $facility->id)->whereDate('session_date', $today)->orderBy('start_time')->get();
        $tomorrow = ClinicSession::with(['doctor', 'facilityLocation'])->where('facility_id', $facility->id)->whereDate('session_date', $today->copy()->addDay())->orderBy('start_time')->get();
        $pending = ClinicSession::with(['doctor'])->where('facility_id', $facility->id)->where('status', 'pending')->whereDate('session_date', '>=', $today)->orderBy('session_date')->orderBy('start_time')->limit(20)->get();
        $todayAppointments = Appointment::with(['doctor', 'clinicSession'])->where('facility_id', $facility->id)->whereDate('appointment_date', $today)->orderBy('start_time')->get();

        return response()->json(['data' => [
            'facility' => $this->formatFacility($facility),
            'today' => ['date' => $today->toDateString(), 'day' => $today->format('l, F j, Y'), 'clinics' => $todays->map(fn ($s) => $this->formatSessionForFacility($s)), 'appointment_count' => $todayAppointments->count(), 'confirmed_clinics' => $todays->where('is_confirmed', true)->count(), 'pending_confirmations' => $todays->where('status', 'pending')->count()],
            'tomorrow' => ['date' => $today->copy()->addDay()->toDateString(), 'clinics' => $tomorrow->map(fn ($s) => $this->formatSessionForFacility($s))],
            'pending_confirmations' => $pending->map(fn ($s) => $this->formatSessionForFacility($s)),
            'appointments_today' => $todayAppointments->map(fn ($a) => $this->formatAppointmentForFacility($a)),
        ]]);
    }

    public function facilities(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['facility-admin', 'facility-staff', 'platform-admin']) && ! $user->isSuperAdmin()) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $facilities = $user->facilities()->withPivot('is_primary')->orderBy('name')->get(['facilities.id', 'facilities.name', 'facilities.city', 'facilities.type']);

        return response()->json(['data' => $facilities->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'city' => $f->city,
            'type' => $f->type,
            'is_primary' => (bool) $f->pivot->is_primary,
        ])]);
    }

    public function doctors(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $doctorFacilities = DoctorFacility::with(['doctor.specialties'])->where('facility_id', $facility->id)->where('is_active', true)->get();
        $today = Carbon::today();

        return response()->json(['data' => $doctorFacilities->map(function ($df) use ($today, $facility) {
            $next = ClinicSession::where('doctor_id', $df->doctor_id)->where('facility_id', $facility->id)->whereDate('session_date', '>=', $today)->orderBy('session_date')->orderBy('start_time')->first();

            return ['id' => $df->doctor_id, 'pivot_id' => $df->id, 'name' => $df->doctor?->display_name, 'slug' => $df->doctor?->slug, 'avatar' => $df->doctor?->avatar, 'is_verified' => $df->doctor?->is_verified, 'specialties' => $df->doctor?->specialties?->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'is_primary' => (bool) $s->pivot->is_primary]) ?? [], 'consultation_fee' => $df->consultation_fee, 'accepts_appointments' => (bool) $df->accepts_appointments, 'relationship_active' => (bool) $df->is_active, 'next_clinic' => $next ? ['id' => $next->id, 'date' => $next->session_date->format('Y-m-d'), 'day' => $next->session_date->format('l'), 'start_time' => substr($next->start_time, 0, 5), 'end_time' => substr($next->end_time, 0, 5), 'status' => $next->status, 'is_confirmed' => $next->is_confirmed] : null];
        })]);
    }

    public function clinicSessions(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $filter = $request->query('filter', 'upcoming');
        $status = $request->query('status');
        $today = Carbon::today();
        $q = ClinicSession::with(['doctor.specialties', 'facilityLocation'])->where('facility_id', $facility->id);
        if ($filter === 'today') {
            $q->whereDate('session_date', $today);
        } elseif ($filter === 'upcoming') {
            $q->whereDate('session_date', '>=', $today);
        } elseif ($filter === 'past') {
            $q->whereDate('session_date', '<', $today);
        }
        if ($status) {
            $q->where('status', $status);
        }

        return response()->json(['data' => $q->orderBy('session_date')->orderBy('start_time')->limit(100)->get()->map(fn ($s) => $this->formatSessionForFacility($s))]);
    }

    public function confirmSession(Request $request, int $id): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $session = ClinicSession::where('facility_id', $facility->id)->find($id);
        if (! $session) {
            return response()->json(['error' => 'Clinic session not found'], 404);
        }
        if ($session->status === 'cancelled') {
            return response()->json(['error' => 'Cannot confirm a cancelled session'], 422);
        }
        $wasConfirmed = $session->status === 'confirmed';
        $wasPending = $session->status === 'pending';
        $session->update(['facility_confirmation' => 'confirmed', 'facility_confirmed_at' => now()]);
        $newStatus = ($session->doctor_confirmation === 'confirmed') ? 'confirmed' : 'pending';
        $session->update(['status' => $newStatus]);
        $fresh = $session->fresh(['doctor', 'facilityLocation']);
        // Phase 14: Fire the event when the session transitions to confirmed.
        if (! $wasConfirmed && $fresh->status === 'confirmed') {
            ClinicSessionConfirmed::dispatch($fresh, $wasPending);
        }

        return response()->json(['data' => $this->formatSessionForFacility($fresh)]);
    }

    public function rejectSession(Request $request, int $id): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $v = Validator::make($request->all(), ['reason' => 'required|string|max:500']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $session = ClinicSession::where('facility_id', $facility->id)->find($id);
        if (! $session) {
            return response()->json(['error' => 'Clinic session not found'], 404);
        }
        $wasCancelled = $session->status === 'cancelled';
        $reason = $request->reason;
        $session->update(['facility_confirmation' => 'declined', 'facility_confirmed_at' => now(), 'cancellation_reason' => $reason]);
        if ($session->doctor_confirmation !== 'confirmed') {
            $session->update(['status' => 'cancelled', 'cancelled_by' => $request->user()->id, 'cancelled_at' => now()]);
            // Phase 17: delegated to AppointmentService — actor recorded, slots
            // released, patients notified per appointment. History preserved.
            $this->appointments->cancelSessionAppointments($session, $request->user(), $reason);
        }
        $fresh = $session->fresh(['doctor', 'facilityLocation']);
        // Phase 14: Fire the event when the session becomes cancelled.
        if (! $wasCancelled && $fresh->status === 'cancelled') {
            ClinicSessionCancelled::dispatch($fresh, $reason);
        }

        return response()->json(['data' => $this->formatSessionForFacility($fresh)]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $q = Appointment::with(['doctor.specialties', 'clinicSession'])->where('facility_id', $facility->id)->whereDate('appointment_date', $date);
        if ($request->query('status')) {
            $q->where('status', $request->query('status'));
        }
        if ($request->query('doctor_id')) {
            $q->where('doctor_id', $request->query('doctor_id'));
        }
        $appointments = $q->orderBy('start_time')->limit(200)->get();
        $doctors = DoctorFacility::with('doctor')->where('facility_id', $facility->id)->where('is_active', true)->get()->map(fn ($df) => ['id' => $df->doctor_id, 'name' => $df->doctor?->display_name]);

        return response()->json(['data' => $appointments->map(fn ($a) => $this->formatAppointmentForFacility($a)), 'doctors' => $doctors, 'meta' => ['date' => $date->toDateString(), 'total' => $appointments->count()]]);
    }

    public function showAppointment(Request $request, int $id): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $appointment = Appointment::with(['doctor.specialties', 'clinicSession.facilityLocation', 'user'])->where('facility_id', $facility->id)->find($id);
        if (! $appointment) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        return response()->json(['data' => $this->formatAppointmentForFacility($appointment, true)]);
    }

    public function locations(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $locations = $facility->allLocations()->with('county')->orderByDesc('is_primary')->get();

        return response()->json(['data' => $locations->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'address' => $l->address, 'city' => $l->city, 'county' => $l->county?->name, 'phone' => $l->phone, 'email' => $l->email, 'is_primary' => (bool) $l->is_primary, 'is_active' => (bool) $l->is_active])]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        try {
            $this->entitlements->assertCanAddLocation($facility);
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        }
        $v = Validator::make($request->all(), ['name' => 'required|string|max:255', 'address' => 'nullable|string|max:255', 'city' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:20', 'email' => 'nullable|email', 'is_primary' => 'boolean']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $data['facility_id'] = $facility->id;
        $data['is_primary'] = $data['is_primary'] ?? false;
        $data['is_active'] = true;
        if ($data['is_primary']) {
            FacilityLocation::where('facility_id', $facility->id)->update(['is_primary' => false]);
        }
        $location = FacilityLocation::create($data);

        return response()->json(['data' => $location], 201);
    }

    public function staff(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $staff = User::whereHas('facilities', fn ($q) => $q->where('facilities.id', $facility->id))->with('roles', 'facilities')->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'is_active' => (bool) $u->is_active, 'roles' => $u->roles->pluck('name'), 'is_primary' => (bool) $u->facilities->where('id', $facility->id)->first()?->pivot?->is_primary, 'authorized_facility_count' => $u->facilities->count()]);

        return response()->json(['data' => $staff]);
    }

    /**
     * POST /api/facility/staff — attach an existing user to a facility as staff.
     * Phase 24: enforces the facility plan's staff-seat limit.
     */
    public function storeStaff(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        try {
            $this->entitlements->assertCanAddStaff($facility);
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        }

        $v = Validator::make($request->all(), ['email' => 'required|email|exists:users,email', 'role' => 'sometimes|string|in:facility-staff,facility-admin']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $user = User::where('email', $data['email'])->firstOrFail();
        $roleSlug = $data['role'] ?? 'facility-staff';

        if ($facility->admins()->where('users.id', $user->id)->exists() || $user->facilities()->where('facilities.id', $facility->id)->exists()) {
            return response()->json(['error' => 'This user is already attached to the facility.'], 409);
        }

        $facility->admins()->attach($user->id, ['is_primary' => false]);
        $role = Role::where('slug', $roleSlug)->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching($role->id);
        }

        AuditLog::record(
            $request->user()->id,
            'facility.staff_added',
            User::class,
            $user->id,
            $user->name.' @ '.$facility->name,
            null,
            ['facility_id' => $facility->id, 'role' => $roleSlug],
        );

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_active' => (bool) $user->is_active, 'role' => $roleSlug]], 201);
    }

    /**
     * PATCH /api/facility/staff/{user} - update a staff member's facility role or status.
     */
    public function updateStaff(Request $request, User $user): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $attached = $facility->admins()->where('users.id', $user->id)->first();
        if (! $attached) {
            return response()->json(['error' => 'User is not attached to this facility.'], 404);
        }

        if ($attached->pivot->is_primary) {
            return response()->json(['error' => 'The primary facility admin cannot be modified.'], 422);
        }

        $v = Validator::make($request->all(), [
            'role' => 'sometimes|string|in:facility-staff,facility-admin',
            'is_active' => 'sometimes|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();

        if (isset($data['role'])) {
            $detach = Role::whereIn('slug', ['facility-staff', 'facility-admin'])->pluck('id');
            $user->roles()->detach($detach);
            $role = Role::where('slug', $data['role'])->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        }
        if (array_key_exists('is_active', $data)) {
            $user->update(['is_active' => (bool) $data['is_active']]);
        }

        AuditLog::record(
            $request->user()->id,
            'facility.staff_updated',
            User::class,
            $user->id,
            $user->name.' @ '.$facility->name,
            null,
            ['facility_id' => $facility->id, 'data' => $data],
        );

        $user->refresh();

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_active' => (bool) $user->is_active, 'roles' => $user->roles->pluck('name')]]);
    }

    /**
     * DELETE /api/facility/staff/{user} - remove a staff member from the facility.
     */
    public function removeStaff(Request $request, User $user): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $attached = $facility->admins()->where('users.id', $user->id)->first();
        if (! $attached) {
            return response()->json(['error' => 'User is not attached to this facility.'], 404);
        }
        if ($attached->pivot->is_primary) {
            return response()->json(['error' => 'The primary facility admin cannot be removed.'], 422);
        }

        $facility->admins()->detach($user->id);

        AuditLog::record(
            $request->user()->id,
            'facility.staff_removed',
            User::class,
            $user->id,
            $user->name.' @ '.$facility->name,
            null,
            ['facility_id' => $facility->id],
        );

        return response()->json(['message' => 'Staff member removed from facility.']);
    }

    public function profile(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }
        $facility->load('county');

        return response()->json(['data' => $this->formatFacility($facility)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            return response()->json(['error' => 'No authorized facility'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'type' => 'sometimes|nullable|string|in:hospital,clinic,diagnostic,pharmacy,other',
            'registry_number' => 'sometimes|nullable|string|max:191',
        ]);

        $allowed = ['name', 'description', 'address', 'city', 'phone', 'email', 'type', 'registry_number'];
        $data = array_intersect_key($validated, array_flip($allowed));

        // Phase 23: registry identifier changes on a verified facility trigger
        // re-verification (Section 10).
        if ($facility->is_verified && filled($data['registry_number'] ?? null) && $data['registry_number'] !== $facility->getOriginal('registry_number')) {
            $data['verification_status'] = VerificationStatus::PENDING->value;
            $data['is_verified'] = false;

            VerificationRequest::create([
                'verifiable_type' => Facility::class,
                'verifiable_id' => $facility->id,
                'user_id' => $request->user()->id,
                'type' => 'profile_change',
                'status' => VerificationRequestStatus::PENDING,
                'registry_number' => $data['registry_number'],
                'submitted_data' => ['changed_fields' => ['registry_number']],
                'verification_source' => 'self_change',
                'submitted_at' => now(),
            ]);

            AuditLog::record(
                $request->user()->id,
                'verification.reverification_triggered',
                Facility::class,
                $facility->id,
                $facility->name,
                ['verification_status' => 'verified'],
                ['verification_status' => 'pending'],
                'Registry information changed on a verified facility.',
                $request->ip(),
                $request->userAgent(),
            );
        }

        $facility->update($data);

        $formatted = $this->formatFacility($facility->fresh('county'));
        $formatted['verification_status'] = $facility->fresh()->verification_status?->value;

        return response()->json(['data' => $formatted]);
    }

    private function formatFacility(Facility $f): array
    {
        return ['id' => $f->id, 'name' => $f->name, 'slug' => $f->slug, 'description' => $f->description, 'address' => $f->address, 'city' => $f->city, 'county' => $f->county?->name, 'phone' => $f->phone, 'email' => $f->email, 'type' => $f->type, 'is_verified' => (bool) $f->is_verified, 'is_active' => (bool) $f->is_active];
    }

    private function formatSessionForFacility(ClinicSession $s): array
    {
        return ['id' => $s->id, 'doctor' => $s->doctor ? ['id' => $s->doctor->id, 'name' => $s->doctor->display_name, 'slug' => $s->doctor->slug, 'avatar' => $s->doctor->avatar, 'specialties' => $s->doctor->specialties?->map(fn ($sp) => ['id' => $sp->id, 'name' => $sp->name]) ?? []] : null, 'facility' => $s->facility ? ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city] : null, 'location' => $s->facilityLocation ? ['id' => $s->facilityLocation->id, 'name' => $s->facilityLocation->name, 'city' => $s->facilityLocation->city] : null, 'date' => $s->session_date->format('Y-m-d'), 'day' => $s->session_date->format('l'), 'start_time' => substr($s->start_time, 0, 5), 'end_time' => substr($s->end_time, 0, 5), 'slot_duration_minutes' => $s->slot_duration_minutes, 'max_appointments' => $s->max_appointments, 'booked_appointments' => $s->booked_appointments, 'available_slots' => $s->available_slots, 'consultation_fee' => $s->consultation_fee, 'status' => $s->status, 'doctor_confirmation' => $s->doctor_confirmation, 'facility_confirmation' => $s->facility_confirmation, 'is_confirmed' => $s->is_confirmed, 'cancellation_reason' => $s->cancellation_reason];
    }

    private function formatAppointmentForFacility(Appointment $a, bool $detailed = false): array
    {
        return ['id' => $a->id, 'appointment_number' => $a->appointment_number, 'date' => $a->appointment_date->format('Y-m-d'), 'start_time' => substr($a->start_time, 0, 5), 'end_time' => substr($a->end_time, 0, 5), 'status' => $a->status, 'allowed_actions' => $a->allowedFacilityActions(), 'reason' => $a->reason, 'doctor' => $a->doctor, 'checked_in_at' => $a->checked_in_at?->toIso8601String(), 'consultation_started_at' => $a->consultation_started_at?->toIso8601String(), 'no_show_at' => $a->no_show_at?->toIso8601String() ? ['id' => $a->doctor->id, 'name' => $a->doctor->display_name, 'slug' => $a->doctor->slug, 'specialties' => $a->doctor->specialties?->map(fn ($s) => $s->name) ?? []] : null, 'patient_name' => $detailed ? $a->user?->name : null, 'patient_phone' => $detailed ? $a->user?->phone : null];
    }
}
