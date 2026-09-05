<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentOperationsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicDayController;
use App\Http\Controllers\Api\CountyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorFollowController;
use App\Http\Controllers\Api\DoctorWorkspaceController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\FacilityWorkspaceController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PatientDiscoveryController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SessionSlotController;
use App\Http\Controllers\Api\SpecialtyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $r) => $r->user());

// Public routes
Route::get('/specialties', [SpecialtyController::class, 'index']);
Route::get('/counties', [CountyController::class, 'index']);
Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/facilities/{slug}', [FacilityController::class, 'show']);
Route::get('/doctors/search', [PatientDiscoveryController::class, 'search']);
Route::get('/discovery/doctors', [PatientDiscoveryController::class, 'search']); // Phase 15 alias
Route::get('/doctors/{slug}/profile', [PatientDiscoveryController::class, 'profile']);
Route::get('/doctors/{slug}/sessions', [PatientDiscoveryController::class, 'sessions']);
Route::get('/doctors', [DoctorController::class, 'index']);
Route::get('/doctors/{slug}', [DoctorController::class, 'show']);
Route::get('/doctors/{id}/sessions', [DoctorController::class, 'sessions']);
Route::get('/doctors/{id}/availability', [SessionController::class, 'doctorAvailability']);
Route::get('/sessions/search', [SessionController::class, 'search']);
Route::get('/sessions/{id}', [PatientDiscoveryController::class, 'showSession']);
Route::get('/sessions/{id}/slots', [SessionSlotController::class, 'index']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{id}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
    // Phase 12: Payments
    // Phase 12: Payments
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    // M-Pesa STK status poll (fallback if callback URL unreachable)
    Route::get('/v1/mpesa/stk/status/{paymentId}', [MpesaController::class, 'stkStatus']);
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    // Phase 11: Clinic Day
    Route::post('/appointments/{appointment}/check-in', [AppointmentOperationsController::class, 'checkIn']);
    Route::post('/appointments/{appointment}/start', [AppointmentOperationsController::class, 'start']);
    Route::post('/appointments/{appointment}/complete', [AppointmentOperationsController::class, 'complete']);
    Route::post('/appointments/{appointment}/no-show', [AppointmentOperationsController::class, 'noShow']);
    Route::post('/appointments/{appointment}/facility-cancel', [AppointmentOperationsController::class, 'facilityCancel']);
    Route::post('/appointments/{appointment}/doctor-cancel', [AppointmentOperationsController::class, 'doctorCancel']); // Phase 17
    Route::post('/appointments/{appointment}/patient-cancel', [AppointmentOperationsController::class, 'patientCancel']); // Phase 18
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-read/{id}', [NotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    // Doctor follow
    Route::post('/doctors/{doctor}/follow', [DoctorFollowController::class, 'follow']);
    Route::delete('/doctors/{doctor}/follow', [DoctorFollowController::class, 'unfollow']);
    Route::get('/my/doctors', [NotificationController::class, 'followingDoctors']);
    // Doctor workspace
    Route::get('/doctor/dashboard', [DoctorWorkspaceController::class, 'dashboard']);
    Route::get('/doctor/clinics', [DoctorWorkspaceController::class, 'clinics']);
    Route::get('/doctor/schedule', [DoctorWorkspaceController::class, 'schedule']);
    Route::get('/doctor/appointments', [DoctorWorkspaceController::class, 'appointments']);
    Route::get('/doctor/appointments/{id}', [DoctorWorkspaceController::class, 'showAppointment']);
    Route::get('/doctor/profile', [DoctorWorkspaceController::class, 'profile']);
    Route::put('/doctor/profile', [DoctorWorkspaceController::class, 'updateProfile']);
    Route::get('/doctor/facilities', [DoctorWorkspaceController::class, 'facilities']);
    Route::get('/doctor/clinic-day', [ClinicDayController::class, 'doctorBoard']);
    // Phase 12: Doctor Earnings
    Route::get('/finance/earnings', [FinanceController::class, 'earnings']);
    Route::get('/finance/earnings/{id}', [FinanceController::class, 'earningDetail']);
    Route::post('/finance/payout-request', [FinanceController::class, 'requestPayout']);
    // Sessions
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::put('/sessions/{id}/confirm', [SessionController::class, 'confirm']);
    Route::put('/sessions/{id}/cancel', [SessionController::class, 'cancel']);
    Route::put('/sessions/{id}', [SessionController::class, 'update']);
    // Facility workspace
    // Facility workspace
    Route::prefix('facility')->group(function () {
        Route::get('/dashboard', [FacilityWorkspaceController::class, 'dashboard']);
        Route::get('/doctors', [FacilityWorkspaceController::class, 'doctors']);
        Route::get('/clinic-sessions', [FacilityWorkspaceController::class, 'clinicSessions']);
        Route::put('/clinic-sessions/{id}/confirm', [FacilityWorkspaceController::class, 'confirmSession']);
        Route::put('/clinic-sessions/{id}/reject', [FacilityWorkspaceController::class, 'rejectSession']);
        Route::get('/appointments', [FacilityWorkspaceController::class, 'appointments']);
        Route::get('/appointments/{id}', [FacilityWorkspaceController::class, 'showAppointment']);
        Route::get('/locations', [FacilityWorkspaceController::class, 'locations']);
        Route::post('/locations', [FacilityWorkspaceController::class, 'storeLocation']);
        Route::get('/staff', [FacilityWorkspaceController::class, 'staff']);
        Route::get('/profile', [FacilityWorkspaceController::class, 'profile']);
        Route::put('/profile', [FacilityWorkspaceController::class, 'updateProfile']);
        Route::get('/clinic-day', [ClinicDayController::class, 'facilityBoard']);
    });
    // Phase 7: Super Admin Control Centre
    Route::middleware('ensure.super.admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'dashboard']);
        Route::get('/verifications/doctors', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'pendingDoctors']);
        Route::get('/verifications/facilities', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'pendingFacilities']);
        Route::post('/doctors/{doctor}/verify', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'verifyDoctor']);
        Route::post('/doctors/{doctor}/unverify', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'unverifyDoctor']);
        Route::post('/facilities/{facility}/verify', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'verifyFacility']);
        Route::post('/facilities/{facility}/unverify', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'unverifyFacility']);
        Route::get('/administrators', [\App\Http\Controllers\Api\SuperAdmin\AdminController::class, 'index']);
        Route::get('/doctors/{doctor}/dashboard', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'doctorDashboard']);
        Route::get('/doctors/{doctor}/sessions', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'doctorSessions']);
        Route::get('/doctors/{doctor}/appointments', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'doctorAppointments']);
        Route::get('/doctors/{doctor}/profile', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'doctorProfile']);
        Route::get('/facilities/{facility}/dashboard', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'facilityDashboard']);
        Route::get('/facilities/{facility}/sessions', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'facilitySessions']);
        Route::get('/facilities/{facility}/appointments', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'facilityAppointments']);
        Route::get('/facilities/{facility}/profile', [\App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController::class, 'facilityProfile']);
        // Phase 12: Marketplace Finance
        Route::get('/finance/marketplace', [FinanceController::class, 'marketplace']);
        Route::get('/finance/payments', [FinanceController::class, 'payments']);
        Route::get('/finance/plans', [FinanceController::class, 'plans']);
    });
});


// Phase 12: Public payment provider webhook (NOT auth-protected).
// In production this would be authenticated by provider signature in middleware.
Route::post('/payments/callback', [PaymentController::class, 'callback']);

// ─── M-Pesa Daraja API v1 — Public Webhooks (Safaricom calls these) ───────────
Route::prefix('v1/mpesa')->group(function () {
    // STK Push result — customer accepted or cancelled the M-Pesa prompt
    Route::post('/stk/result',  [MpesaController::class, 'stkResult']);
    // STK Push timeout — customer didn't respond within ~30 minutes
    Route::post('/stk/timeout', [MpesaController::class, 'stkTimeout']);
    // B2C payout result — doctor payout completed or failed
    Route::post('/b2c/result',  [MpesaController::class, 'b2cResult']);
});
// Phase 12: Public payment provider webhook (NOT auth-protected).
// In production this would be authenticated by provider signature in middleware.
Route::post('/payments/callback', [PaymentController::class, 'callback']);

// Phase 12: Development-only payment simulator.
// In non-local environments this requires X-Simulation-Secret header.
Route::post('/payments/simulate', [PaymentController::class, 'simulate']);

// Phase 13: Super Admin governance lifecycle (requires auth + super-admin).
Route::middleware(['auth:sanctum', 'ensure.super.admin'])->group(function () {
    Route::get('/audit/logs', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'auditLogs']);
    // Doctor verification lifecycle
    Route::post('/doctors/{doctor}/reject', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'rejectDoctor']);
    Route::post('/doctors/{doctor}/suspend', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'suspendDoctor']);
    Route::post('/doctors/{doctor}/unsuspend', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'unsuspendDoctor']);
    // Facility verification lifecycle
    Route::post('/facilities/{facility}/reject', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'rejectFacility']);
    Route::post('/facilities/{facility}/suspend', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'suspendFacility']);
    Route::post('/facilities/{facility}/unsuspend', [\App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController::class, 'unsuspendFacility']);
});