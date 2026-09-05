import { createRouter, createWebHistory } from "vue-router";
import PatientHome from "../views/patient/Home.vue";
import DoctorSearch from "../views/patient/DoctorSearch.vue";
import DoctorProfile from "../views/patient/DoctorProfile.vue";
import LoginPage from "../views/patient/LoginPage.vue";
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
import SuperAdminLayout from "../layouts/SuperAdminLayout.vue";
import AdminDashboard from "../views/admin/AdminDashboard.vue";
import AdminVerifications from "../views/admin/AdminVerifications.vue";
import AdminAdministrators from "../views/admin/AdminAdministrators.vue";
import AdminInspectLauncher from "../views/admin/AdminInspectLauncher.vue";
import AdminInspectSessions from "../views/admin/AdminInspectSessions.vue";
import AdminInspectAppointments from "../views/admin/AdminInspectAppointments.vue";
import AdminInspectProfile from "../views/admin/AdminInspectProfile.vue";
import AdminFinance from "../views/super-admin/Finance.vue";
import { useAuthStore } from "../stores/authStore";

const routes = [
    { path: "/", name: "home", component: PatientHome, meta: { title: "Find Your Doctor" } },
    { path: "/search", name: "doctor-search", component: DoctorSearch, meta: { title: "Search Doctors" } },
    { path: "/doctors/:slug", name: "doctor-profile", component: DoctorProfile, meta: { title: "Doctor Profile" } },
    { path: "/book/:sessionId", name: "book", component: BookingPage, meta: { title: "Book Appointment" } },
    { path: "/appointments/:id", name: "appointment-detail", component: AppointmentDetail, meta: { title: "Appointment Details" } },
    { path: "/login", name: "login", component: LoginPage, meta: { title: "Sign In" } },
    { path: "/my-appointments", name: "my-appointments", component: MyAppointments, meta: { title: "My Appointments", requiresAuth: true } },

    // Phase 10: Marketplace retention
    { path: "/notifications", name: "notifications", component: NotificationsPage, meta: { title: "Notifications", requiresAuth: true } },
    { path: "/my-doctors", name: "my-doctors", component: MyDoctorsPage, meta: { title: "My Doctors", requiresAuth: true } },

    // Doctor workspace (Phase 5)
    {
        path: "/doctor",
        component: DoctorLayout,
        meta: { title: "Doctor Workspace", requiresAuth: true },
        children: [
            { path: "dashboard", name: "doctor-dashboard", component: DoctorDashboard, meta: { title: "Dashboard", requiresDoctor: true } },
            { path: "clinics", name: "doctor-clinics", component: DoctorClinics, meta: { title: "My Clinics", requiresDoctor: true } },
            { path: "schedule", name: "doctor-schedule", component: DoctorSchedule, meta: { title: "Schedule", requiresDoctor: true } },
            { path: "appointments", name: "doctor-appointments", component: DoctorAppointments, meta: { title: "Appointments", requiresDoctor: true } },
            { path: "profile", name: "doctor-profile-view", component: DoctorProfileView, meta: { title: "Profile", requiresDoctor: true } },
            { path: "earnings", name: "doctor-earnings", component: DoctorEarnings, meta: { title: "Earnings", requiresDoctor: true } },
        ],
    },

    // Facility workspace (Phase 6)
    {
        path: "/facility",
        component: FacilityLayout,
        meta: { title: "Facility Workspace", requiresAuth: true },
        children: [
            { path: "dashboard", name: "facility-dashboard", component: FacilityDashboard, meta: { title: "Dashboard", requiresFacility: true } },
            { path: "doctors", name: "facility-doctors", component: FacilityDoctors, meta: { title: "My Doctors", requiresFacility: true } },
            { path: "clinic-sessions", name: "facility-clinic-sessions", component: FacilityClinicSessions, meta: { title: "Clinic Sessions", requiresFacility: true } },
            { path: "appointments", name: "facility-appointments", component: FacilityAppointments, meta: { title: "Appointments", requiresFacility: true } },
            { path: "profile", name: "facility-profile", component: FacilityProfile, meta: { title: "Facility Profile", requiresFacility: true } },
            { path: "locations", name: "facility-locations", component: FacilityLocations, meta: { title: "Locations", requiresFacility: true } },
            { path: "staff", name: "facility-staff", component: FacilityStaff, meta: { title: "Staff", requiresFacility: true } },
        ],
    },

    // Super Admin Control Centre (Phase 7)
    {
        path: "/admin",
        component: SuperAdminLayout,
        meta: { title: "Control Centre", requiresAuth: true, requiresSuperAdmin: true },
        children: [
            { path: "", redirect: "/admin/dashboard" },
            { path: "dashboard", name: "admin-dashboard", component: AdminDashboard, meta: { title: "Control Centre", requiresSuperAdmin: true } },
            { path: "administrators", name: "admin-administrators", component: AdminAdministrators, meta: { title: "Administrators", requiresSuperAdmin: true } },
            { path: "verifications/doctors", name: "admin-verifications-doctors", component: AdminVerifications, meta: { title: "Pending Doctors", requiresSuperAdmin: true } },
            { path: "verifications/facilities", name: "admin-verifications-facilities", component: AdminVerifications, meta: { title: "Pending Facilities", requiresSuperAdmin: true } },
            // Launcher: /admin/inspect/:type/:id — sets store then redirects
            { path: "inspect/:type/:id", name: "admin-inspect-launcher", component: AdminInspectLauncher, meta: { title: "Inspecting", requiresSuperAdmin: true } },
            // Workspace views: /admin/inspect/:type/:id/:section
            { path: "inspect/:type/:id/sessions", name: "admin-inspect-sessions", component: AdminInspectSessions, meta: { title: "Sessions", requiresSuperAdmin: true } },
            { path: "inspect/:type/:id/appointments", name: "admin-inspect-appointments", component: AdminInspectAppointments, meta: { title: "Appointments", requiresSuperAdmin: true } },
            { path: "inspect/:type/:id/profile", name: "admin-inspect-profile", component: AdminInspectProfile, meta: { title: "Profile", requiresSuperAdmin: true } },
            { path: "finance", name: "admin-finance", component: AdminFinance, meta: { title: "Marketplace Finance", requiresSuperAdmin: true } },
            { path: "payouts", name: "admin-payouts", component: () => import("../views/super-admin/Payouts.vue"), meta: { title: "Payouts", requiresSuperAdmin: true } },
        ],
    },

    // 404 — catch-all must be last
    { path: "/:pathMatch(.*)*", name: "not-found", component: NotFound, meta: { title: "Page not found" } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const store = useAuthStore();
    document.title = to.meta.title ? `${to.meta.title} — Afya Plaza` : "Afya Plaza";

    // If user data hasn't loaded yet but we have a token, fetch it first
    if (store.isAuthenticated && !store.user) {
        await store.fetchMe();
    }

    if (to.meta.requiresSuperAdmin && !store.isSuperAdmin) { next({ name: "home" }); return; }
    if (to.meta.requiresDoctor && !store.isDoctor) { next({ name: "home" }); return; }
    if (to.meta.requiresFacility && !store.isFacility) { next({ name: "home" }); return; }
    if (to.meta.requiresAuth && !store.isAuthenticated) { next({ name: "login" }); return; }
    next();
});

export default router;
