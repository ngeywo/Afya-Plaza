<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSession;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Events\ClinicSessionConfirmed;
use App\Events\ClinicSessionCancelled;
use App\Events\ClinicSessionChanged;
use App\Services\AppointmentService;

/**
 * Phase 2: Session lifecycle, confirmation, collision detection, availability.
 */
class SessionController extends Controller
{
    public function __construct(private AppointmentService $appointments) {}

    /** GET /api/sessions - List sessions scoped by user role */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $from = $request->filled("from") ? Carbon::parse($request->from) : today();
        $to = $request->filled("to") ? Carbon::parse($request->to) : $from->copy()->addDays(14);
        $q = ClinicSession::with(["doctor","facility"])->whereBetween("session_date",[$from->toDateString(),$to->toDateString()]);
        if ($user->hasRole("doctor")) $q->where("doctor_id",$user->doctor->id);
        elseif ($user->hasRole("facility_admin")) $q->whereIn("facility_id",$user->facilities()->pluck("facilities.id"));
        elseif (!$user->hasRole("admin")) return response()->json(["error"=>"Forbidden"],403);
        return response()->json(["data"=>$q->orderBy("session_date")->orderBy("start_time")->get()->map(fn($s)=>$this->formatSession($s))]);
    }

    /** GET /api/sessions/{id} - Single session with slots */
    public function show(int $id): JsonResponse
    {
        $s = ClinicSession::with(["doctor","facility"])->findOrFail($id);
        $slots = $this->calculateSlots($s);
        $booked = $s->appointments()->whereIn("status",["pending","confirmed"])->pluck("start_time")->map(fn($t)=>substr($t,0,5));
        return response()->json(["data"=>array_merge($this->formatSession($s),["slots"=>collect($slots)->map(fn($sl)=>["start_time"=>$sl["start_time"],"end_time"=>$sl["end_time"],"is_available"=>!$booked->contains($sl["start_time"])])])]);
    }

    /** POST /api/sessions - Create session with collision detection */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $v = Validator::make($request->all(),["doctor_id"=>"required|integer|exists:doctors,id","facility_id"=>"required|integer|exists:facilities,id","session_date"=>"required|date|after_or_equal:today","start_time"=>"required|date_format:H:i","end_time"=>"required|date_format:H:i|after:start_time","slot_duration_minutes"=>"nullable|integer|min:10|max:120","max_appointments"=>"nullable|integer|min:1","consultation_fee"=>"nullable|numeric|min:0","notes"=>"nullable|string|max:500"]);
        if ($v->fails()) return response()->json(["error"=>$v->errors()->first(),"errors"=>$v->errors()],422);
        $d = $v->validated();
        // Authorization FIRST
        $doctor = Doctor::find($d["doctor_id"]);
        if ($user->hasRole("doctor") && $user->doctor?->id !== $doctor->id) return response()->json(["error"=>"Forbidden"],403);
        if ($user->hasRole("facility_admin") && !$user->facilities()->where("facilities.id",$d["facility_id"])->exists()) return response()->json(["error"=>"Forbidden"],403);
        if (!$user->hasAnyRole(["doctor","facility_admin","admin"])) return response()->json(["error"=>"Forbidden"],403);
        // Section 21: Collision check
        $col = ClinicSession::where("doctor_id",$d["doctor_id"])->where("session_date",$d["session_date"])->whereIn("status",["pending","confirmed"])->where(fn($q)=>$q->where(fn($i)=>$i->where("start_time","<=",$d["start_time"].":00")->where("end_time",">",$d["start_time"].":00"))->orWhere(fn($i)=>$i->where("start_time","<",$d["end_time"].":00")->where("end_time",">=",$d["end_time"].":00"))->orWhere(fn($i)=>$i->where("start_time",">=",$d["start_time"].":00")->where("end_time","<=",$d["end_time"].":00")))->first();
        if ($col) {$col->load("facility");return response()->json(["error"=>"Doctor already has a clinic at this time.","code"=>"DOCTOR_COLLISION","collision"=>["facility"=>$col->facility->name,"time"=>substr($col->start_time,0,5)." - ".substr($col->end_time,0,5)]],409);}
        $d = $v->validated();
        $col = ClinicSession::where("doctor_id",$d["doctor_id"])->where("session_date",$d["session_date"])->whereIn("status",["pending","confirmed"])->where(fn($q)=>$q->where(fn($i)=>$i->where("start_time","<=",$d["start_time"].":00")->where("end_time",">",$d["start_time"].":00"))->orWhere(fn($i)=>$i->where("start_time","<",$d["end_time"].":00")->where("end_time",">=",$d["end_time"].":00"))->orWhere(fn($i)=>$i->where("start_time",">=",$d["start_time"].":00")->where("end_time","<=",$d["end_time"].":00")))->first();
        if ($col) {$col->load("facility");return response()->json(["error"=>"Doctor already has a clinic at this time.","code"=>"DOCTOR_COLLISION","collision"=>["facility"=>$col->facility->name,"time"=>substr($col->start_time,0,5)." - ".substr($col->end_time,0,5)]],409);}
        $doctor = Doctor::find($d["doctor_id"]);
        if ($user->hasRole("doctor") && $user->doctor?->id !== $doctor->id) return response()->json(["error"=>"Forbidden"],403);
        $date = Carbon::parse($d["session_date"]);
        $df = $doctor->doctorFacilities()->where("facility_id",$d["facility_id"])->where("is_active",true)->first();
        $sch = $df ? $df->schedules()->where("day_of_week",$date->dayOfWeek)->where("is_active",true)->first() : null;
        $docConf = $user->hasRole("doctor") ? "confirmed" : "pending";
        $facConf = $user->hasRole("facility_admin") ? "confirmed" : "pending";
        $status = ($docConf === "confirmed" && $facConf === "confirmed") ? "confirmed" : "pending";
        $session = DB::transaction(fn()=>ClinicSession::create(["doctor_id"=>$d["doctor_id"],"facility_id"=>$d["facility_id"],"doctor_facility_schedule_id"=>$sch?->id,"session_date"=>$d["session_date"],"start_time"=>$d["start_time"].":00","end_time"=>$d["end_time"].":00","slot_duration_minutes"=>$d["slot_duration_minutes"] ?? $sch?->slot_duration_minutes ?? 30,"max_appointments"=>$d["max_appointments"] ?? $sch?->max_appointments,"consultation_fee"=>$d["consultation_fee"] ?? $df?->consultation_fee ?? $doctor->consultation_fee,"status"=>$status,"doctor_confirmation"=>$docConf,"doctor_confirmed_at"=>$docConf==="confirmed"?now():null,"facility_confirmation"=>$facConf,"facility_confirmed_at"=>$facConf==="confirmed"?now():null,"notes"=>$d["notes"]??null]));
        $session->load(["doctor","facility"]);
        return response()->json(["data"=>$this->formatSession($session),"message"=>"Session created."],201);
    }

    /** PUT /api/sessions/{id}/confirm - Section 7 confirmation */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $s = ClinicSession::with(["doctor","facility"])->findOrFail($id);
        $user = $request->user();
        $role = null;
        if ($user->hasRole("doctor") && $user->doctor?->id === $s->doctor_id) $role = "doctor";
        elseif ($user->hasRole("facility_admin") && $user->facilities()->where("facilities.id",$s->facility_id)->exists()) $role = "facility";
        elseif ($user->hasRole("admin")) $role = "admin";
        else return response()->json(["error"=>"Forbidden"],403);
        if ($s->status === "cancelled") return response()->json(["error"=>"Cannot confirm a cancelled session."],422);
        $wasConfirmed = $s->status === 'confirmed';
        $wasPending = $s->status === 'pending';
        DB::transaction(function()use($s,$role){
            if (in_array($role,["doctor","admin"])) $s->update(["doctor_confirmation"=>"confirmed","doctor_confirmed_at"=>now()]);
            if (in_array($role,["facility","admin"])) $s->update(["facility_confirmation"=>"confirmed","facility_confirmed_at"=>now()]);
            $f = $s->fresh();
            if ($f->doctor_confirmation==="confirmed" && $f->facility_confirmation==="confirmed") $s->update(["status"=>"confirmed"]);
        });
        $s->refresh()->load(["doctor","facility"]);
        // Fire event only when a non-confirmed session transitions to confirmed
        if (!$wasConfirmed && $s->status === 'confirmed') {
            ClinicSessionConfirmed::dispatch($s, $wasPending);
        }
        return response()->json(["data"=>$this->formatSession($s),"message"=>"Session confirmed."]);
    }

    /**
     * PUT /api/sessions/{id}/cancel */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $s = ClinicSession::findOrFail($id);
        $user = $request->user();
        $can = $user->hasRole("admin")||($user->hasRole("doctor")&&$user->doctor?->id===$s->doctor_id)||($user->hasRole("facility_admin")&&$user->facilities()->where("facilities.id",$s->facility_id)->exists());
        if (!$can) return response()->json(["error"=>"Forbidden"],403);
        if ($s->status==="completed") return response()->json(["error"=>"Cannot cancel completed session."],422);
        $reason = $request->input("reason","Cancelled");
        $wasCancelled = $s->status === "cancelled";
        // Phase 17: delegated to AppointmentService — every affected appointment
        // is cancelled with the acting user recorded, slots are released and
        // patients are notified for their specific appointment. Appointments are
        // never hard-deleted (historical truth preserved).
        $this->appointments->cancelSession($s, $user, $reason);
        $this->appointments->cancelSessionAppointments($s, $user, $reason);
        // Fire event only once per cancellation
        if (!$wasCancelled) {
            ClinicSessionCancelled::dispatch($s->fresh(), $reason);
        }
        return response()->json(["data"=>$this->formatSession($s->fresh(["doctor","facility"])),"message"=>"Session cancelled."]);
    }

    /**
     * PUT /api/sessions/{id}
     *
     * Phase 14: Updates session fields. If the date, time, or facility
     * meaningfully changes, fires ClinicSessionChanged so listeners can
     * notify affected patients and the doctor.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $s = ClinicSession::with(["doctor", "facility"])->findOrFail($id);
        $user = $request->user();
        $can = $user->hasRole("admin")
            || ($user->hasRole("doctor") && $user->doctor?->id === $s->doctor_id)
            || ($user->hasRole("facility_admin") && $user->facilities()->where("facilities.id", $s->facility_id)->exists());
        if (!$can) return response()->json(["error" => "Forbidden"], 403);
        if ($s->status === "cancelled") return response()->json(["error" => "Cannot edit cancelled session."], 422);

        $v = Validator::make($request->all(), [
            "facility_id" => "sometimes|integer|exists:facilities,id",
            "session_date" => "sometimes|date|after_or_equal:today",
            "start_time" => "sometimes|date_format:H:i",
            "end_time" => "sometimes|date_format:H:i|after:start_time",
            "slot_duration_minutes" => "sometimes|integer|min:10|max:120",
            "max_appointments" => "sometimes|integer|min:1",
            "consultation_fee" => "sometimes|numeric|min:0",
            "notes" => "sometimes|nullable|string|max:500",
        ]);
        if ($v->fails()) return response()->json(["error" => $v->errors()->first(), "errors" => $v->errors()], 422);

        $data = $v->validated();
        if (isset($data["start_time"])) $data["start_time"] = $data["start_time"] . ":00";
        if (isset($data["end_time"]))   $data["end_time"]   = $data["end_time"] . ":00";

        $tracked = ["facility_id", "session_date", "start_time", "end_time"];
        $oldValues = [];
        foreach ($tracked as $k) {
            $oldValues[$k] = $k === "session_date" ? $s->session_date->toDateString() : $s->{$k};
        }

        DB::transaction(function () use ($s, $data) {
            $s->update($data);
        });

        $s->refresh()->load(["doctor", "facility"]);

        $changes = [];
        foreach ($tracked as $k) {
            $newVal = $k === "session_date" ? $s->session_date->toDateString() : $s->{$k};
            $oldVal = $oldValues[$k] ?? null;
            if ((string) $oldVal !== (string) $newVal) {
                $changes[$k] = $newVal;
            }
        }

        if (!empty($changes)) {
            ClinicSessionChanged::dispatch($s, array_keys($changes), $oldValues);
        }

        return response()->json(["data" => $this->formatSession($s), "message" => "Session updated."]);
    }
public function doctorAvailability(Request $request, int $doctorId): JsonResponse
    {
        $date = $request->filled("date") ? Carbon::parse($request->date) : today();
        $doctor = Doctor::with(["specialties", "facilities.county"])->findOrFail($doctorId);

        // Only confirmed sessions are patient-visible (Section 12)
        $sessions = ClinicSession::with(["facility", "facility.county"])
            ->where("doctor_id", $doctorId)
            ->where("session_date", $date->toDateString())
            ->where("status", "confirmed")
            ->orderBy("start_time")
            ->get();

        $next = null;
        if ($sessions->isEmpty()) {
            $next = ClinicSession::with(["facility"])
                ->where("doctor_id", $doctorId)
                ->where("session_date", ">", $date->toDateString())
                ->where("status", "confirmed")
                ->orderBy("session_date")
                ->orderBy("start_time")
                ->first();
        }

        $r = [
            "doctor" => [
                "id" => $doctor->id,
                "slug" => $doctor->slug,
                "name" => $doctor->display_name,
                "avatar" => $doctor->avatar,
                "is_verified" => $doctor->is_verified,
                "consultation_fee" => $doctor->consultation_fee,
                "specialties" => $doctor->specialties->map(fn($s) => [
                    "id" => $s->id,
                    "name" => $s->name,
                    "icon" => $s->icon,
                ]),
            ],
            "date" => $date->toDateString(),
            "day" => $date->format("l, F j, Y"),
            "sessions" => $sessions->map(fn($s) => [
                "id" => $s->id,
                "facility" => [
                    "id" => $s->facility->id,
                    "name" => $s->facility->name,
                    "city" => $s->facility->city,
                    "county" => $s->facility->county?->name,
                    "type" => $s->facility->type,
                ],
                "start_time" => substr($s->start_time, 0, 5),
                "end_time" => substr($s->end_time, 0, 5),
                "status" => $s->status,
                "is_confirmed" => $s->is_confirmed,
                "available_slots" => $s->available_slots,
                "max_appointments" => $s->max_appointments,
                "is_bookable" => $s->is_bookable,
                "consultation_fee" => $s->consultation_fee,
            ]),
        ];
        if ($next) {
            $r["next_session"] = [
                "date" => $next->session_date->format("Y-m-d"),
                "day" => $next->session_date->format("l, F j"),
                "facility" => $next->facility->name,
                "city" => $next->facility->city,
                "county" => $next->facility->county?->name,
                "start_time" => substr($next->start_time, 0, 5),
                "end_time" => substr($next->end_time, 0, 5),
            ];
        }
        return response()->json(["data" => $r]);
    }

    /**
     * GET /api/sessions/search
     *
     * Phase 3: Session-driven doctor search.
     * Filters by date (required), doctor_name, specialty_id, county_id, facility_id, city.
     * Only returns confirmed sessions with available slots (patient-safe).
     *
     * Section 3: Doctor-Facility relationship ≠ actual presence on a date.
     * Section 4-7: Search modes by doctor, specialty, location.
     * Section 12: Doctor verification is independent of session confirmation.
     * Section 17: Efficient querying with eager loading; N+1 prevention via with().
     */
    public function search(Request $request): JsonResponse
    {
        $date = $request->filled("date") ? Carbon::parse($request->date) : today();

        // Only show confirmed sessions to patients (Section 12: session visibility rules).
        // Pending sessions are internal until confirmed by both parties.
        $query = ClinicSession::with(["doctor.specialties", "facility.county"])
            ->where("session_date", $date->toDateString())
            ->where("status", "confirmed")
            // Only show sessions with availability. Handle nullable max_appointments.
            ->where(function ($q) {
                $q->whereRaw("`booked_appointments` < `max_appointments`")
                  ->orWhereNull("max_appointments");
            });

        // Section 4: Search by doctor name
        if ($request->filled("doctor_name")) {
            $query->whereHas("doctor", fn($q) => $q->where("display_name", "like", "%" . $request->doctor_name . "%"));
        }

        // Section 5: Search by specialty
        if ($request->filled("specialty_id")) {
            $query->whereHas("doctor.specialties", fn($q) => $q->where("specialties.id", $request->specialty_id));
        }

        // Section 6-7: Search by location (county or city)
        if ($request->filled("county_id")) {
            $query->whereHas("facility.county", fn($q) => $q->where("counties.id", $request->county_id));
        }

        if ($request->filled("city")) {
            $query->whereHas("facility", fn($q) => $q->where("city", "like", "%" . $request->city . "%"));
        }

        // Section 6: Search by specific facility
        if ($request->filled("facility_id")) {
            $query->where("facility_id", $request->facility_id);
        }

        $sessions = $query->orderBy("start_time")->get();

        // Section 13: Use authoritative availability from model, not duplicated calculation
        return response()->json([
            "data" => $sessions->map(fn($s) => [
                "id" => $s->id,
                "doctor" => [
                    "id" => $s->doctor->id,
                    "slug" => $s->doctor->slug,
                    "name" => $s->doctor->display_name,
                    "avatar" => $s->doctor->avatar,
                    "is_verified" => $s->doctor->is_verified,
                    "consultation_fee" => $s->doctor->consultation_fee,
                    "years_of_experience" => $s->doctor->years_of_experience,
                    "specialties" => $s->doctor->specialties->map(fn($sp) => [
                        "id" => $sp->id,
                        "name" => $sp->name,
                        "icon" => $sp->icon,
                        "is_primary" => (bool) $sp->pivot->is_primary,
                    ]),
                ],
                "facility" => [
                    "id" => $s->facility->id,
                    "name" => $s->facility->name,
                    "city" => $s->facility->city,
                    "county" => $s->facility->county?->name,
                    "type" => $s->facility->type,
                    "is_verified" => $s->facility->is_verified,
                ],
                "start_time" => substr($s->start_time, 0, 5),
                "end_time" => substr($s->end_time, 0, 5),
                "slot_duration_minutes" => $s->slot_duration_minutes,
                "max_appointments" => $s->max_appointments,
                "available_slots" => $s->available_slots,
                "consultation_fee" => $s->consultation_fee,
                "status" => $s->status,
                "is_confirmed" => $s->is_confirmed,
                "is_bookable" => $s->is_bookable,
            ]),
            "meta" => [
                "date" => $date->toDateString(),
                "day" => $date->format("l, F j, Y"),
                "total" => $sessions->count(),
            ],
        ]);
    }
    private function formatSession(ClinicSession $s): array
    {
        return ["id"=>$s->id,"doctor"=>$s->doctor?["id"=>$s->doctor->id,"slug"=>$s->doctor->slug,"name"=>$s->doctor->display_name,"avatar"=>$s->doctor->avatar]:null,"facility"=>$s->facility?["id"=>$s->facility->id,"name"=>$s->facility->name,"city"=>$s->facility->city,"type"=>$s->facility->type]:null,"session_date"=>$s->session_date->format("Y-m-d"),"day"=>$s->session_date->format("l"),"start_time"=>substr($s->start_time,0,5),"end_time"=>substr($s->end_time,0,5),"slot_duration_minutes"=>$s->slot_duration_minutes,"max_appointments"=>$s->max_appointments,"booked_appointments"=>$s->booked_appointments,"available_slots"=>$s->available_slots,"consultation_fee"=>$s->consultation_fee,"status"=>$s->status,"doctor_confirmation"=>$s->doctor_confirmation,"facility_confirmation"=>$s->facility_confirmation,"is_confirmed"=>$s->is_confirmed,"is_bookable"=>$s->is_bookable,"cancellation_reason"=>$s->cancellation_reason,"notes"=>$s->notes];
    }

    private function calculateSlots(ClinicSession $s): array
    {
        $slots = []; $start = Carbon::parse($s->session_date->format("Y-m-d")." ".$s->start_time); $end = Carbon::parse($s->session_date->format("Y-m-d")." ".$s->end_time); $dur = $s->slot_duration_minutes;
        while ($start->lt($end)){$slotEnd=$start->copy()->addMinutes($dur);if($slotEnd->gt($end))break;$slots[]=["start_time"=>$start->format("H:i"),"end_time"=>$slotEnd->format("H:i")];$start->addMinutes($dur);}
        return $slots;
    }
}
