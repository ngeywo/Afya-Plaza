<?php

namespace App\Http\Controllers\Api;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceCommandCentreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        return response()->json([
            'health' => $this->marketplaceHealth($today),
            'attention' => $this->attentionQueue(),
            'today' => $this->todayOperations($today),
            'activity' => $this->recentActivity(),
            'meta' => ['generated_at' => now()->toIso8601String(), 'today' => $today],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->marketplaceHealth(now()->toDateString())]);
    }

    public function attention(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->attentionQueue()]);
    }

    public function today(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->todayOperations(now()->toDateString())]);
    }

    private function marketplaceHealth(string $today): array
    {
        $trust = $this->healthTrust();
        $ops = $this->healthOperations($today);
        $bookings = $this->healthBookings($today);
        $finance = $this->healthFinance();
        $overall = 'healthy';
        foreach ([$trust, $ops, $bookings, $finance] as $d) {
            if (($d['status'] ?? '') === 'critical') {
                $overall = 'critical';
                break;
            }
            if (($d['status'] ?? '') === 'warning') {
                $overall = 'warning';
            }
        }

        return ['trust' => $trust, 'operations' => $ops, 'bookings' => $bookings, 'finance' => $finance, 'subscriptions' => ['status' => 'healthy', 'issues' => 0], 'overall' => $overall];
    }

    private function healthTrust(): array
    {
        $pendingDoctors = Doctor::where('verification_status', VerificationStatus::PENDING->value)->count();
        $pendingFacilities = Facility::where('verification_status', VerificationStatus::PENDING->value)->count();
        $verifiedDoctors = Doctor::where('verification_status', VerificationStatus::VERIFIED->value)->count();
        $verifiedFacilities = Facility::where('verification_status', VerificationStatus::VERIFIED->value)->count();
        $status = ($pendingDoctors > 10 || $pendingFacilities > 5) ? 'critical' : (($pendingDoctors > 5 || $pendingFacilities > 3) ? 'warning' : 'healthy');

        return ['status' => $status, 'pending_doctors' => $pendingDoctors, 'pending_facilities' => $pendingFacilities, 'verified_doctors' => $verifiedDoctors, 'verified_facilities' => $verifiedFacilities, 'issues' => $pendingDoctors + $pendingFacilities];
    }

    private function healthOperations(string $today): array
    {
        $unconfirmedToday = ClinicSession::where('session_date', $today)->where('status', '!=', 'confirmed')->whereIn('facility_confirmation', ['pending', null])->count();
        $cancelledWithBookings = ClinicSession::where('status', 'cancelled')->where('cancelled_at', '>=', now()->subDays(7))->whereHas('appointments')->count();
        $inactiveWithSessions = ClinicSession::whereHas('facility', fn ($q) => $q->where('is_active', false))->where('session_date', '>=', $today)->where('status', 'confirmed')->count();
        $issues = $unconfirmedToday + $cancelledWithBookings + $inactiveWithSessions;
        $status = ($inactiveWithSessions > 0 || $cancelledWithBookings > 5) ? 'critical' : ($issues > 3 ? 'warning' : 'healthy');

        return ['status' => $status, 'unconfirmed_today' => $unconfirmedToday, 'cancelled_with_bookings' => $cancelledWithBookings, 'inactive_with_confirmed_sessions' => $inactiveWithSessions, 'issues' => $issues];
    }

    private function healthBookings(string $today): array
    {
        $failedPayments = Payment::whereIn('status', ['pending', 'failed'])->where('created_at', '>=', now()->subDays(7))->count();
        $cancelledAppointments = Appointment::where('appointment_date', '>=', now()->subDays(7))->where('status', 'cancelled')->count();
        $status = $failedPayments > 15 ? 'critical' : ($failedPayments > 5 || $cancelledAppointments > 20 ? 'warning' : 'healthy');

        return ['status' => $status, 'failed_payments_week' => $failedPayments, 'cancelled_appointments_week' => $cancelledAppointments, 'issues' => $failedPayments];
    }

    private function healthFinance(): array
    {
        $pendingPayouts = Payout::whereIn('status', ['requested', 'processing'])->count();
        $failedPayouts = Payout::where('status', 'rejected')->where('rejected_at', '>=', now()->subDays(7))->count();
        $status = $failedPayouts > 5 ? 'critical' : ($pendingPayouts > 10 || $failedPayouts > 0 ? 'warning' : 'healthy');

        return ['status' => $status, 'pending_payouts' => $pendingPayouts, 'failed_payouts_week' => $failedPayouts, 'issues' => $pendingPayouts + $failedPayouts];
    }

    private function attentionQueue(): array
    {
        $critical = $high = $medium = $low = [];

        // CRITICAL: Cancelled clinics with patients
        $cancelled = ClinicSession::with(['doctor:id,display_name', 'facility:id,name'])->where('status', 'cancelled')->where('cancelled_at', '>=', now()->subDays(7))->whereHas('appointments', fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show']))->get();
        foreach ($cancelled as $s) {
            $count = $s->appointments()->whereNotIn('status', ['cancelled', 'no_show'])->count();
            $critical[] = ['type' => 'clinic_cancelled_with_patients', 'title' => 'Cancelled Clinic With Booked Patients', 'doctor' => $s->doctor?->display_name, 'facility' => $s->facility?->name, 'date' => $s->session_date->format('Y-m-d'), 'patients_affected' => $count, 'action_label' => 'Review Appointments', 'action_url' => "/admin/appointments?session_id={$s->id}"];
        }

        // HIGH: Unconfirmed clinics today with bookings
        $unconfirmed = ClinicSession::with(['doctor:id,display_name', 'facility:id,name'])->where('session_date', now()->toDateString())->where('status', 'pending')->whereHas('appointments')->get();
        foreach ($unconfirmed as $s) {
            $count = $s->appointments()->whereNotIn('status', ['cancelled', 'no_show'])->count();
            $high[] = ['type' => 'unconfirmed_clinic_today', 'title' => 'Unconfirmed Clinic Today', 'doctor' => $s->doctor?->display_name, 'facility' => $s->facility?->name, 'time' => substr($s->start_time ?? '', 0, 5), 'patients_booked' => $count, 'action_label' => 'Review Session', 'action_url' => "/admin/sessions/{$s->id}"];
        }

        // HIGH: Inactive facilities with confirmed future sessions
        $inactive = ClinicSession::with(['doctor:id,display_name', 'facility:id,name'])->whereHas('facility', fn ($q) => $q->where('is_active', false))->where('session_date', '>=', now()->toDateString())->where('status', 'confirmed')->limit(10)->get();
        foreach ($inactive as $s) {
            $high[] = ['type' => 'inactive_facility_confirmed_session', 'title' => 'Inactive Facility With Confirmed Session', 'doctor' => $s->doctor?->display_name, 'facility' => $s->facility?->name, 'date' => $s->session_date->format('Y-m-d'), 'action_label' => 'Review Facility', 'action_url' => "/admin/facilities/{$s->facility?->slug}"];
        }

        // MEDIUM: Pending doctor verification
        $pendingDocs = Doctor::with('user:id,name,email')->where('verification_status', VerificationStatus::PENDING->value)->orderBy('created_at')->limit(10)->get();
        foreach ($pendingDocs as $d) {
            $medium[] = ['type' => 'doctor_pending_verification', 'title' => 'Doctor Pending Verification', 'doctor' => $d->display_name, 'email' => $d->user?->email, 'pending_since' => $d->created_at?->diffForHumans(), 'action_label' => 'Review Doctor', 'action_url' => "/admin/doctors/{$d->slug}"];
        }

        // MEDIUM: Pending facility verification
        $pendingFacs = Facility::with('county:id,name')->where('verification_status', VerificationStatus::PENDING->value)->orderBy('created_at')->limit(10)->get();
        foreach ($pendingFacs as $f) {
            $medium[] = ['type' => 'facility_pending_verification', 'title' => 'Facility Pending Verification', 'facility' => $f->name, 'county' => $f->county?->name, 'pending_since' => $f->created_at?->diffForHumans(), 'action_label' => 'Review Facility', 'action_url' => "/admin/facilities/{$f->slug}"];
        }

        // LOW: Failed payments
        $failedPmts = Payment::with(['doctor:id,display_name', 'payer:id,name'])->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->orderByDesc('created_at')->limit(5)->get();
        foreach ($failedPmts as $p) {
            $low[] = ['type' => 'failed_payment', 'title' => 'Failed Payment', 'doctor' => $p->doctor?->display_name, 'patient' => $p->payer?->name, 'amount' => 'KES '.number_format((float) $p->gross_amount, 2), 'reason' => $p->failure_reason, 'action_label' => 'Review Payment', 'action_url' => '/admin/finance/payments?status=failed'];
        }

        // LOW: Pending payouts
        $pendingPayoutsList = Payout::with('doctor:id,display_name')->where('status', 'requested')->orderBy('requested_at')->limit(5)->get();
        foreach ($pendingPayoutsList as $p) {
            $low[] = ['type' => 'pending_payout', 'title' => 'Pending Payout Approval', 'doctor' => $p->doctor?->display_name, 'amount' => 'KES '.number_format((float) $p->amount, 2), 'requested_at' => $p->requested_at?->diffForHumans(), 'action_label' => 'Review Payout', 'action_url' => '/admin/finance/payouts'];
        }

        return ['critical' => ['count' => count($critical), 'items' => array_values($critical)], 'high' => ['count' => count($high), 'items' => array_values($high)], 'medium' => ['count' => count($medium), 'items' => array_values($medium)], 'low' => ['count' => count($low), 'items' => array_values($low)], 'total' => count($critical) + count($high) + count($medium) + count($low)];
    }

    private function todayOperations(string $today): array
    {
        $sessions = ClinicSession::with(['doctor:id,display_name', 'facility:id,name'])->where('session_date', $today)->get();
        $appointments = Appointment::where('appointment_date', $today);

        return [
            'sessions' => ['total' => $sessions->count(), 'confirmed' => $sessions->where('status', 'confirmed')->count(), 'pending' => $sessions->where('status', 'pending')->count(), 'cancelled' => $sessions->where('status', 'cancelled')->count()],
            'appointments' => ['total' => $appointments->count(), 'confirmed' => (clone $appointments)->whereIn('status', ['confirmed', 'checked_in', 'in_progress', 'completed'])->count(), 'completed' => (clone $appointments)->where('status', 'completed')->count()],
            'capacity' => ['available_slots' => $sessions->sum(fn ($s) => $s->available_slots), 'booked' => $sessions->sum('booked_appointments')],
            'facilities_active' => $sessions->pluck('facility_id')->unique()->count(),
            'doctors_practicing' => $sessions->pluck('doctor_id')->unique()->count(),
        ];
    }

    private function recentActivity(): array
    {
        $activities = [];
        foreach (ClinicSession::with(['doctor:id,display_name', 'facility:id,name'])->where('status', 'confirmed')->where('facility_confirmed_at', '>=', now()->subHours(24))->orderByDesc('facility_confirmed_at')->limit(3)->get() as $s) {
            $activities[] = ['icon' => 'mdi-check-circle', 'color' => 'success', 'message' => "{$s->doctor?->display_name} confirmed clinic at {$s->facility?->name} on {$s->session_date->format('M d')}", 'time' => $s->facility_confirmed_at?->diffForHumans(), 'type' => 'session_confirmed'];
        }
        foreach (Appointment::with(['doctor:id,display_name', 'facility:id,name'])->where('created_at', '>=', now()->subHours(24))->whereNotIn('status', ['cancelled'])->orderByDesc('created_at')->limit(5)->get() as $a) {
            $activities[] = ['icon' => 'mdi-calendar-check', 'color' => 'primary', 'message' => "New booking for {$a->doctor?->display_name} at {$a->facility?->name}", 'time' => $a->created_at?->diffForHumans(), 'type' => 'booking_created'];
        }
        foreach (Payment::with(['doctor:id,display_name'])->where('status', 'paid')->where('confirmed_at', '>=', now()->subHours(24))->orderByDesc('confirmed_at')->limit(3)->get() as $p) {
            $activities[] = ['icon' => 'mdi-cash-check', 'color' => 'success', 'message' => 'Payment of KES '.number_format((float) $p->gross_amount, 0)." for {$p->doctor?->display_name}", 'time' => $p->confirmed_at?->diffForHumans(), 'type' => 'payment_completed'];
        }

        return array_slice($activities, 0, 10);
    }
}
