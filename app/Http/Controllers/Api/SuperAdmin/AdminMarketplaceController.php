<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\DoctorRelationshipStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilityService;
use App\Models\Facility;
use App\Models\Specialty;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Super Admin marketplace overview + governance.
 *
 * Read surfaces are operator-scoped (route level). Every override action here
 * requires a reason, is audited, and never silently mutates history.
 */
class AdminMarketplaceController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    // ─── Doctors ──────────────────────────────────────────────────────────────

    public function doctors(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:200',
            'verified' => 'nullable|in:all,verified,unverified,pending,suspended,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $search = $request->input('search');
        $verified = $request->input('verified', 'all');
        $perPage = (int) $request->input('per_page', 20);

        $query = Doctor::with(['user:id,name,email,phone,is_active', 'specialties:id,name'])
            ->withCount(['facilities'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('display_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            });

        if ($verified === 'verified') {
            $query->where('is_verified', true);
        } elseif ($verified === 'unverified') {
            $query->where('is_verified', false)->where('verification_status', '!=', 'suspended');
        } elseif (in_array($verified, ['pending', 'suspended', 'rejected'], true)) {
            $query->where('verification_status', $verified);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (Doctor $d) => [
                'id' => $d->id, 'display_name' => $d->display_name, 'slug' => $d->slug,
                'user' => $d->user ? ['id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email, 'phone' => $d->user->phone] : null,
                'is_active' => (bool) $d->is_active, 'is_verified' => (bool) $d->is_verified,
                'verification_status' => $d->verification_status?->value,
                'consultation_fee' => (float) $d->consultation_fee,
                'specialties' => $d->specialties->pluck('name'),
                'facility_count' => (int) $d->facilities_count,
                'created_at' => $d->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['total' => $paginated->total(), 'per_page' => $perPage, 'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage()],
        ]);
    }

    // ─── Facilities ───────────────────────────────────────────────────────────

    public function facilities(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:200',
            'verified' => 'nullable|in:all,verified,unverified,pending,suspended,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $search = $request->input('search');
        $verified = $request->input('verified', 'all');
        $perPage = (int) $request->input('per_page', 20);

        $query = Facility::withCount(['admins', 'doctors'])
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")));

        if ($verified === 'verified') {
            $query->where('is_verified', true);
        } elseif ($verified === 'unverified') {
            $query->where('is_verified', false)->where('verification_status', '!=', 'suspended');
        } elseif (in_array($verified, ['pending', 'suspended', 'rejected'], true)) {
            $query->where('verification_status', $verified);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (Facility $f) => [
                'id' => $f->id, 'name' => $f->name, 'slug' => $f->slug, 'type' => $f->type, 'city' => $f->city,
                'is_active' => (bool) $f->is_active, 'is_verified' => (bool) $f->is_verified,
                'verification_status' => $f->verification_status?->value,
                'admin_count' => (int) $f->admins_count, 'doctor_count' => (int) $f->doctors_count,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['total' => $paginated->total(), 'per_page' => $perPage, 'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage()],
        ]);
    }

    // ─── Specialties ──────────────────────────────────────────────────────────

    public function specialties(): JsonResponse
    {
        $items = Specialty::withCount('doctors')->orderBy('name')->get()
            ->map(fn (Specialty $s) => [
                'id' => $s->id, 'name' => $s->name, 'slug' => $s->slug,
                'description' => $s->description, 'icon' => $s->icon,
                'is_active' => (bool) $s->is_active, 'doctor_count' => (int) $s->doctors_count,
            ]);

        return response()->json(['data' => $items]);
    }

    public function storeSpecialty(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:191|unique:specialties,name',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $specialty = Specialty::create([
            'name' => $v->validated()['name'],
            'slug' => Str::slug($v->validated()['name']),
            'description' => $v->validated()['description'] ?? null,
            'icon' => $v->validated()['icon'] ?? null,
            'is_active' => $v->validated()['is_active'] ?? true,
        ]);

        AuditLog::record($request->user()->id, 'specialty.created', 'specialty', $specialty->id, $specialty->name);

        return response()->json(['data' => $this->presentSpecialty($specialty)], 201);
    }

    public function updateSpecialty(Request $request, Specialty $specialty): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:191|unique:specialties,name,'.$specialty->id,
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $specialty->update($v->validated());
        AuditLog::record($request->user()->id, 'specialty.updated', 'specialty', $specialty->id, $specialty->name);

        return response()->json(['data' => $this->presentSpecialty($specialty->fresh())]);
    }

    public function destroySpecialty(Request $request, Specialty $specialty): JsonResponse
    {
        if ($specialty->doctors()->exists()) {
            return response()->json(['error' => 'Specialty is assigned to doctors and cannot be deleted; deactivate it instead.'], 422);
        }
        $specialty->delete();
        AuditLog::record($request->user()->id, 'specialty.deleted', 'specialty', $specialty->id, $specialty->name);

        return response()->json(['message' => 'Specialty deleted.']);
    }

    // ─── Services (marketplace-wide) ──────────────────────────────────────────

    public function services(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $query = DoctorFacilityService::with(['doctorFacility.doctor', 'doctorFacility.facility:id,name,city,type'])
            ->when($request->search, function ($q) use ($request) {
                $q->where('service_name', 'like', '%'.$request->input('search').'%')
                    ->orWhereHas('doctorFacility', fn ($sub) => $sub->whereHas('doctor', fn ($d) => $d->where('display_name', 'like', '%'.$request->input('search').'%')));
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $paginated = $query->orderBy('service_name')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (DoctorFacilityService $s) => [
                'id' => $s->id, 'service_name' => $s->service_name, 'price' => (float) $s->price,
                'duration_minutes' => $s->duration_minutes, 'is_active' => (bool) $s->is_active,
                'doctor' => $s->doctorFacility?->doctor ? ['id' => $s->doctorFacility->doctor->id, 'name' => $s->doctorFacility->doctor->display_name] : null,
                'facility' => $s->doctorFacility?->facility ? ['id' => $s->doctorFacility->facility->id, 'name' => $s->doctorFacility->facility->name, 'city' => $s->doctorFacility->facility->city] : null,
            ])->values(),
            'meta' => ['total' => $paginated->total(), 'per_page' => $perPage, 'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage()],
        ]);
    }

    // ─── Doctor ↔ Facility relationships ──────────────────────────────────────

    public function relationships(Request $request): JsonResponse
    {
        $request->validate(['status' => 'nullable|in:'.implode(',', array_map(fn ($s) => $s->value, DoctorRelationshipStatus::cases())), 'per_page' => 'nullable|integer|min:1|max:100']);
        $perPage = (int) $request->input('per_page', 20);

        $query = DoctorFacility::with(['doctor:id,display_name,is_verified', 'facility:id,name,city,type'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q) => $q->whereHas('doctor', fn ($d) => $d->where('display_name', 'like', '%'.$request->input('search').'%'))->orWhereHas('facility', fn ($f) => $f->where('name', 'like', '%'.$request->input('search').'%')));

        $paginated = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (DoctorFacility $r) => [
                'id' => $r->id,
                'status' => $r->status?->value, 'status_label' => $r->statusLabel(), 'status_color' => $r->status?->color(),
                'accepts_appointments' => (bool) $r->accepts_appointments,
                'consultation_fee' => (float) $r->consultation_fee,
                'requested_by' => $r->requested_by,
                'doctor' => $r->doctor ? ['id' => $r->doctor->id, 'name' => $r->doctor->display_name, 'is_verified' => (bool) $r->doctor->is_verified] : null,
                'facility' => $r->facility ? ['id' => $r->facility->id, 'name' => $r->facility->name, 'city' => $r->facility->city] : null,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['total' => $paginated->total(), 'per_page' => $perPage, 'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage()],
        ]);
    }

    public function relationshipApprove(Request $request, DoctorFacility $relationship): JsonResponse
    {
        return $this->transitionRelationship($request, $relationship, DoctorRelationshipStatus::ACTIVE, 'approve');
    }

    public function relationshipReject(Request $request, DoctorFacility $relationship): JsonResponse
    {
        $v = Validator::make($request->all(), ['reason' => 'required|string|max:255']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }
        $relationship->forceFill(['decline_reason' => $v->validated()['reason']]);

        return $this->transitionRelationship($request, $relationship, DoctorRelationshipStatus::DECLINED, 'reject', $v->validated()['reason']);
    }

    public function relationshipSuspend(Request $request, DoctorFacility $relationship): JsonResponse
    {
        $v = Validator::make($request->all(), ['reason' => 'required|string|max:255']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        return $this->transitionRelationship($request, $relationship, DoctorRelationshipStatus::SUSPENDED, 'suspend', $v->validated()['reason']);
    }

    public function relationshipReactivate(Request $request, DoctorFacility $relationship): JsonResponse
    {
        return $this->transitionRelationship($request, $relationship, DoctorRelationshipStatus::ACTIVE, 'reactivate');
    }

    public function relationshipEnd(Request $request, DoctorFacility $relationship): JsonResponse
    {
        return $this->transitionRelationship($request, $relationship, DoctorRelationshipStatus::INACTIVE, 'end');
    }

    // ─── Clinic sessions ──────────────────────────────────────────────────────

    public function sessions(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'status' => 'nullable|in:pending,confirmed,cancelled,completed',
            'doctor_id' => 'nullable|integer', 'facility_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $perPage = (int) $request->input('per_page', 20);

        $query = ClinicSession::with(['doctor:id,display_name,slug', 'facility:id,name,city,type'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('session_date', '>=', Carbon::parse($request->from)->toDateString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('session_date', '<=', Carbon::parse($request->to)->toDateString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->input('doctor_id')))
            ->when($request->filled('facility_id'), fn ($q) => $q->where('facility_id', $request->input('facility_id')));

        $paginated = $query->orderByDesc('session_date')->orderBy('start_time')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (ClinicSession $s) => $this->presentSession($s))->values(),
            'meta' => ['total' => $paginated->total(), 'per_page' => $perPage, 'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage()],
        ]);
    }

    public function cancelSession(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), ['reason' => 'required|string|max:255']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }
        $session = ClinicSession::find($id);
        if (! $session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }
        if (in_array($session->status, [ClinicSession::STATUS_CANCELLED, ClinicSession::STATUS_COMPLETED], true)) {
            return response()->json(['error' => 'Session cannot be cancelled from its current state.'], 422);
        }

        $reason = $v->validated()['reason'];
        $before = ['status' => $session->status];
        $affectedAppointments = $session->appointments()->whereIn('status', ['pending', 'confirmed'])->get();
        $affectedCount = $affectedAppointments->count();

        // Notify patients while their bookings are still pending/confirmed so the
        // notification can route them to their (soon-to-be-cancelled) appointment.
        $this->notifications->notifyPatientsSessionCancelled($session, $reason);
        $this->notifications->notifyDoctorSessionChanged($session, ['status' => ClinicSession::STATUS_CANCELLED], ['status' => $before['status']]);

        $affectedAppointments->each(function (Appointment $a) use ($request, $reason) {
            $a->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => 'Session cancelled by platform administrator: '.$reason]);
            AuditLog::record($request->user()->id, 'appointment.cancelled', 'appointment', $a->id, null, ['status' => 'booked'], ['status' => 'cancelled'], $reason, $request->ip(), $request->userAgent());
        });

        $session->update(['status' => ClinicSession::STATUS_CANCELLED, 'cancelled_by' => $request->user()->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);

        AuditLog::record(
            $request->user()->id,
            'clinic_session.cancelled_override',
            'clinic_session',
            $session->id,
            $session->doctor?->display_name.' @ '.($session->facility?->name ?? ''),
            $before,
            ['status' => ClinicSession::STATUS_CANCELLED, 'appointments_affected' => $affectedCount],
            $reason,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'message' => 'Session cancelled. '.$affectedCount.' booking(s) affected.',
            'data' => $this->presentSession($session->fresh()),
        ]);
    }

    // ─── Appointments ─────────────────────────────────────────────────────────

    public function appointments(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:200',
            'status' => 'nullable|in:pending,confirmed,completed,cancelled,no_show',
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'doctor_id' => 'nullable|integer', 'facility_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $perPage = (int) $request->input('per_page', 20);
        $search = $request->input('search');

        $query = Appointment::with(['doctor:id,display_name', 'facility:id,name', 'user:id,name,phone,email'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('appointment_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('appointment_date', '>=', Carbon::parse($request->from)->toDateString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('appointment_date', '<=', Carbon::parse($request->to)->toDateString()))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->input('doctor_id')))
            ->when($request->filled('facility_id'), fn ($q) => $q->where('facility_id', $request->input('facility_id')));

        $paginated = $query->orderByDesc('appointment_date')->orderBy('start_time')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (Appointment $a) => [
                'id' => $a->id, 'appointment_number' => $a->appointment_number, 'status' => $a->status,
                'appointment_date' => $a->appointment_date?->toDateString(),
                'start_time' => substr($a->start_time ?? '', 0, 5), 'end_time' => substr($a->end_time ?? '', 0, 5),
                'amount_paid' => (float) $a->amount_paid, 'payment_status' => $a->payment_status,
                'patient' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'phone' => $a->user->phone, 'email' => $a->user->email] : null,
                'doctor' => $a->doctor ? ['id' => $a->doctor->id, 'name' => $a->doctor->display_name] : null,
                'facility' => $a->facility ? ['id' => $a->facility->id, 'name' => $a->facility->name] : null,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['total' => $paginated->total(), 'per_page' => $perPage, 'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage()],
        ]);
    }

    public function cancelAppointment(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), ['reason' => 'required|string|max:255']);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }
        $appointment = Appointment::find($id);
        if (! $appointment) {
            return response()->json(['error' => 'Appointment not found.'], 404);
        }
        if (in_array($appointment->status, ['cancelled', 'completed', 'no_show'], true)) {
            return response()->json(['error' => 'Appointment cannot be cancelled from its current state.'], 422);
        }

        $before = ['status' => $appointment->status];
        $appointment->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => $v->validated()['reason']]);

        AuditLog::record(
            $request->user()->id,
            'appointment.cancelled_exceptionally',
            'appointment',
            $appointment->id,
            $appointment->appointment_number,
            $before,
            ['status' => 'cancelled'],
            $v->validated()['reason'],
            $request->ip(),
            $request->userAgent(),
        );

        $this->notifications->notifyPatientAppointmentCancelled($appointment);
        $this->notifications->notifyDoctorAppointmentCancelled($appointment);

        return response()->json(['message' => 'Appointment cancelled.', 'data' => ['id' => $appointment->id, 'status' => 'cancelled']]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function transitionRelationship(Request $request, DoctorFacility $relationship, DoctorRelationshipStatus $target, string $verb, ?string $reason = null): JsonResponse
    {
        $current = $relationship->status;
        $reason = $reason ?: $request->input('reason');

        if (! $current || ! $current->canTransitionTo($target)) {
            return response()->json(['error' => "Relationship cannot transition from {$current?->value} to {$target->value}."], 422);
        }

        $relationship->update([
            'status' => $target,
            'approved_by' => in_array($verb, ['approve', 'reactivate'], true) ? $request->user()->id : $relationship->approved_by,
            'approved_at' => in_array($verb, ['approve', 'reactivate'], true) ? now() : $relationship->approved_at,
            'ended_by' => in_array($verb, ['end'], true) ? $request->user()->id : $relationship->ended_by,
            'ended_at_governance' => in_array($verb, ['end'], true) ? now() : $relationship->ended_at_governance,
            'notes_governance' => $reason ?: $relationship->notes_governance,
        ]);

        AuditLog::record(
            $request->user()->id,
            'relationship.'.$verb,
            'doctor_facility',
            $relationship->id,
            ($relationship->doctor?->display_name ?? '').' ↔ '.($relationship->facility?->name ?? ''),
            ['status' => $current?->value],
            ['status' => $target->value],
            $reason,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Relationship '.$verb.'d.', 'data' => ['id' => $relationship->id, 'status' => $target->value]]);
    }

    private function presentSession(ClinicSession $s): array
    {
        return [
            'id' => $s->id,
            'session_date' => $s->session_date?->toDateString(),
            'start_time' => substr($s->start_time ?? '', 0, 5), 'end_time' => substr($s->end_time ?? '', 0, 5),
            'status' => $s->status, 'doctor_confirmation' => $s->doctor_confirmation, 'facility_confirmation' => $s->facility_confirmation,
            'is_confirmed' => $s->is_confirmed,
            'max_appointments' => $s->max_appointments, 'booked_appointments' => (int) $s->booked_appointments,
            'available_slots' => $s->available_slots, 'consultation_fee' => (float) $s->consultation_fee,
            'cancellation_reason' => $s->cancellation_reason,
            'doctor' => $s->doctor ? ['id' => $s->doctor->id, 'name' => $s->doctor->display_name, 'slug' => $s->doctor->slug] : null,
            'facility' => $s->facility ? ['id' => $s->facility->id, 'name' => $s->facility->name, 'city' => $s->facility->city] : null,
        ];
    }

    private function presentSpecialty(Specialty $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'description' => $s->description, 'icon' => $s->icon, 'is_active' => (bool) $s->is_active];
    }
}
