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
use App\Http\Controllers\Api\MarketplaceCommandCentreController;
use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PatientDiscoveryController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SessionSlotController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\SuperAdmin\AdminController;
use App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->get("/user", fn (Request $r) => $r->user());

Route::get("/specialties", [SpecialtyController::class, "index"]);
Route::get("/counties", [CountyController::class, "index"]);
Route::get("/facilities", [FacilityController::class, "index"]);
Route::get("/facilities/{slug}", [FacilityController::class, "show"]);
Route::get("/doctors", [DoctorController::class, "index"]);
Route::get("/doctors/{slug}", [DoctorController::class, "show"]);
Route::get("/doctors/{id}/sessions", [DoctorController::class, "sessions"]);
Route::get("/doctors/{id}/availability", [SessionController::class, "doctorAvailability"]);
Route::get("/doctors/search", [PatientDiscoveryController::class, "search"]);
Route::get("/doctors/{slug}/profile", [PatientDiscoveryController::class, "profile"]);
Route::get("/doctors/{slug}/sessions", [PatientDiscoveryController::class, "sessions"]);
Route::get("/sessions/search", [SessionController::class, "search"]);
Route::get("/sessions/{id}", [PatientDiscoveryController::class, "showSession"]);
Route::get("/sessions/{id}/slots", [SessionSlotController::class, "index"]);
Route::post("/auth/login", [AuthController::class, "login"]);
Route::post("/auth/register", [AuthController::class, "register"]);

// Public marketplace endpoints (no auth required)
Route::get("/admin/marketplace/command-centre", [MarketplaceCommandCentreController::class, "index"]);
Route::get("/admin/marketplace/health", [MarketplaceCommandCentreController::class, "health"]);
Route::get("/admin/marketplace/attention", [MarketplaceCommandCentreController::class, "attention"]);
Route::get("/admin/marketplace/today", [MarketplaceCommandCentreController::class, "today"]);

Route::middleware("auth:sanctum")->group(function () {
    Route::get("/auth/me", [AuthController::class, "me"]);
    Route::post("/auth/logout", [AuthController::class, "logout"]);
    Route::get("/appointments", [AppointmentController::class, "index"]);
    Route::get("/appointments/{id}", [AppointmentController::class, "show"]);
    Route::post("/appointments", [AppointmentController::class, "store"]);
    Route::patch("/appointments/{id}", [AppointmentController::class, "update"]);
    Route::delete("/appointments/{id}", [AppointmentController::class, "destroy"]);
    Route::post("/appointments/{appointment}/check-in", [AppointmentOperationsController::class, "checkIn"]);
    Route::post("/appointments/{appointment}/start", [AppointmentOperationsController::class, "startConsultation"]);
    Route::post("/appointments/{appointment}/complete", [AppointmentOperationsController::class, "completeConsultation"]);
    Route::post("/appointments/{appointment}/no-show", [AppointmentOperationsController::class, "markNoShow"]);
    Route::post("/appointments/{appointment}/patient-cancel", [AppointmentOperationsController::class, "patientCancel"]);
    Route::post("/appointments/{appointment}/doctor-cancel", [AppointmentOperationsController::class, "doctorCancel"]);
    Route::post("/appointments/{appointment}/facility-cancel", [AppointmentOperationsController::class, "facilityCancel"]);
    Route::get("/notifications", [NotificationController::class, "index"]);
    Route::patch("/notifications/{id}/read", [NotificationController::class, "markRead"]);
    Route::post("/doctors/{doctor}/follow", [DoctorFollowController::class, "follow"]);
    Route::delete("/doctors/{doctor}/follow", [DoctorFollowController::class, "unfollow"]);

    // Doctor workspace
    Route::prefix("doctor")->group(function () {
        Route::get("/me", [DoctorWorkspaceController::class, "me"]);
        Route::get("/dashboard", [DoctorWorkspaceController::class, "dashboard"]);
        Route::get("/appointments", [DoctorWorkspaceController::class, "appointments"]);
        Route::get("/schedule", [DoctorWorkspaceController::class, "schedule"]);
        Route::get("/earnings", [FinanceController::class, "earnings"]);
        Route::get("/earnings/{id}", [FinanceController::class, "earningDetail"]);
        Route::get("/payouts", [FinanceController::class, "doctorPayouts"]);
        Route::post("/payout-request", [FinanceController::class, "requestPayout"]);
    });

    // Facility workspace
    Route::prefix("facility")->group(function () {
        Route::get("/me", [FacilityWorkspaceController::class, "me"]);
        Route::get("/dashboard", [FacilityWorkspaceController::class, "dashboard"]);
        Route::get("/appointments", [FacilityWorkspaceController::class, "appointments"]);
        Route::get("/doctors", [FacilityWorkspaceController::class, "doctors"]);
        Route::get("/sessions", [FacilityWorkspaceController::class, "sessions"]);
    });

    // Admin workspace
    Route::prefix("admin")->group(function () {
        Route::get("/me", [AdminController::class, "me"]);
        Route::get("/workspace", [AdminWorkspaceController::class, "workspace"]);
        Route::post("/workspace/switch", [AdminWorkspaceController::class, "switchWorkspace"]);
        Route::post("/workspace/exit", [AdminWorkspaceController::class, "exitWorkspace"]);
        Route::get("/dashboard", [SuperAdminDashboardController::class, "dashboard"]);
        Route::get("/audit/logs", [SuperAdminDashboardController::class, "auditLogs"]);
        Route::get("/finance/marketplace", [FinanceController::class, "marketplace"]);
        Route::get("/finance/payments", [FinanceController::class, "payments"]);
        Route::get("/finance/plans", [FinanceController::class, "plans"]);
        Route::get("/finance/payouts", [FinanceController::class, "adminPayouts"]);
        Route::post("/finance/payouts/{id}/approve", [FinanceController::class, "approvePayout"]);
        Route::post("/finance/payouts/{id}/reject", [FinanceController::class, "rejectPayout"]);
        Route::post("/finance/payouts/{id}/cancel", [FinanceController::class, "cancelPayout"]);
        Route::post("/doctors/{doctor}/approve", [SuperAdminDashboardController::class, "approveDoctor"]);
        Route::post("/doctors/{doctor}/reject", [SuperAdminDashboardController::class, "rejectDoctor"]);
        Route::post("/doctors/{doctor}/suspend", [SuperAdminDashboardController::class, "suspendDoctor"]);
        Route::post("/facilities/{facility}/approve", [SuperAdminDashboardController::class, "approveFacility"]);
        Route::post("/facilities/{facility}/reject", [SuperAdminDashboardController::class, "rejectFacility"]);
        Route::post("/facilities/{facility}/suspend", [SuperAdminDashboardController::class, "suspendFacility"]);
    });
});

Route::post("/payments/callback", [PaymentController::class, "callback"]);
Route::post("/payments/simulate", [PaymentController::class, "simulate"]);
Route::prefix("v1/mpesa")->group(function () {
    Route::post("/stk/result", [MpesaController::class, "stkResult"]);
    Route::post("/stk/timeout", [MpesaController::class, "stkTimeout"]);
    Route::post("/b2c/result", [MpesaController::class, "b2cResult"]);
});
