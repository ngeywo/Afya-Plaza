<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Facility;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWorkspaceController extends Controller
{
    // ─── Doctor workspace inspection ───────────────────────────────────────────

    public function doctorDashboard(Request $request, Doctor $doctor): JsonResponse
    {
        if (!$doctor->is_active) {
            return response()->json(["message" => "Doctor account is inactive."], 403);
        }

        $today = Carbon::today();
        $twoWeeks = $today->copy()->addDays(14);

        $todaySessions = ClinicSession::with(["facility:id,name,city,type"])
            ->where("doctor_id", $doctor->id)
            ->whereDate("session_date", $today)
            ->whereIn("status", ["pending", "confirmed"])
            ->orderBy("start_time")->get();

        $todayAppointmentCount = Appointment::where("doctor_id", $doctor->id)
            ->whereDate("appointment_date", $today)
            ->whereIn("status", ["pending", "confirmed"])->count();

        $upcomingSessionCount = ClinicSession::where("doctor_id", $doctor->id)
            ->whereBetween("session_date", [$today->copy()->addDay()->toDateString(), $twoWeeks->toDateString()])
            ->whereIn("status", ["pending", "confirmed"])->count();

        $pendingConfirmations = ClinicSession::where("doctor_id", $doctor->id)
            ->where("session_date", ">=", $today->toDateString())
            ->where("doctor_confirmation", "pending")
            ->where("status", "pending")->count();

        $todayAppointments = [];
        if ($todaySessions->isNotEmpty()) {
            $todayAppointments = Appointment::with(["user:id,name,email,phone", "facility:id,name"])
                ->where("doctor_id", $doctor->id)
                ->whereDate("appointment_date", $today)
                ->whereIn("status", ["pending", "confirmed"])
                ->orderBy("start_time")->limit(15)->get()
                ->map(fn($a) => [
                    "id" => $a->id, "status" => $a->status,
                    "appointment_date" => $a->appointment_date instanceof Carbon ? $a->appointment_date->format("Y-m-d") : $a->appointment_date,
                    "start_time" => substr($a->start_time, 0, 5), "end_time" => substr($a->end_time, 0, 5),
                    "reason" => $a->reason, "amount_paid" => $a->amount_paid, "payment_status" => $a->payment_status,
                    "patient" => $a->user ? ["id" => $a->user->id, "name" => $a->user->name, "email" => $a->user->email, "phone" => $a->user->phone] : null,
                    "facility" => $a->facility ? ["id" => $a->facility->id, "name" => $a->facility->name] : null,
                ]);
        }

        $upcomingSessions = ClinicSession::with(["facility:id,name,city"])
            ->where("doctor_id", $doctor->id)
            ->whereBetween("session_date", [$today->copy()->addDay()->toDateString(), $twoWeeks->toDateString()])
            ->whereIn("status", ["pending", "confirmed"])
            ->orderBy("session_date")->orderBy("start_time")->limit(8)->get()
            ->map(fn($s) => [
                "id" => $s->id,
                "session_date" => $s->session_date instanceof Carbon ? $s->session_date->format("Y-m-d") : $s->session_date,
                "day" => $s->session_date instanceof Carbon ? $s->session_date->format("l, M j") : "",
                "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5),
                "status" => $s->status, "doctor_confirmation" => $s->doctor_confirmation,
                "facility_confirmation" => $s->facility_confirmation, "is_confirmed" => $s->is_confirmed,
                "facility" => $s->facility ? ["id" => $s->facility->id, "name" => $s->facility->name, "city" => $s->facility->city] : null,
                "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments,
                "available_slots" => $s->available_slots,
            ]);

        return response()->json(["data" => [
            "workspace_type" => "doctor",
            "doctor" => ["id" => $doctor->id, "name" => $doctor->display_name, "avatar" => $doctor->avatar,
                "is_verified" => $doctor->is_verified, "verified_at" => $doctor->verified_at?->toIso8601String()],
            "today" => [
                "sessions" => $todaySessions->map(fn($s) => [
                    "id" => $s->id,
                    "session_date" => $s->session_date instanceof Carbon ? $s->session_date->format("Y-m-d") : $s->session_date,
                    "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5),
                    "status" => $s->status, "is_confirmed" => $s->is_confirmed,
                    "facility_confirmation" => $s->facility_confirmation,
                    "facility" => $s->facility ? ["id" => $s->facility->id, "name" => $s->facility->name, "city" => $s->facility->city] : null,
                    "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments,
                    "available_slots" => $s->available_slots, "consultation_fee" => $s->consultation_fee,
                ]),
                "appointment_count" => $todayAppointmentCount,
            ],
            "counts" => ["upcoming_sessions" => $upcomingSessionCount, "pending_confirmations" => $pendingConfirmations],
            "upcoming_sessions" => $upcomingSessions, "today_appointments" => $todayAppointments,
        ]]);
    }

    public function doctorSessions(Request $request, Doctor $doctor): JsonResponse
    {
        $from = $request->input("from", Carbon::today()->subMonth()->toDateString());
        $to = $request->input("to", Carbon::today()->addMonth()->toDateString());
        $status = $request->input("status", "all");
        $perPage = (int) $request->input("per_page", 20);

        $sessions = ClinicSession::with(["facility:id,name,city"])
            ->where("doctor_id", $doctor->id)
            ->whereBetween("session_date", [$from, $to])
            ->when($status !== "all", fn($q) => $q->where("status", $status))
            ->orderByDesc("session_date")->orderBy("start_time")
            ->paginate($perPage);

        return response()->json([
            "data" => $sessions->getCollection()->map(fn($s) => [
                "id" => $s->id,
                "session_date" => $s->session_date instanceof Carbon ? $s->session_date->format("Y-m-d") : $s->session_date,
                "day" => $s->session_date instanceof Carbon ? $s->session_date->format("l, M j") : "",
                "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5),
                "status" => $s->status, "doctor_confirmation" => $s->doctor_confirmation,
                "facility_confirmation" => $s->facility_confirmation, "is_confirmed" => $s->is_confirmed,
                "facility" => $s->facility ? ["id" => $s->facility->id, "name" => $s->facility->name, "city" => $s->facility->city] : null,
                "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments,
                "available_slots" => $s->available_slots, "consultation_fee" => $s->consultation_fee,
            ])->values(),
            "meta" => ["total" => $sessions->total(), "per_page" => $sessions->perPage(),
                "current_page" => $sessions->currentPage(), "last_page" => $sessions->lastPage()],
        ]);
    }

    public function doctorAppointments(Request $request, Doctor $doctor): JsonResponse
    {
        $from = $request->input("from", Carbon::today()->subMonth()->toDateString());
        $to = $request->input("to", Carbon::today()->addMonth()->toDateString());
        $status = $request->input("status", "all");
        $perPage = (int) $request->input("per_page", 20);

        $appointments = Appointment::with(["user:id,name,email,phone", "facility:id,name"])
            ->where("doctor_id", $doctor->id)
            ->whereBetween("appointment_date", [$from, $to])
            ->when($status !== "all", fn($q) => $q->where("status", $status))
            ->orderByDesc("appointment_date")->orderBy("start_time")
            ->paginate($perPage);

        return response()->json([
            "data" => $appointments->getCollection()->map(fn($a) => [
                "id" => $a->id, "appointment_number" => $a->appointment_number, "status" => $a->status,
                "appointment_date" => $a->appointment_date instanceof Carbon ? $a->appointment_date->format("Y-m-d") : $a->appointment_date,
                "start_time" => substr($a->start_time, 0, 5), "end_time" => substr($a->end_time, 0, 5),
                "reason" => $a->reason, "notes" => $a->notes,
                "amount_paid" => $a->amount_paid, "payment_status" => $a->payment_status,
                "patient" => $a->user ? ["id" => $a->user->id, "name" => $a->user->name, "email" => $a->user->email, "phone" => $a->user->phone] : null,
                "facility" => $a->facility ? ["id" => $a->facility->id, "name" => $a->facility->name] : null,
            ])->values(),
            "meta" => ["total" => $appointments->total(), "per_page" => $appointments->perPage(),
                "current_page" => $appointments->currentPage(), "last_page" => $appointments->lastPage()],
        ]);
    }

    public function doctorProfile(Doctor $doctor): JsonResponse
    {
        $doctor->load(["user.roles", "specialties:id,name", "facilities:id,name,slug,city,type"]);

        return response()->json(["data" => [
            "id" => $doctor->id, "display_name" => $doctor->display_name,
            "avatar" => $doctor->avatar, "gender" => $doctor->gender,
            "qualifications" => $doctor->qualifications,
            "biography" => $doctor->biography,
            "years_of_experience" => $doctor->years_of_experience,
            "consultation_fee" => $doctor->consultation_fee,
            "is_active" => $doctor->is_active, "is_verified" => $doctor->is_verified,
            "verification_status" => $doctor->verification_status?->value,
            "verification_status_label" => $doctor->verification_status?->label(),
            "rejection_reason" => $doctor->rejection_reason,
            "suspension_reason" => $doctor->suspension_reason,
            "is_trustworthy" => $doctor->isTrustworthy(),
            "is_bookable" => $doctor->isBookable(),
            "is_suspended" => $doctor->isSuspended(),
            "verified_at" => $doctor->verified_at?->toIso8601String(),
            "created_at" => $doctor->created_at?->toIso8601String(),
            "user" => $doctor->user ? [
                "id" => $doctor->user->id, "name" => $doctor->user->name,
                "email" => $doctor->user->email, "phone" => $doctor->user->phone,
                "is_active" => $doctor->user->is_active,
                "roles" => $doctor->user->roles->pluck("slug"),
            ] : null,
            "specialties" => $doctor->specialties->map(fn($s) => ["id" => $s->id, "name" => $s->name]),
            "facilities" => $doctor->facilities->map(fn($f) => ["id" => $f->id, "name" => $f->name, "slug" => $f->slug, "city" => $f->city, "type" => $f->type]),
        ]]);
    }

    // ─── Facility workspace inspection ─────────────────────────────────────────

    public function facilityDashboard(Request $request, Facility $facility): JsonResponse
    {
        if (!$facility->is_active) {
            return response()->json(["message" => "Facility account is inactive."], 403);
        }

        $today = Carbon::today();
        $twoWeeks = $today->copy()->addDays(14);

        $todaySessions = ClinicSession::with(["doctor:id,display_name,avatar"])
            ->where("facility_id", $facility->id)
            ->whereDate("session_date", $today)
            ->whereIn("status", ["pending", "confirmed"])
            ->orderBy("start_time")->get();

        $todayAppointmentCount = Appointment::where("facility_id", $facility->id)
            ->whereDate("appointment_date", $today)
            ->whereIn("status", ["pending", "confirmed"])->count();

        $pendingConfirmations = ClinicSession::where("facility_id", $facility->id)
            ->where("session_date", ">=", $today->toDateString())
            ->where("facility_confirmation", "pending")
            ->where("status", "pending")->count();

        $activeDoctors = ClinicSession::where("facility_id", $facility->id)
            ->whereDate("session_date", $today)
            ->whereIn("status", ["pending", "confirmed"])
            ->distinct("doctor_id")->count("doctor_id");

        $upcomingSessions = ClinicSession::with(["doctor:id,display_name,avatar"])
            ->where("facility_id", $facility->id)
            ->whereBetween("session_date", [$today->copy()->addDay()->toDateString(), $twoWeeks->toDateString()])
            ->whereIn("status", ["pending", "confirmed"])
            ->orderBy("session_date")->orderBy("start_time")->limit(8)->get()
            ->map(fn($s) => [
                "id" => $s->id,
                "session_date" => $s->session_date instanceof Carbon ? $s->session_date->format("Y-m-d") : $s->session_date,
                "day" => $s->session_date instanceof Carbon ? $s->session_date->format("l, M j") : "",
                "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5),
                "status" => $s->status, "facility_confirmation" => $s->facility_confirmation,
                "doctor_confirmation" => $s->doctor_confirmation, "is_confirmed" => $s->is_confirmed,
                "doctor" => $s->doctor ? ["id" => $s->doctor->id, "name" => $s->doctor->display_name, "avatar" => $s->doctor->avatar] : null,
                "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments,
                "available_slots" => $s->available_slots, "consultation_fee" => $s->consultation_fee,
            ]);

        $todayAppointments = Appointment::with(["user:id,name,phone", "doctor:id,display_name"])
            ->where("facility_id", $facility->id)
            ->whereDate("appointment_date", $today)
            ->whereIn("status", ["pending", "confirmed"])
            ->orderBy("start_time")->limit(15)->get()
            ->map(fn($a) => [
                "id" => $a->id, "status" => $a->status,
                "appointment_date" => $a->appointment_date instanceof Carbon ? $a->appointment_date->format("Y-m-d") : $a->appointment_date,
                "start_time" => substr($a->start_time, 0, 5), "end_time" => substr($a->end_time, 0, 5),
                "reason" => $a->reason, "payment_status" => $a->payment_status,
                "patient" => $a->user ? ["id" => $a->user->id, "name" => $a->user->name, "phone" => $a->user->phone] : null,
                "doctor" => $a->doctor ? ["id" => $a->doctor->id, "name" => $a->doctor->display_name] : null,
            ]);

        return response()->json(["data" => [
            "workspace_type" => "facility",
            "facility" => ["id" => $facility->id, "name" => $facility->name, "type" => $facility->type,
                "city" => $facility->city, "is_verified" => $facility->is_verified,
                "verified_at" => $facility->verified_at?->toIso8601String()],
            "today" => [
                "sessions" => $todaySessions->map(fn($s) => [
                    "id" => $s->id,
                    "session_date" => $s->session_date instanceof Carbon ? $s->session_date->format("Y-m-d") : $s->session_date,
                    "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5),
                    "status" => $s->status, "is_confirmed" => $s->is_confirmed,
                    "facility_confirmation" => $s->facility_confirmation, "doctor_confirmation" => $s->doctor_confirmation,
                    "doctor" => $s->doctor ? ["id" => $s->doctor->id, "name" => $s->doctor->display_name] : null,
                    "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments,
                    "available_slots" => $s->available_slots, "consultation_fee" => $s->consultation_fee,
                ]),
                "appointment_count" => $todayAppointmentCount, "active_doctor_count" => $activeDoctors,
            ],
            "counts" => ["pending_confirmations" => $pendingConfirmations],
            "upcoming_sessions" => $upcomingSessions, "today_appointments" => $todayAppointments,
        ]]);
    }

    public function facilitySessions(Request $request, Facility $facility): JsonResponse
    {
        $from = $request->input("from", Carbon::today()->subMonth()->toDateString());
        $to = $request->input("to", Carbon::today()->addMonth()->toDateString());
        $status = $request->input("status", "all");
        $perPage = (int) $request->input("per_page", 20);

        $sessions = ClinicSession::with(["doctor:id,display_name,avatar"])
            ->where("facility_id", $facility->id)
            ->whereBetween("session_date", [$from, $to])
            ->when($status !== "all", fn($q) => $q->where("status", $status))
            ->orderByDesc("session_date")->orderBy("start_time")
            ->paginate($perPage);

        return response()->json([
            "data" => $sessions->getCollection()->map(fn($s) => [
                "id" => $s->id,
                "session_date" => $s->session_date instanceof Carbon ? $s->session_date->format("Y-m-d") : $s->session_date,
                "day" => $s->session_date instanceof Carbon ? $s->session_date->format("l, M j") : "",
                "start_time" => substr($s->start_time, 0, 5), "end_time" => substr($s->end_time, 0, 5),
                "status" => $s->status, "facility_confirmation" => $s->facility_confirmation,
                "doctor_confirmation" => $s->doctor_confirmation, "is_confirmed" => $s->is_confirmed,
                "doctor" => $s->doctor ? ["id" => $s->doctor->id, "name" => $s->doctor->display_name, "avatar" => $s->doctor->avatar] : null,
                "max_appointments" => $s->max_appointments, "booked_appointments" => $s->booked_appointments,
                "available_slots" => $s->available_slots, "consultation_fee" => $s->consultation_fee,
            ])->values(),
            "meta" => ["total" => $sessions->total(), "per_page" => $sessions->perPage(),
                "current_page" => $sessions->currentPage(), "last_page" => $sessions->lastPage()],
        ]);
    }

    public function facilityAppointments(Request $request, Facility $facility): JsonResponse
    {
        $from = $request->input("from", Carbon::today()->subMonth()->toDateString());
        $to = $request->input("to", Carbon::today()->addMonth()->toDateString());
        $status = $request->input("status", "all");
        $perPage = (int) $request->input("per_page", 20);

        $appointments = Appointment::with(["user:id,name,email,phone", "doctor:id,display_name"])
            ->where("facility_id", $facility->id)
            ->whereBetween("appointment_date", [$from, $to])
            ->when($status !== "all", fn($q) => $q->where("status", $status))
            ->orderByDesc("appointment_date")->orderBy("start_time")
            ->paginate($perPage);

        return response()->json([
            "data" => $appointments->getCollection()->map(fn($a) => [
                "id" => $a->id, "appointment_number" => $a->appointment_number, "status" => $a->status,
                "appointment_date" => $a->appointment_date instanceof Carbon ? $a->appointment_date->format("Y-m-d") : $a->appointment_date,
                "start_time" => substr($a->start_time, 0, 5), "end_time" => substr($a->end_time, 0, 5),
                "reason" => $a->reason, "notes" => $a->notes,
                "amount_paid" => $a->amount_paid, "payment_status" => $a->payment_status,
                "patient" => $a->user ? ["id" => $a->user->id, "name" => $a->user->name, "email" => $a->user->email, "phone" => $a->user->phone] : null,
                "doctor" => $a->doctor ? ["id" => $a->doctor->id, "name" => $a->doctor->display_name] : null,
            ])->values(),
            "meta" => ["total" => $appointments->total(), "per_page" => $appointments->perPage(),
                "current_page" => $appointments->currentPage(), "last_page" => $appointments->lastPage()],
        ]);
    }

    public function facilityProfile(Facility $facility): JsonResponse
    {
        $facility->load(["admins:id,name,email"]);

        return response()->json(["data" => [
            "id" => $facility->id, "name" => $facility->name, "slug" => $facility->slug,
            "type" => $facility->type, "city" => $facility->city, "address" => $facility->address,
            "phone" => $facility->phone, "email" => $facility->email, "website" => $facility->website,
            "operating_hours" => $facility->operating_hours,
            "admins" => $facility->admins->map(fn($a) => ["id" => $a->id, "name" => $a->name, "email" => $a->email]),
            "is_active" => $facility->is_active, "is_verified" => $facility->is_verified,
            "verification_status" => $facility->verification_status?->value,
            "verification_status_label" => $facility->verification_status?->label(),
            "rejection_reason" => $facility->rejection_reason,
            "suspension_reason" => $facility->suspension_reason,
            "is_trustworthy" => $facility->isTrustworthy(),
            "is_operational" => $facility->isOperational(),
            "is_suspended" => $facility->isSuspended(),
            "verified_at" => $facility->verified_at?->toIso8601String(),
            "created_at" => $facility->created_at?->toIso8601String(),
        ]]);
    }
}
