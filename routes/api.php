<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentOperationsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicDayController;
use App\Http\Controllers\Api\ContactVerificationController;
use App\Http\Controllers\Api\CountyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorFacilityAgreementController;
use App\Http\Controllers\Api\DoctorFollowController;
use App\Http\Controllers\Api\DoctorPracticeController;
use App\Http\Controllers\Api\DoctorRelationshipController;
use App\Http\Controllers\Api\DoctorSettlementController;
use App\Http\Controllers\Api\DoctorWorkspaceController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\FacilityDoctorController;
use App\Http\Controllers\Api\FacilityPaymentAccountController;
use App\Http\Controllers\Api\FacilitySettlementController;
use App\Http\Controllers\Api\FacilitySubscriptionController;
use App\Http\Controllers\Api\FacilityWorkspaceController;
use App\Http\Controllers\Api\FacilityWorkspaceExtensionsController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\MarketplaceCommandCentreController;
use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PatientDiscoveryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlatformFeeController;
use App\Http\Controllers\Api\ProviderVerificationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SessionSlotController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\SuperAdmin\AdminAccountController;
use App\Http\Controllers\Api\SuperAdmin\AdminController;
use App\Http\Controllers\Api\SuperAdmin\AdminMarketplaceController;
use App\Http\Controllers\Api\SuperAdmin\AdminPlanController;
use App\Http\Controllers\Api\SuperAdmin\AdminReportsController;
use App\Http\Controllers\Api\SuperAdmin\AdminSettingsController;
use App\Http\Controllers\Api\SuperAdmin\AdminSettlementController;
use App\Http\Controllers\Api\SuperAdmin\AdminSubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\AdminVerificationController;
use App\Http\Controllers\Api\SuperAdmin\AdminWorkspaceController;
use App\Http\Controllers\Api\SuperAdmin\PermissionController;
use App\Http\Controllers\Api\SuperAdmin\RoleController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $r) => $r->user());

Route::get('/specialties', [SpecialtyController::class, 'index']);
Route::get('/counties', [CountyController::class, 'index']);
Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/facilities/{slug}', [FacilityController::class, 'show']);
Route::get('/doctors', [DoctorController::class, 'index']);
Route::get('/doctors/search', [PatientDiscoveryController::class, 'search']);
Route::get('/doctors/{slug}', [DoctorController::class, 'show']);
Route::get('/doctors/{slug}/today', [DoctorController::class, 'today']);
Route::get('/doctors/{id}/sessions', [DoctorController::class, 'sessions']);
Route::get('/doctors/{id}/availability', [SessionController::class, 'doctorAvailability']);
Route::get('/doctors/{slug}/profile', [PatientDiscoveryController::class, 'profile']);
Route::get('/doctors/{slug}/sessions', [PatientDiscoveryController::class, 'sessions']);
Route::get('/sessions/search', [SessionController::class, 'search']);
Route::get('/sessions/{id}', [PatientDiscoveryController::class, 'showSession']);
Route::get('/sessions/{id}/slots', [SessionSlotController::class, 'index']);
Route::get('/facilities/{slug}/doctors', [FacilityController::class, 'doctors']);

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [PasswordController::class, 'forgot']);
Route::post('/auth/reset-password', [PasswordController::class, 'reset']);

Route::get('/admin/marketplace/command-centre', [MarketplaceCommandCentreController::class, 'index']);
Route::get('/admin/marketplace/health', [MarketplaceCommandCentreController::class, 'health']);
Route::get('/admin/marketplace/attention', [MarketplaceCommandCentreController::class, 'attention']);
Route::get('/admin/marketplace/today', [MarketplaceCommandCentreController::class, 'today']);

Route::middleware(['auth:sanctum', 'ensure.account.open'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{id}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
    Route::post('/appointments/{appointment}/check-in', [AppointmentOperationsController::class, 'checkIn']);
    Route::post('/appointments/{appointment}/complete', [AppointmentOperationsController::class, 'complete']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentOperationsController::class, 'cancel']);
    Route::post('/appointments/{appointment}/doctor-cancel', [AppointmentOperationsController::class, 'doctorCancel']);
    Route::post('/appointments/{appointment}/facility-cancel', [AppointmentOperationsController::class, 'facilityCancel']);
    Route::post('/appointments/{appointment}/patient-cancel', [AppointmentOperationsController::class, 'patientCancel']);
    Route::post('/appointments/{appointment}/no-show', [AppointmentOperationsController::class, 'noShow']);
    Route::get('/appointments/{appointment}/reschedule-slots', [AppointmentOperationsController::class, 'rescheduleSlots']);
    Route::post('/appointments/{appointment}/reschedule', [AppointmentOperationsController::class, 'reschedule']);
    Route::get('/doctor-facilities', [DoctorController::class, 'facilities']);
    Route::post('/doctor-facilities', [DoctorController::class, 'attachFacility']);
    Route::delete('/doctor-facilities/{doctorFacility}', [DoctorController::class, 'detachFacility']);
    Route::get('/clinic-days', [ClinicDayController::class, 'index']);
    Route::get('/clinic-days/{id}', [ClinicDayController::class, 'show']);
    Route::post('/clinic-days', [ClinicDayController::class, 'store']);
    Route::patch('/clinic-days/{id}', [ClinicDayController::class, 'update']);
    Route::delete('/clinic-days/{id}', [ClinicDayController::class, 'destroy']);

    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::patch('/sessions/{id}', [SessionController::class, 'update']);
    Route::post('/sessions/{id}/confirm', [SessionController::class, 'confirm']);
    Route::post('/sessions/{id}/cancel', [SessionController::class, 'cancel']);

    Route::get('/account', [AccountController::class, 'show']);
    Route::get('/account/sessions', [AccountController::class, 'sessions']);
    Route::post('/account/sessions/revoke-others', [AccountController::class, 'revokeOtherSessions']);
    Route::delete('/account/sessions/{tokenId}', [AccountController::class, 'revokeSession']);
    Route::post('/account/deactivate', [AccountController::class, 'deactivate']);
    Route::post('/account/password/change', [PasswordController::class, 'change']);
    Route::post('/account/verification/send', [ContactVerificationController::class, 'send']);
    Route::post('/account/verification/verify', [ContactVerificationController::class, 'verify']);

    Route::get('/doctor/verification', [ProviderVerificationController::class, 'doctorStatus']);
    Route::post('/doctor/verification', [ProviderVerificationController::class, 'submitDoctor']);
    Route::get('/facility/verification', [ProviderVerificationController::class, 'facilityStatus']);
    Route::post('/facility/verification', [ProviderVerificationController::class, 'submitFacility']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::post('/doctors/{doctor}/follow', [DoctorFollowController::class, 'follow']);
    Route::delete('/doctors/{doctor}/follow', [DoctorFollowController::class, 'unfollow']);

    Route::prefix('doctor')->group(function () {
        Route::get('/me', [DoctorWorkspaceController::class, 'me']);
        Route::get('/dashboard', [DoctorWorkspaceController::class, 'dashboard']);
        Route::get('/appointments', [DoctorWorkspaceController::class, 'appointments']);
        Route::get('/schedule', [DoctorWorkspaceController::class, 'schedule']);
        Route::get('/profile', [DoctorWorkspaceController::class, 'profile']);
        Route::put('/profile', [DoctorWorkspaceController::class, 'updateProfile']);
        Route::get('/practice/weekly-overview', [DoctorPracticeController::class, 'weeklyOverview']);
        Route::get('/practice/analytics', [DoctorPracticeController::class, 'analytics']);
        Route::get('/practice/facility-performance', [DoctorPracticeController::class, 'facilityPerformance']);
        Route::get('/practice/detect-conflicts', [DoctorPracticeController::class, 'detectConflicts']);
        Route::get('/earnings', [FinanceController::class, 'earnings']);
        Route::get('/earnings/{id}', [FinanceController::class, 'earningDetail']);
        Route::get('/payouts', [FinanceController::class, 'doctorPayouts']);
        Route::post('/payout-request', [FinanceController::class, 'requestPayout']);
        Route::get('/settlements', [DoctorSettlementController::class, 'index']);
        Route::post('/relationships/join', [DoctorRelationshipController::class, 'join']);
        Route::get('/relationships', [DoctorRelationshipController::class, 'index']);
        Route::post('/relationships/{id}/accept', [DoctorRelationshipController::class, 'accept']);
        Route::post('/relationships/{id}/decline', [DoctorRelationshipController::class, 'decline']);
        Route::post('/relationships/{id}/end', [DoctorRelationshipController::class, 'end']);
        Route::put('/relationships/{id}/settings', [DoctorRelationshipController::class, 'updateSettings']);
        Route::get('/relationships/{id}/schedules', [DoctorRelationshipController::class, 'schedules']);
        Route::post('/relationships/{id}/schedules', [DoctorRelationshipController::class, 'storeSchedule']);
        Route::put('/relationships/{id}/schedules/{scheduleId}', [DoctorRelationshipController::class, 'updateSchedule']);
        Route::delete('/relationships/{id}/schedules/{scheduleId}', [DoctorRelationshipController::class, 'deleteSchedule']);
        Route::get('/relationships/{id}/services', [DoctorRelationshipController::class, 'services']);
        Route::post('/relationships/{id}/services', [DoctorRelationshipController::class, 'storeService']);
        Route::put('/relationships/{id}/services/{serviceId}', [DoctorRelationshipController::class, 'updateService']);
        Route::delete('/relationships/{id}/services/{serviceId}', [DoctorRelationshipController::class, 'deleteService']);
    });

    Route::prefix('facility')->group(function () {
        Route::get('/me', [FacilityWorkspaceController::class, 'me']);
        Route::get('/dashboard', [FacilityWorkspaceController::class, 'dashboard']);
        Route::get('/appointments', [FacilityWorkspaceController::class, 'appointments']);
        Route::get('/doctors', [FacilityWorkspaceController::class, 'doctors']);
        Route::get('/facilities', [FacilityWorkspaceController::class, 'facilities']);
        Route::get('/doctors/search', [FacilityDoctorController::class, 'searchDoctors']);
        Route::get('/sessions', [FacilityWorkspaceController::class, 'clinicSessions']);
        Route::put('/sessions/{id}/confirm', [FacilityWorkspaceController::class, 'confirmSession']);
        Route::put('/sessions/{id}/reject', [FacilityWorkspaceController::class, 'rejectSession']);
        Route::get('/profile', [FacilityWorkspaceController::class, 'profile']);
        Route::put('/profile', [FacilityWorkspaceController::class, 'updateProfile']);
        Route::post('/agreements', [DoctorFacilityAgreementController::class, 'store']);
        Route::get('/agreements', [DoctorFacilityAgreementController::class, 'index']);
        Route::get('/settlements', [FacilitySettlementController::class, 'index']);
        Route::get('/locations', [FacilityWorkspaceController::class, 'locations']);
        Route::post('/locations', [FacilityWorkspaceController::class, 'storeLocation']);
        Route::get('/staff', [FacilityWorkspaceController::class, 'staff']);
        Route::post('/staff', [FacilityWorkspaceController::class, 'storeStaff']);
        Route::patch('/staff/{user}', [FacilityWorkspaceController::class, 'updateStaff']);
        Route::delete('/staff/{user}', [FacilityWorkspaceController::class, 'removeStaff']);
        Route::get('/payment-accounts', [FacilityPaymentAccountController::class, 'index']);
        Route::post('/payment-accounts', [FacilityPaymentAccountController::class, 'store']);
        Route::patch('/payment-accounts/{id}', [FacilityPaymentAccountController::class, 'update']);
        Route::delete('/payment-accounts/{id}', [FacilityPaymentAccountController::class, 'destroy']);
        Route::get('/subscription', [FacilitySubscriptionController::class, 'show']);
        Route::get('/subscription/plans', [FacilitySubscriptionController::class, 'plans']);
        Route::post('/subscription/checkout', [FacilitySubscriptionController::class, 'checkout']);
        Route::post('/subscription/payment/confirm', [FacilitySubscriptionController::class, 'confirmPayment']);
        Route::post('/subscription/payment/failure', [FacilitySubscriptionController::class, 'paymentFailure']);
        Route::post('/subscription/upgrade', [FacilitySubscriptionController::class, 'upgrade']);
        Route::post('/subscription/downgrade', [FacilitySubscriptionController::class, 'downgrade']);
        Route::post('/subscription/cancel', [FacilitySubscriptionController::class, 'cancel']);

        Route::post('/doctors/{doctor}/invite', [FacilityDoctorController::class, 'invite']);
        Route::get('/relationships', [FacilityDoctorController::class, 'index']);
        Route::post('/relationships/{id}/approve', [FacilityDoctorController::class, 'approve']);
        Route::post('/relationships/{id}/decline', [FacilityDoctorController::class, 'decline']);
        Route::post('/relationships/{id}/suspend', [FacilityDoctorController::class, 'suspend']);
        Route::post('/relationships/{id}/reactivate', [FacilityDoctorController::class, 'reactivate']);
        Route::post('/relationships/{id}/end', [FacilityDoctorController::class, 'end']);
        Route::put('/relationships/{id}/settings', [FacilityDoctorController::class, 'updateSettings']);
        Route::get('/relationships/{id}/schedules', [FacilityDoctorController::class, 'schedules']);
        Route::post('/relationships/{id}/schedules', [FacilityDoctorController::class, 'storeSchedule']);
        Route::put('/relationships/{id}/schedules/{scheduleId}', [FacilityDoctorController::class, 'updateSchedule']);
        Route::delete('/relationships/{id}/schedules/{scheduleId}', [FacilityDoctorController::class, 'deleteSchedule']);
        Route::get('/relationships/{id}/services', [FacilityDoctorController::class, 'services']);
        Route::post('/relationships/{id}/services', [FacilityDoctorController::class, 'storeService']);
        Route::put('/relationships/{id}/services/{serviceId}', [FacilityDoctorController::class, 'updateService']);
        Route::delete('/relationships/{id}/services/{serviceId}', [FacilityDoctorController::class, 'deleteService']);
        Route::get('/relationships/{id}/calendar', [FacilityDoctorController::class, 'calendar']);

        Route::get('/operating-hours', [FacilityWorkspaceExtensionsController::class, 'operatingHours']);
        Route::put('/operating-hours', [FacilityWorkspaceExtensionsController::class, 'saveOperatingHours']);
        Route::get('/schedules', [FacilityWorkspaceExtensionsController::class, 'schedules']);
        Route::get('/services', [FacilityWorkspaceExtensionsController::class, 'services']);
        Route::post('/services', [FacilityWorkspaceExtensionsController::class, 'storeService']);
        Route::patch('/services/{id}', [FacilityWorkspaceExtensionsController::class, 'updateService']);
        Route::delete('/services/{id}', [FacilityWorkspaceExtensionsController::class, 'destroyService']);
        Route::get('/payments', [FacilityWorkspaceExtensionsController::class, 'payments']);
        Route::get('/transactions', [FacilityWorkspaceExtensionsController::class, 'transactions']);
        Route::get('/reports', [FacilityWorkspaceExtensionsController::class, 'reports']);
        Route::get('/reports/export', [FacilityWorkspaceExtensionsController::class, 'exportReport']);
        Route::get('/billing', [FacilityWorkspaceExtensionsController::class, 'billing']);
        Route::get('/settings', [FacilityWorkspaceExtensionsController::class, 'settings']);
        Route::put('/settings', [FacilityWorkspaceExtensionsController::class, 'updateSettings']);
    });

    Route::prefix('admin')->middleware('ensure.platform.operator')->group(function () {
        Route::get('/me', [AdminController::class, 'me']);
        Route::get('/workspace', [AdminWorkspaceController::class, 'workspace']);
        Route::post('/workspace/switch', [AdminWorkspaceController::class, 'switchWorkspace']);
        Route::post('/workspace/exit', [AdminWorkspaceController::class, 'exitWorkspace']);
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'dashboard']);
        Route::get('/audit/logs', [SuperAdminDashboardController::class, 'auditLogs']);

        Route::get('/verifications/doctors', [SuperAdminDashboardController::class, 'pendingDoctors']);
        Route::get('/verifications/facilities', [SuperAdminDashboardController::class, 'pendingFacilities']);
        Route::post('/doctors/{doctor}/verify', [SuperAdminDashboardController::class, 'verifyDoctor']);
        Route::post('/doctors/{doctor}/unverify', [SuperAdminDashboardController::class, 'unverifyDoctor']);
        Route::post('/doctors/{doctor}/reject', [SuperAdminDashboardController::class, 'rejectDoctor']);
        Route::post('/doctors/{doctor}/suspend', [SuperAdminDashboardController::class, 'suspendDoctor']);
        Route::post('/doctors/{doctor}/unsuspend', [SuperAdminDashboardController::class, 'unsuspendDoctor']);
        Route::post('/facilities/{facility}/verify', [SuperAdminDashboardController::class, 'verifyFacility']);
        Route::post('/facilities/{facility}/unverify', [SuperAdminDashboardController::class, 'unverifyFacility']);
        Route::post('/facilities/{facility}/reject', [SuperAdminDashboardController::class, 'rejectFacility']);
        Route::post('/facilities/{facility}/suspend', [SuperAdminDashboardController::class, 'suspendFacility']);
        Route::post('/facilities/{facility}/unsuspend', [SuperAdminDashboardController::class, 'unsuspendFacility']);

        Route::get('/doctors/{doctor}/dashboard', [AdminWorkspaceController::class, 'doctorDashboard']);
        Route::get('/doctors/{doctor}/sessions', [AdminWorkspaceController::class, 'doctorSessions']);
        Route::get('/doctors/{doctor}/appointments', [AdminWorkspaceController::class, 'doctorAppointments']);
        Route::get('/doctors/{doctor}/profile', [AdminWorkspaceController::class, 'doctorProfile']);
        Route::get('/facilities/{facility}/dashboard', [AdminWorkspaceController::class, 'facilityDashboard']);
        Route::get('/facilities/{facility}/sessions', [AdminWorkspaceController::class, 'facilitySessions']);
        Route::get('/facilities/{facility}/appointments', [AdminWorkspaceController::class, 'facilityAppointments']);
        Route::get('/facilities/{facility}/profile', [AdminWorkspaceController::class, 'facilityProfile']);

        Route::get('/platform-fees', [PlatformFeeController::class, 'index']);
        Route::get('/platform-fees/active', [PlatformFeeController::class, 'active']);
        Route::post('/platform-fees', [PlatformFeeController::class, 'store']);
        Route::patch('/platform-fees/{id}', [PlatformFeeController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/roles', [UserController::class, 'updateRoles']);
        Route::post('/users/{user}/status', [UserController::class, 'changeStatus']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::middleware('ensure.super.admin')->group(function () {
            Route::get('/marketplace/doctors', [AdminMarketplaceController::class, 'doctors']);
            Route::get('/marketplace/facilities', [AdminMarketplaceController::class, 'facilities']);
            Route::get('/specialties', [AdminMarketplaceController::class, 'specialties']);
            Route::post('/specialties', [AdminMarketplaceController::class, 'storeSpecialty']);
            Route::put('/specialties/{specialty}', [AdminMarketplaceController::class, 'updateSpecialty']);
            Route::delete('/specialties/{specialty}', [AdminMarketplaceController::class, 'destroySpecialty']);
            Route::get('/services', [AdminMarketplaceController::class, 'services']);
            Route::get('/relationships', [AdminMarketplaceController::class, 'relationships']);
            Route::post('/relationships/{relationship}/approve', [AdminMarketplaceController::class, 'relationshipApprove']);
            Route::post('/relationships/{relationship}/reject', [AdminMarketplaceController::class, 'relationshipReject']);
            Route::post('/relationships/{relationship}/suspend', [AdminMarketplaceController::class, 'relationshipSuspend']);
            Route::post('/relationships/{relationship}/reactivate', [AdminMarketplaceController::class, 'relationshipReactivate']);
            Route::post('/relationships/{relationship}/end', [AdminMarketplaceController::class, 'relationshipEnd']);
            Route::get('/sessions', [AdminMarketplaceController::class, 'sessions']);
            Route::post('/sessions/{id}/cancel', [AdminMarketplaceController::class, 'cancelSession']);
            Route::get('/appointments', [AdminMarketplaceController::class, 'appointments']);
            Route::post('/appointments/{id}/cancel', [AdminMarketplaceController::class, 'cancelAppointment']);

            Route::get('/transactions', [AdminReportsController::class, 'transactions']);
            Route::get('/commissions', [AdminReportsController::class, 'commissions']);
            Route::get('/reports', [AdminReportsController::class, 'reports']);
            Route::get('/analytics', [AdminReportsController::class, 'analytics']);
            Route::get('/settings', [AdminSettingsController::class, 'index']);
            Route::put('/settings', [AdminSettingsController::class, 'update']);

            Route::middleware('permission:doctors.verify,facilities.verify')->group(function () {
                Route::get('/verification-requests', [AdminVerificationController::class, 'index']);
                Route::get('/verification-requests/{verificationRequest}', [AdminVerificationController::class, 'show']);
                Route::post('/verification-requests/{verificationRequest}/review', [AdminVerificationController::class, 'review']);
            });

            Route::middleware('permission:users.manage')->group(function () {
                Route::get('/accounts', [AdminAccountController::class, 'index']);
                Route::get('/accounts/{user}/activity', [AdminAccountController::class, 'activity']);
                Route::post('/accounts/{user}/suspend', [AdminAccountController::class, 'suspend']);
                Route::post('/accounts/{user}/reactivate', [AdminAccountController::class, 'reactivate']);
                Route::post('/accounts/{user}/disable', [AdminAccountController::class, 'disable']);
            });

            Route::get('/finance/marketplace', [FinanceController::class, 'marketplace']);
            Route::get('/finance/payments', [FinanceController::class, 'payments']);
            Route::get('/finance/plans', [FinanceController::class, 'plans']);
            Route::get('/finance/payouts', [FinanceController::class, 'adminPayouts']);
            Route::post('/finance/payouts/{id}/approve', [FinanceController::class, 'approvePayout']);
            Route::post('/finance/payouts/{id}/reject', [FinanceController::class, 'rejectPayout']);
            Route::post('/finance/payouts/{id}/cancel', [FinanceController::class, 'cancelPayout']);
            Route::get('/settlements', [AdminSettlementController::class, 'index']);
            Route::get('/settlements/summary', [AdminSettlementController::class, 'summary']);
            Route::get('/settlements/doctor-breakdown', [AdminSettlementController::class, 'doctorBreakdown']);
            Route::post('/settlements/{id}/approve', [AdminSettlementController::class, 'approve']);
            Route::post('/settlements/{id}/reject', [AdminSettlementController::class, 'reject']);

            Route::get('/roles', [RoleController::class, 'index']);
            Route::get('/roles/{role}', [RoleController::class, 'show']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::patch('/roles/{role}', [RoleController::class, 'update']);
            Route::post('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::post('/permissions', [PermissionController::class, 'store']);
            Route::patch('/permissions/{permission}', [PermissionController::class, 'update']);
            Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);

            Route::get('/plans', [AdminPlanController::class, 'index']);
            Route::post('/plans', [AdminPlanController::class, 'store']);
            Route::put('/plans/{plan}', [AdminPlanController::class, 'update']);
            Route::post('/plans/{plan}/status', [AdminPlanController::class, 'setActive']);
            Route::get('/plans/{plan}/versions', [AdminPlanController::class, 'versions']);

            Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
            Route::get('/subscriptions/{subscription}', [AdminSubscriptionController::class, 'show']);
            Route::post('/subscriptions/{subscription}/verify-payment', [AdminSubscriptionController::class, 'verifyPayment']);
            Route::post('/subscriptions/{subscription}/adjust', [AdminSubscriptionController::class, 'adjust']);
        });
    });
});

Route::post('/payments/callback', [PaymentController::class, 'callback']);
Route::post('/payments/simulate', [PaymentController::class, 'simulate']);
Route::prefix('v1/mpesa')->group(function () {
    Route::post('/stk/result', [MpesaController::class, 'stkResult']);
    Route::post('/stk/timeout', [MpesaController::class, 'stkTimeout']);
    Route::post('/b2c/result', [MpesaController::class, 'b2cResult']);
});
