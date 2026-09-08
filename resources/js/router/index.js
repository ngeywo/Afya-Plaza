import { createRouter, createWebHistory } from "vue-router";
import PatientHome from "../views/patient/Home.vue";
import DoctorSearch from "../views/patient/DoctorSearch.vue";
import DoctorProfile from "../views/patient/DoctorProfile.vue";
import LoginPage from "../views/patient/LoginPage.vue";
import RegisterPage from "../views/patient/RegisterPage.vue";
import MyAppointments from "../views/patient/MyAppointments.vue";
import BookingPage from "../views/patient/BookingPage.vue";
import AppointmentDetail from "../views/patient/AppointmentDetail.vue";
import NotificationsPage from "../views/patient/NotificationsPage.vue";
import MyDoctorsPage from "../views/patient/MyDoctorsPage.vue";
import NotFound from "../views/NotFound.vue";
import DoctorLayout from "../layouts/DoctorLayout.vue";
import DoctorDashboard from "../views/doctor/Dashboard.vue";
import DoctorClinics from "../views/doctor/Clinics.vue";
import DoctorSchedule from "../views/doctor/Schedule.vue";
import DoctorOnboarding from "../views/doctor/Onboarding.vue";
import DoctorPlans from "../views/doctor/Plans.vue";
import DoctorAppointments from "../views/doctor/Appointments.vue";
import DoctorProfileView from "../views/doctor/Profile.vue";
import DoctorEarnings from "../views/doctor/Earnings.vue";
import FacilityLayout from "../layouts/FacilityLayout.vue";
import FacilityDashboard from "../views/facility/Dashboard.vue";
import FacilityDoctors from "../views/facility/Doctors.vue";
import FacilityClinicSessions from "../views/facility/ClinicSessions.vue";
import FacilityAppointments from "../views/facility/Appointments.vue";
import FacilityProfile from "../views/facility/Profile.vue";
import FacilityLocations from "../views/facility/Locations.vue";
import FacilityStaff from "../views/facility/Staff.vue";
import FacilitySubscription from "../views/facility/Subscription.vue";
import FacilityOnboarding from "../views/facility/Onboarding.vue";
import FacilityOperatingHours from "../views/facility/OperatingHours.vue";
import FacilitySchedules from "../views/facility/Schedules.vue";
import FacilityServices from "../views/facility/Services.vue";
import FacilityPayments from "../views/facility/Payments.vue";
import FacilityTransactions from "../views/facility/Transactions.vue";
import FacilityReports from "../views/facility/Reports.vue";
import FacilityMyPlan from "../views/facility/MyPlan.vue";
import FacilityBilling from "../views/facility/Billing.vue";
import FacilityNotifications from "../views/facility/Notifications.vue";
import FacilitySettings from "../views/facility/Settings.vue";
import SuperAdminLayout from "../layouts/SuperAdminLayout.vue";
import AdminDashboard from "../views/admin/AdminDashboard.vue";
import AdminVerifications from "../views/admin/AdminVerifications.vue";
import AdminAdministrators from "../views/admin/AdminAdministrators.vue";
import AdminAuditLog from "../views/admin/AdminAuditLog.vue";
import AdminInspectLauncher from "../views/admin/AdminInspectLauncher.vue";
import AdminInspectSessions from "../views/admin/AdminInspectSessions.vue";
import AdminInspectAppointments from "../views/admin/AdminInspectAppointments.vue";
import AdminInspectProfile from "../views/admin/AdminInspectProfile.vue";
import AdminFinance from "../views/super-admin/Finance.vue";
import AdminSettlements from "../views/super-admin/Settlements.vue";
import AdminUsers from "../views/system/Users.vue";
import AdminRoles from "../views/system/Roles.vue";
import AdminPrivileges from "../views/system/Privileges.vue";
import AdminPermissions from "../views/system/Permissions.vue";
import AdminPlans from "../views/super-admin/Plans.vue";
import AdminSubscriptions from "../views/super-admin/Subscriptions.vue";
import AdminDoctors from "../views/admin/AdminDoctors.vue";
import AdminFacilities from "../views/admin/AdminFacilities.vue";
import AdminSpecialties from "../views/admin/AdminSpecialties.vue";
import AdminServices from "../views/admin/AdminServices.vue";
import AdminRelationships from "../views/admin/AdminRelationships.vue";
import AdminSessions from "../views/admin/AdminSessions.vue";
import AdminAppointments from "../views/admin/AdminAppointments.vue";
import AdminVerificationRequests from "../views/admin/AdminVerificationRequests.vue";
import AdminTransactions from "../views/admin/AdminTransactions.vue";
import AdminCommissions from "../views/admin/AdminCommissions.vue";
import AdminReports from "../views/admin/AdminReports.vue";
import AdminAnalytics from "../views/admin/AdminAnalytics.vue";
import AdminSettings from "../views/admin/AdminSettings.vue";
import AdminNotifications from "../views/admin/AdminNotifications.vue";
import { useAuthStore } from "../stores/authStore";
const routes = [
    { path: "/", name: "home", component: PatientHome, meta: { title: "Find Your Doctor" } },
    { path: "/search", name: "doctor-search", component: DoctorSearch, meta: { title: "Search Doctors" } },
    { path: "/doctors/:slug", name: "doctor-profile", component: DoctorProfile, meta: { title: "Doctor Profile" } },
    { path: "/book/:sessionId", name: "book", component: BookingPage, meta: { title: "Book Appointment" } },
    { path: "/appointments/:id", name: "appointment-detail", component: AppointmentDetail, meta: { title: "Appointment Details" } },
    { path: "/login", name: "login", component: LoginPage, meta: { title: "Sign In" } },
    { path: "/register", name: "register", component: RegisterPage, meta: { title: "Create Account" } },
    { path: "/my-appointments", name: "my-appointments", component: MyAppointments, meta: { title: "My Appointments", requiresAuth: true } },
    { path: "/notifications", name: "notifications", component: NotificationsPage, meta: { title: "Notifications", requiresAuth: true } },
    { path: "/my-doctors", name: "my-doctors", component: MyDoctorsPage, meta: { title: "My Doctors", requiresAuth: true } },
    { path: "/doctor", component: DoctorLayout, meta: { requiresAuth: true, requiresDoctor: true }, children: [
        { path: "", redirect: "/doctor/dashboard" },
        { path: "dashboard", name: "doctor-dashboard", component: DoctorDashboard, meta: { title: "Dashboard", requiresDoctor: true } },
        { path: "clinics", name: "doctor-clinics", component: DoctorClinics, meta: { title: "My Clinics", requiresDoctor: true } },
        { path: "schedule", name: "doctor-schedule", component: DoctorSchedule, meta: { title: "Schedule", requiresDoctor: true } },
        { path: "appointments", name: "doctor-appointments", component: DoctorAppointments, meta: { title: "Appointments", requiresDoctor: true } },
        { path: "profile", name: "doctor-profile-view", component: DoctorProfileView, meta: { title: "My Profile", requiresDoctor: true, requiresAuth: true } },
        { path: "earnings", name: "doctor-earnings", component: DoctorEarnings, meta: { title: "Earnings", requiresDoctor: true } },
        { path: "plans", name: "doctor-plans", component: DoctorPlans, meta: { title: "Plans", requiresDoctor: true } },
    ]},
    { path: "/facility", component: FacilityLayout, meta: { requiresAuth: true, requiresFacility: true }, children: [
        { path: "", redirect: "/facility/dashboard" },
        { path: "dashboard", name: "facility-dashboard", component: FacilityDashboard, meta: { title: "Dashboard", requiresFacility: true } },
        { path: "doctors", name: "facility-doctors", component: FacilityDoctors, meta: { title: "Doctors", requiresFacility: true } },
        { path: "sessions", name: "facility-sessions", component: FacilityClinicSessions, meta: { title: "Sessions", requiresFacility: true } },
        { path: "appointments", name: "facility-appointments", component: FacilityAppointments, meta: { title: "Appointments", requiresFacility: true } },
        { path: "profile", name: "facility-profile", component: FacilityProfile, meta: { title: "Profile", requiresFacility: true } },
        { path: "locations", name: "facility-locations", component: FacilityLocations, meta: { title: "Locations", requiresFacility: true } },
        { path: "staff", name: "facility-staff", component: FacilityStaff, meta: { title: "Staff", requiresFacility: true } },
        { path: "subscription", name: "facility-subscription", component: FacilitySubscription, meta: { title: "Subscription", requiresFacility: true } },
        { path: "operating-hours", name: "facility-operating-hours", component: FacilityOperatingHours, meta: { title: "Operating Hours", requiresFacility: true } },
        { path: "schedules", name: "facility-schedules", component: FacilitySchedules, meta: { title: "Schedules", requiresFacility: true } },
        { path: "services", name: "facility-services", component: FacilityServices, meta: { title: "Services", requiresFacility: true } },
        { path: "payments", name: "facility-payments", component: FacilityPayments, meta: { title: "Payments", requiresFacility: true } },
        { path: "transactions", name: "facility-transactions", component: FacilityTransactions, meta: { title: "Transactions", requiresFacility: true } },
        { path: "reports", name: "facility-reports", component: FacilityReports, meta: { title: "Reports", requiresFacility: true } },
        { path: "my-plan", name: "facility-my-plan", component: FacilityMyPlan, meta: { title: "My Plan", requiresFacility: true } },
        { path: "billing", name: "facility-billing", component: FacilityBilling, meta: { title: "Billing", requiresFacility: true } },
        { path: "notifications", name: "facility-notifications", component: FacilityNotifications, meta: { title: "Notifications", requiresFacility: true } },
        { path: "settings", name: "facility-settings", component: FacilitySettings, meta: { title: "Settings", requiresFacility: true } },
    ]},
    { path: "/admin", component: SuperAdminLayout, meta: { title: "Control Centre", requiresAuth: true, requiresPlatformOperator: true }, children: [
        { path: "", redirect: "/admin/dashboard" },
        { path: "dashboard", name: "admin-dashboard", component: AdminDashboard, meta: { title: "Control Centre", requiresPlatformOperator: true } },
        { path: "administrators", name: "admin-administrators", component: AdminAdministrators, meta: { title: "Administrators", requiresPlatformOperator: true } },
{ path: "users", name: "admin-users", component: AdminUsers, meta: { title: "Users", requiresPlatformOperator: true } },
        { path: "privileges", name: "admin-privileges", component: AdminPrivileges, meta: { title: "Privileges", requiresSuperAdmin: true } },
        { path: "roles", name: "admin-roles", component: AdminRoles, meta: { title: "Roles", requiresSuperAdmin: true } },
        { path: "permissions", name: "admin-permissions", component: AdminPermissions, meta: { title: "Permissions", requiresSuperAdmin: true } },
        { path: "verifications/doctors", name: "admin-verifications-doctors", component: AdminVerifications, meta: { title: "Pending Doctors", requiresPlatformOperator: true } },
        { path: "verifications/facilities", name: "admin-verifications-facilities", component: AdminVerifications, meta: { title: "Pending Facilities", requiresPlatformOperator: true } },
        { path: "verification-requests", name: "admin-verification-requests", component: AdminVerificationRequests, meta: { title: "Verification", requiresSuperAdmin: true } },
        { path: "marketplace/doctors", name: "admin-doctors", component: AdminDoctors, meta: { title: "Doctors", requiresSuperAdmin: true } },
        { path: "marketplace/facilities", name: "admin-facilities", component: AdminFacilities, meta: { title: "Facilities", requiresSuperAdmin: true } },
        { path: "specialties", name: "admin-specialties", component: AdminSpecialties, meta: { title: "Specialties", requiresSuperAdmin: true } },
        { path: "services", name: "admin-services", component: AdminServices, meta: { title: "Services", requiresSuperAdmin: true } },
        { path: "relationships", name: "admin-relationships", component: AdminRelationships, meta: { title: "Relationships", requiresSuperAdmin: true } },
        { path: "sessions", name: "admin-sessions", component: AdminSessions, meta: { title: "Clinic Sessions", requiresSuperAdmin: true } },
        { path: "appointments", name: "admin-appointments", component: AdminAppointments, meta: { title: "Appointments", requiresSuperAdmin: true } },
        { path: "transactions", name: "admin-transactions", component: AdminTransactions, meta: { title: "Transactions", requiresSuperAdmin: true } },
        { path: "commissions", name: "admin-commissions", component: AdminCommissions, meta: { title: "Commissions", requiresSuperAdmin: true } },
        { path: "reports", name: "admin-reports", component: AdminReports, meta: { title: "Reports", requiresSuperAdmin: true } },
        { path: "analytics", name: "admin-analytics", component: AdminAnalytics, meta: { title: "Analytics", requiresSuperAdmin: true } },
        { path: "settings", name: "admin-settings", component: AdminSettings, meta: { title: "Settings", requiresSuperAdmin: true } },
        { path: "notifications", name: "admin-notifications", component: AdminNotifications, meta: { title: "Notifications", requiresPlatformOperator: true } },
        { path: "inspect/:type/:id", name: "admin-inspect-launcher", component: AdminInspectLauncher, meta: { title: "Inspecting", requiresPlatformOperator: true } },
        { path: "inspect/:type/:id/sessions", name: "admin-inspect-sessions", component: AdminInspectSessions, meta: { title: "Sessions", requiresPlatformOperator: true } },
        { path: "inspect/:type/:id/appointments", name: "admin-inspect-appointments", component: AdminInspectAppointments, meta: { title: "Appointments", requiresPlatformOperator: true } },
        { path: "inspect/:type/:id/profile", name: "admin-inspect-profile", component: AdminInspectProfile, meta: { title: "Profile", requiresPlatformOperator: true } },
{ path: "finance", name: "admin-finance", component: AdminFinance, meta: { title: "Marketplace Finance", requiresSuperAdmin: true } },
        { path: "settlements", name: "admin-settlements", component: AdminSettlements, meta: { title: "Settlements", requiresSuperAdmin: true } },
        { path: "plans", name: "admin-plans", component: AdminPlans, meta: { title: "Plans", requiresSuperAdmin: true } },
        { path: "subscriptions", name: "admin-subscriptions", component: AdminSubscriptions, meta: { title: "Subscriptions", requiresSuperAdmin: true } },
        { path: "payouts", name: "admin-payouts", component: () => import("../views/super-admin/Payouts.vue"), meta: { title: "Payouts", requiresSuperAdmin: true } },
        { path: "audit", name: "admin-audit", component: AdminAuditLog, meta: { title: "Audit Log", requiresPlatformOperator: true } },
    ]},
    { path: "/:pathMatch(.*)*", name: "not-found", component: NotFound, meta: { title: "Page not found" } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const store = useAuthStore();
    document.title = to.meta.title ? `${to.meta.title} - Afya Plaza` : "Afya Plaza";
    if (store.isAuthenticated && !store.user) { await store.fetchMe(); }
    if (to.meta.requiresSuperAdmin && !store.isSuperAdmin) { next({ name: "home" }); return; }
    if (to.meta.requiresPlatformOperator && !store.isPlatformOperator) { next({ name: "home" }); return; }
    if (to.meta.requiresDoctor && !store.isDoctor) { next({ name: "home" }); return; }
    if (to.meta.requiresFacility && !store.isFacility) { next({ name: "home" }); return; }
    if (to.meta.requiresAuth && !store.isAuthenticated) { next({ name: "login" }); return; }
    next();
});

export default router;
