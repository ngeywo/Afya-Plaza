<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $totalDoctors = Doctor::where('is_active', true)->count();
        $totalFacilities = Facility::where('is_active', true)->count();
        $totalUsers = User::where('is_active', true)->count();
        $todaysSessions = ClinicSession::whereDate('session_date', $today)->count();
        $todaysAppointments = Appointment::whereDate('appointment_date', $today)->count();
        $pendingDoctorVerification = Doctor::where('verification_status', VerificationStatus::PENDING->value)->where('is_active', true)->count();
        $pendingFacilityVerification = Facility::where('verification_status', VerificationStatus::PENDING->value)->where('is_active', true)->count();
        $pendingDoctorConfirmations = ClinicSession::where('doctor_confirmation', 'pending')->where('status', 'pending')->whereDate('session_date', '>=', $today)->count();
        $pendingFacilityConfirmations = ClinicSession::where('facility_confirmation', 'pending')->where('status', 'pending')->whereDate('session_date', '>=', $today)->count();
        $problemAppointments = Appointment::whereDate('appointment_date', $today)->whereIn('status', ['cancelled', 'no_show'])->count();
        $facilitiesToday = ClinicSession::whereDate('session_date', $today)->select('facility_id', DB::raw('COUNT(DISTINCT doctor_id) as doctor_count'), DB::raw('COUNT(*) as session_count'))->with('facility:id,name,city')->groupBy('facility_id')->orderByDesc('session_count')->limit(5)->get()->map(fn($row) => ['id' => $row->facility_id, 'name' => $row->facility?->name, 'city' => $row->facility?->city, 'doctor_count' => (int) $row->doctor_count, 'session_count' => (int) $row->session_count]);
        return response()->json(['data' => ['counts' => ['total_doctors' => $totalDoctors, 'total_facilities' => $totalFacilities, 'total_users' => $totalUsers, 'todays_sessions' => $todaysSessions, 'todays_appointments' => $todaysAppointments, 'pending_doctor_verification' => $pendingDoctorVerification, 'pending_facility_verification' => $pendingFacilityVerification, 'pending_doctor_confirmations' => $pendingDoctorConfirmations, 'pending_facility_confirmations' => $pendingFacilityConfirmations, 'problem_appointments' => $problemAppointments], 'facilities_today' => $facilitiesToday]]);
    }
    public function verifyDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        $before = ['is_verified' => $doctor->is_verified, 'verification_status' => $doctor->verification_status?->value];
        $doctor->update(['is_verified' => true, 'verified_at' => now(), 'verified_by' => $request->user()->id, 'verification_status' => VerificationStatus::VERIFIED->value, 'rejection_reason' => null, 'rejection_notes' => null]);
        AuditLog::record($request->user()->id, 'doctor.verified', 'doctor', $doctor->id, $doctor->display_name, $before, ['is_verified' => true, 'verification_status' => VerificationStatus::VERIFIED->value], $request->input('reason'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Doctor verified.', 'data' => ['id' => $doctor->id, 'is_verified' => true, 'verification_status' => VerificationStatus::VERIFIED->value]]);
    }
    public function rejectDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $before = ['is_verified' => $doctor->is_verified, 'verification_status' => $doctor->verification_status?->value];
        $doctor->update(['is_verified' => false, 'verification_status' => VerificationStatus::REJECTED->value, 'rejection_reason' => $request->input('reason'), 'rejection_notes' => $request->input('notes')]);
        AuditLog::record($request->user()->id, 'doctor.rejected', 'doctor', $doctor->id, $doctor->display_name, $before, ['verification_status' => VerificationStatus::REJECTED->value, 'rejection_reason' => $request->input('reason')], $request->input('reason'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Doctor rejected.', 'data' => ['id' => $doctor->id, 'verification_status' => VerificationStatus::REJECTED->value]]);
    }
    public function suspendDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $before = ['verification_status' => $doctor->verification_status?->value];
        $doctor->update(['verification_status' => VerificationStatus::SUSPENDED->value, 'suspended_at' => now(), 'suspended_by' => $request->user()->id, 'suspension_reason' => $request->input('reason')]);
        AuditLog::record($request->user()->id, 'doctor.suspended', 'doctor', $doctor->id, $doctor->display_name, $before, ['verification_status' => VerificationStatus::SUSPENDED->value, 'suspension_reason' => $request->input('reason')], $request->input('reason'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Doctor suspended.', 'data' => ['id' => $doctor->id, 'verification_status' => VerificationStatus::SUSPENDED->value]]);
    }
    public function unsuspendDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        if (!$doctor->isSuspended()) return response()->json(['message' => 'Doctor is not suspended.'], 422);
        $before = ['verification_status' => $doctor->verification_status?->value];
        $doctor->update(['verification_status' => VerificationStatus::VERIFIED->value, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null]);
        AuditLog::record($request->user()->id, 'doctor.unsuspended', 'doctor', $doctor->id, $doctor->display_name, $before, ['verification_status' => VerificationStatus::VERIFIED->value], $request->input('reason', 'Unsuspension'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Doctor suspension lifted.', 'data' => ['id' => $doctor->id, 'verification_status' => VerificationStatus::VERIFIED->value]]);
    }
    public function unverifyDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        $before = ['is_verified' => $doctor->is_verified, 'verification_status' => $doctor->verification_status?->value];
        $doctor->update(['is_verified' => false, 'verified_at' => null, 'verification_status' => VerificationStatus::PENDING->value]);
        AuditLog::record($request->user()->id, 'doctor.unverified', 'doctor', $doctor->id, $doctor->display_name, $before, ['verification_status' => VerificationStatus::PENDING->value], null, $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Doctor verification reset.', 'data' => ['id' => $doctor->id, 'verification_status' => VerificationStatus::PENDING->value]]);
    }
    public function verifyFacility(Request $request, Facility $facility): JsonResponse
    {
        $before = ['is_verified' => $facility->is_verified, 'verification_status' => $facility->verification_status?->value];
        $facility->update(['is_verified' => true, 'verified_at' => now(), 'verification_status' => VerificationStatus::VERIFIED->value, 'rejection_reason' => null, 'rejection_notes' => null]);
        AuditLog::record($request->user()->id, 'facility.verified', 'facility', $facility->id, $facility->name, $before, ['is_verified' => true, 'verification_status' => VerificationStatus::VERIFIED->value], $request->input('reason'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Facility verified.', 'data' => ['id' => $facility->id, 'is_verified' => true, 'verification_status' => VerificationStatus::VERIFIED->value]]);
    }
    public function rejectFacility(Request $request, Facility $facility): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $before = ['is_verified' => $facility->is_verified, 'verification_status' => $facility->verification_status?->value];
        $facility->update(['is_verified' => false, 'verification_status' => VerificationStatus::REJECTED->value, 'rejection_reason' => $request->input('reason'), 'rejection_notes' => $request->input('notes')]);
        AuditLog::record($request->user()->id, 'facility.rejected', 'facility', $facility->id, $facility->name, $before, ['verification_status' => VerificationStatus::REJECTED->value, 'rejection_reason' => $request->input('reason')], $request->input('reason'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Facility rejected.', 'data' => ['id' => $facility->id, 'verification_status' => VerificationStatus::REJECTED->value]]);
    }
    public function suspendFacility(Request $request, Facility $facility): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $before = ['verification_status' => $facility->verification_status?->value];
        $facility->update(['verification_status' => VerificationStatus::SUSPENDED->value, 'suspended_at' => now(), 'suspended_by' => $request->user()->id, 'suspension_reason' => $request->input('reason')]);
        AuditLog::record($request->user()->id, 'facility.suspended', 'facility', $facility->id, $facility->name, $before, ['verification_status' => VerificationStatus::SUSPENDED->value, 'suspension_reason' => $request->input('reason')], $request->input('reason'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Facility suspended.', 'data' => ['id' => $facility->id, 'verification_status' => VerificationStatus::SUSPENDED->value]]);
    }
    public function unsuspendFacility(Request $request, Facility $facility): JsonResponse
    {
        if (!$facility->isSuspended()) return response()->json(['message' => 'Facility is not suspended.'], 422);
        $before = ['verification_status' => $facility->verification_status?->value];
        $facility->update(['verification_status' => VerificationStatus::VERIFIED->value, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null]);
        AuditLog::record($request->user()->id, 'facility.unsuspended', 'facility', $facility->id, $facility->name, $before, ['verification_status' => VerificationStatus::VERIFIED->value], $request->input('reason', 'Unsuspension'), $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Facility suspension lifted.', 'data' => ['id' => $facility->id, 'verification_status' => VerificationStatus::VERIFIED->value]]);
    }
    public function unverifyFacility(Request $request, Facility $facility): JsonResponse
    {
        $before = ['is_verified' => $facility->is_verified, 'verification_status' => $facility->verification_status?->value];
        $facility->update(['is_verified' => false, 'verified_at' => null, 'verification_status' => VerificationStatus::PENDING->value]);
        AuditLog::record($request->user()->id, 'facility.unverified', 'facility', $facility->id, $facility->name, $before, ['verification_status' => VerificationStatus::PENDING->value], null, $request->ip(), $request->userAgent());
        return response()->json(['message' => 'Facility verification reset.', 'data' => ['id' => $facility->id, 'verification_status' => VerificationStatus::PENDING->value]]);
    }
    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate(['resource_type' => 'nullable|in:doctor,facility,doctor_facility,clinic_session', 'resource_id' => 'nullable|integer', 'action' => 'nullable|string|max:100', 'actor_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date', 'per_page' => 'nullable|integer|min:1|max:100']);
        $perPage = (int) $request->input('per_page', 25);
        $logs = AuditLog::with('actor:id,name,email')
            ->when($request->resource_type, fn($q) => $q->where('resource_type', $request->resource_type))
            ->when($request->resource_id, fn($q) => $q->where('resource_id', $request->resource_id))
            ->when($request->action, fn($q) => $q->where('action', $request->action))
            ->when($request->actor_id, fn($q) => $q->where('actor_id', $request->actor_id))
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')->paginate($perPage);
        return response()->json(['data' => $logs->getCollection()->map(fn($log) => ['id' => $log->id, 'action' => $log->action, 'resource_type' => $log->resource_type, 'resource_id' => $log->resource_id, 'resource_label' => $log->resource_label, 'before' => $log->before, 'after' => $log->after, 'reason' => $log->reason, 'actor' => $log->actor ? ['id' => $log->actor->id, 'name' => $log->actor->name] : null, 'created_at' => $log->created_at?->toIso8601String()])->values(), 'meta' => ['total' => $logs->total(), 'per_page' => $logs->perPage(), 'current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage()]]);
    }
    public function pendingDoctors(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $doctors = Doctor::with(['user:id,name,email', 'specialties:id,name'])->where('verification_status', VerificationStatus::PENDING->value)->where('is_active', true)->orderByDesc('created_at')->paginate($perPage);
        return response()->json(['data' => $doctors->getCollection()->map(fn($d) => ['id' => $d->id, 'display_name' => $d->display_name, 'qualifications' => $d->qualifications, 'years_of_experience' => $d->years_of_experience, 'consultation_fee' => $d->consultation_fee, 'verification_status' => $d->verification_status?->value, 'rejection_reason' => $d->rejection_reason, 'suspended_at' => $d->suspended_at?->toIso8601String(), 'created_at' => $d->created_at?->toIso8601String(), 'user' => $d->user ? ['id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email] : null, 'specialties' => $d->specialties->map(fn($s) => ['id' => $s->id, 'name' => $s->name])])->values(), 'meta' => ['total' => $doctors->total(), 'per_page' => $doctors->perPage(), 'current_page' => $doctors->currentPage(), 'last_page' => $doctors->lastPage()]]);
    }
    public function pendingFacilities(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $facilities = Facility::withCount(['admins'])->where('verification_status', VerificationStatus::PENDING->value)->where('is_active', true)->orderByDesc('created_at')->paginate($perPage);
        return response()->json(['data' => $facilities->getCollection()->map(fn($f) => ['id' => $f->id, 'name' => $f->name, 'slug' => $f->slug, 'type' => $f->type, 'city' => $f->city, 'address' => $f->address, 'phone' => $f->phone, 'email' => $f->email, 'verification_status' => $f->verification_status?->value, 'rejection_reason' => $f->rejection_reason, 'suspended_at' => $f->suspended_at?->toIso8601String(), 'admin_count' => $f->admins_count, 'created_at' => $f->created_at?->toIso8601String()])->values(), 'meta' => ['total' => $facilities->total(), 'per_page' => $facilities->perPage(), 'current_page' => $facilities->currentPage(), 'last_page' => $facilities->lastPage()]]);
    }
}
