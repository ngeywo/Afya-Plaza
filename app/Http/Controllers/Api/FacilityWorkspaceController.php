<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\DoctorFacility;
use App\Models\Facility;
use App\Models\FacilityLocation;
use App\Models\User;
use App\Events\ClinicSessionConfirmed;
use App\Events\ClinicSessionCancelled;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FacilityWorkspaceController extends Controller
{
    public function __construct(private AppointmentService $appointments) {}

    private function resolveFacility(Request $request): ?Facility
    {
        $user = $request->user();
        if (!$user->hasAnyRole(["facility-admin", "facility-staff", "admin"])) return null;
        if ($user->isSuperAdmin() || $user->hasRole("admin")) {
            $id = $request->query("facility_id");
            return $id ? Facility::find($id) : Facility::first();
        }
        $id = $request->query("facility_id");
        if ($id) return $user->facilities()->where("facilities.id", $id)->first();
        return $user->facilities()->first();
    }

    private function authorizeFacility(Request $request): ?Facility
    {
        $user = $request->user();
        if (!$user) return null;
        $facility = $this->resolveFacility($request);
        if (!$facility) return null;
        if ($user->isSuperAdmin() || $user->hasRole("admin")) return $facility;
        if (!$user->facilities()->where("facilities.id", $facility->id)->exists()) return null;
        return $facility;
    }

    public function dashboard(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $today = Carbon::today();
        $todays = ClinicSession::with(["doctor.specialties", "facilityLocation"])->where("facility_id", $facility->id)->whereDate("session_date", $today)->orderBy("start_time")->get();
        $tomorrow = ClinicSession::with(["doctor", "facilityLocation"])->where("facility_id", $facility->id)->whereDate("session_date", $today->copy()->addDay())->orderBy("start_time")->get();
        $pending = ClinicSession::with(["doctor"])->where("facility_id", $facility->id)->where("status", "pending")->whereDate("session_date", ">=", $today)->orderBy("session_date")->orderBy("start_time")->limit(20)->get();
        $todayAppointments = Appointment::with(["doctor", "clinicSession"])->where("facility_id", $facility->id)->whereDate("appointment_date", $today)->orderBy("start_time")->get();
        return response()->json(["data" => [
            "facility" => $this->formatFacility($facility),
            "today" => ["date" => $today->toDateString(), "day" => $today->format("l, F j, Y"), "clinics" => $todays->map(fn ($s) => $this->formatSessionForFacility($s)), "appointment_count" => $todayAppointments->count(), "confirmed_clinics" => $todays->where("is_confirmed", true)->count(), "pending_confirmations" => $todays->where("status", "pending")->count()],
            "tomorrow" => ["date" => $today->copy()->addDay()->toDateString(), "clinics" => $tomorrow->map(fn ($s) => $this->formatSessionForFacility($s))],
            "pending_confirmations" => $pending->map(fn ($s) => $this->formatSessionForFacility($s)),
            "appointments_today" => $todayAppointments->map(fn ($a) => $this->formatAppointmentForFacility($a)),
        ]]);
    }

    public function doctors(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $doctorFacilities = DoctorFacility::with(["doctor.specialties"])->where("facility_id", $facility->id)->where("is_active", true)->get();
        $today = Carbon::today();
        return response()->json(["data" => $doctorFacilities->map(function ($df) use ($today, $facility) {
            $next = ClinicSession::where("doctor_id", $df->doctor_id)->where("facility_id", $facility->id)->whereDate("session_date", ">=", $today)->orderBy("session_date")->orderBy("start_time")->first();
            return ["id" => $df->doctor_id, "pivot_id" => $df->id, "name" => $df->doctor?->display_name, "slug" => $df->doctor?->slug, "avatar" => $df->doctor?->avatar, "is_verified" => $df->doctor?->is_verified, "specialties" => $df->doctor?->specialties?->map(fn ($s) => ["id" => $s->id, "name" => $s->name, "is_primary" => (bool) $s->pivot->is_primary]) ?? [], "consultation_fee" => $df->consultation_fee, "accepts_appointments" => (bool) $df->accepts_appointments, "relationship_active" => (bool) $df->is_active, "next_clinic" => $next ? ["id" => $next->id, "date" => $next->session_date->format("Y-m-d"), "day" => $next->session_date->format("l"), "start_time" => substr($next->start_time, 0, 5), "end_time" => substr($next->end_time, 0, 5), "status" => $next->status, "is_confirmed" => $next->is_confirmed] : null];
        })]);
    }

    public function clinicSessions(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $filter = $request->query("filter", "upcoming");
        $status = $request->query("status");
        $today = Carbon::today();
        $q = ClinicSession::with(["doctor.specialties", "facilityLocation"])->where("facility_id", $facility->id);
        if ($filter === "today") $q->whereDate("session_date", $today);
        elseif ($filter === "upcoming") $q->whereDate("session_date", ">=", $today);
        elseif ($filter === "past") $q->whereDate("session_date", "<", $today);
        if ($status) $q->where("status", $status);
        return response()->json(["data" => $q->orderBy("session_date")->orderBy("start_time")->limit(100)->get()->map(fn ($s) => $this->formatSessionForFacility($s))]);
    }

    public function confirmSession(Request $request, int $id): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $session = ClinicSession::where("facility_id", $facility->id)->find($id);
        if (!$session) return response()->json(["error" => "Clinic session not found"], 404);
        if ($session->status === "cancelled") return response()->json(["error" => "Cannot confirm a cancelled session"], 422);
        $wasConfirmed = $session->status === "confirmed";
        $wasPending   = $session->status === "pending";
        $session->update(["facility_confirmation" => "confirmed", "facility_confirmed_at" => now()]);
        $newStatus = ($session->doctor_confirmation === "confirmed") ? "confirmed" : "pending";
        $session->update(["status" => $newStatus]);
        $fresh = $session->fresh(["doctor", "facilityLocation"]);
        // Phase 14: Fire the event when the session transitions to confirmed.
        if (!$wasConfirmed && $fresh->status === "confirmed") {
            ClinicSessionConfirmed::dispatch($fresh, $wasPending);
        }
        return response()->json(["data" => $this->formatSessionForFacility($fresh)]);
    }

    public function rejectSession(Request $request, int $id): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $v = Validator::make($request->all(), ["reason" => "required|string|max:500"]);
        if ($v->fails()) return response()->json(["error" => $v->errors()->first(), "errors" => $v->errors()], 422);
        $session = ClinicSession::where("facility_id", $facility->id)->find($id);
        if (!$session) return response()->json(["error" => "Clinic session not found"], 404);
        $wasCancelled = $session->status === "cancelled";
        $reason = $request->reason;
        $session->update(["facility_confirmation" => "declined", "facility_confirmed_at" => now(), "cancellation_reason" => $reason]);
        if ($session->doctor_confirmation !== "confirmed") {
            $session->update(["status" => "cancelled", "cancelled_by" => $request->user()->id, "cancelled_at" => now()]);
            // Phase 17: delegated to AppointmentService — actor recorded, slots
            // released, patients notified per appointment. History preserved.
            $this->appointments->cancelSessionAppointments($session, $request->user(), $reason);
        }
        $fresh = $session->fresh(["doctor", "facilityLocation"]);
        // Phase 14: Fire the event when the session becomes cancelled.
        if (!$wasCancelled && $fresh->status === "cancelled") {
            ClinicSessionCancelled::dispatch($fresh, $reason);
        }
        return response()->json(["data" => $this->formatSessionForFacility($fresh)]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $date = $request->filled("date") ? Carbon::parse($request->date) : Carbon::today();
        $q = Appointment::with(["doctor.specialties", "clinicSession"])->where("facility_id", $facility->id)->whereDate("appointment_date", $date);
        if ($request->query("status")) $q->where("status", $request->query("status"));
        if ($request->query("doctor_id")) $q->where("doctor_id", $request->query("doctor_id"));
        $appointments = $q->orderBy("start_time")->limit(200)->get();
        $doctors = DoctorFacility::with("doctor")->where("facility_id", $facility->id)->where("is_active", true)->get()->map(fn ($df) => ["id" => $df->doctor_id, "name" => $df->doctor?->display_name]);
        return response()->json(["data" => $appointments->map(fn ($a) => $this->formatAppointmentForFacility($a)), "doctors" => $doctors, "meta" => ["date" => $date->toDateString(), "total" => $appointments->count()]]);
    }

    public function showAppointment(Request $request, int $id): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $appointment = Appointment::with(["doctor.specialties", "clinicSession.facilityLocation", "user"])->where("facility_id", $facility->id)->find($id);
        if (!$appointment) return response()->json(["error" => "Appointment not found"], 404);
        return response()->json(["data" => $this->formatAppointmentForFacility($appointment, true)]);
    }

    public function locations(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $locations = $facility->allLocations()->with("county")->orderByDesc("is_primary")->get();
        return response()->json(["data" => $locations->map(fn ($l) => ["id" => $l->id, "name" => $l->name, "address" => $l->address, "city" => $l->city, "county" => $l->county?->name, "phone" => $l->phone, "email" => $l->email, "is_primary" => (bool) $l->is_primary, "is_active" => (bool) $l->is_active])]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $v = Validator::make($request->all(), ["name" => "required|string|max:255", "address" => "nullable|string|max:255", "city" => "nullable|string|max:120", "phone" => "nullable|string|max:20", "email" => "nullable|email", "is_primary" => "boolean"]);
        if ($v->fails()) return response()->json(["error" => $v->errors()->first(), "errors" => $v->errors()], 422);
        $data = $v->validated();
        $data["facility_id"] = $facility->id;
        $data["is_primary"] = $data["is_primary"] ?? false;
        $data["is_active"] = true;
        if ($data["is_primary"]) FacilityLocation::where("facility_id", $facility->id)->update(["is_primary" => false]);
        $location = FacilityLocation::create($data);
        return response()->json(["data" => $location], 201);
    }

    public function staff(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $staff = User::whereHas("facilities", fn ($q) => $q->where("facilities.id", $facility->id))->with("roles", "facilities")->get()->map(fn ($u) => ["id" => $u->id, "name" => $u->name, "email" => $u->email, "phone" => $u->phone, "is_active" => (bool) $u->is_active, "roles" => $u->roles->pluck("name"), "is_primary" => (bool) $u->facilities->where("id", $facility->id)->first()?->pivot?->is_primary, "authorized_facility_count" => $u->facilities->count()]);
        return response()->json(["data" => $staff]);
    }

    public function profile(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $facility->load("county");
        return response()->json(["data" => $this->formatFacility($facility)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $facility = $this->authorizeFacility($request);
        if (!$facility) return response()->json(["error" => "No authorized facility"], 403);
        $allowed = ["name", "description", "address", "city", "phone", "email", "type"];
        $data = array_intersect_key($request->all(), array_flip($allowed));
        $facility->update($data);
        return response()->json(["data" => $this->formatFacility($facility->fresh("county"))]);
    }

    private function formatFacility(Facility $f): array
    {
        return ["id" => $f->id, "name" => $f->name, "slug" => $f->slug, "description" => $f->description, "address" => $f->address, "city" => $f->city, "county" => $f->county?->name, "phone" => $f->phone, "email" => $f->email, "type" => $f->type, "is_verified" => (bool) $f->is_verified, "is_active" => (bool) $f->is_active];
    }

    private function formatSessionForFacility(ClinicSession $s): array
    {
        return ["id" => $s->id, "doctor" => $s->doctor ? ["id" => $s->doctor->id, "name" => $s->doctor->display_name, "slug" => $s->doctor->slug, "avatar" => $s->doctor->avatar, "specialties" => $s->doctor->specialties?->map(fn ($sp) => ["id" => $sp->id, "name" => $sp->name]) ?? []] : null, "facility" => $s->facility ? ["id" => $s->facility->id, "name" => $s->facility->name, "city" => $s->facility->city] : null, "location" => $s->facilityLocation ? ["id" => $s->facilityLocation->id, "name" => $s->facilityLocation->name, "city" => $s->facilityLocation->city] : null, "date" => $s->session_date->format("Y-m-d"), "day" => $s->session_date->format("l"), "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5), "slot_duration_minutes" => $s->slot_duration_minutes, "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments, "available_slots" => $s->available_slots, "consultation_fee" => $s->consultation_fee, "status" => $s->status, "doctor_confirmation" => $s->doctor_confirmation, "facility_confirmation" => $s->facility_confirmation, "is_confirmed" => $s->is_confirmed, "cancellation_reason" => $s->cancellation_reason];
    }

    private function formatAppointmentForFacility(Appointment $a, bool $detailed = false): array
    {
        return ["id" => $a->id, "appointment_number" => $a->appointment_number, "date" => $a->appointment_date->format("Y-m-d"), "start_time" => substr($a->start_time, 0, 5), "end_time" => substr($a->end_time, 0, 5), "status" => $a->status, "allowed_actions" => $a->allowedFacilityActions(), "reason" => $a->reason,"doctor" => $a->doctor,"checked_in_at" => $a->checked_in_at?->toIso8601String(), "consultation_started_at" => $a->consultation_started_at?->toIso8601String(), "no_show_at" => $a->no_show_at?->toIso8601String() ? ["id" => $a->doctor->id, "name" => $a->doctor->display_name, "slug" => $a->doctor->slug, "specialties" => $a->doctor->specialties?->map(fn ($s) => $s->name) ?? []] : null, "patient_name" => $detailed ? $a->user?->name : null, "patient_phone" => $detailed ? $a->user?->phone : null];
    }
}
