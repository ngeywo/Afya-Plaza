import { createRouter, createWebHistory } from "vue-router";
import PatientHome from "../views/patient/Home.vue";
import DoctorSearch from "../views/patient/DoctorSearch.vue";
import DoctorProfile from "../views/patient/DoctorProfile.vue";
import LoginPage from "../views/patient/LoginPage.vue";
import MyAppointments from "../views/patient/MyAppointments.vue";

const routes = [
    { path: "/", name: "home", component: PatientHome, meta: { title: "Find Your Doctor" } },
    { path: "/search", name: "doctor-search", component: DoctorSearch, meta: { title: "Search Doctors" } },
    { path: "/doctors/:slug", name: "doctor-profile", component: DoctorProfile, meta: { title: "Doctor Profile" } },
    { path: "/login", name: "login", component: LoginPage, meta: { title: "Sign In" } },
    { path: "/my-appointments", name: "my-appointments", component: MyAppointments, meta: { title: "My Appointments", requiresAuth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} — Docta Plaza` : "Docta Plaza";
});

export default router;
