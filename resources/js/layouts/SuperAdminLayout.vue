<template>
    <v-app>
        <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="surface" class="border-e">
            <v-list-item class="pa-4">
                <template #prepend v-if="rail"><v-icon icon="mdi-shield-account" color="red-darken-2"></v-icon></template>
                <template v-if="!rail">
                    <div class="d-flex align-center">
                        <v-icon icon="mdi-shield-account" color="red-darken-2" class="mr-2"></v-icon>
                        <div>
                            <div class="text-subtitle-2 font-weight-bold text-red-darken-2">Afya Plaza</div>
                            <div class="text-caption text-medium-emphasis">Control Centre</div>
                        </div>
                    </div>
                </template>
            </v-list-item>
            <v-divider></v-divider>

            <v-list-item v-if="store.isInspecting" class="px-3 py-2 bg-blue-grey-lighten-5">
                <template #prepend>
                    <v-btn icon="mdi-arrow-left" size="x-small" variant="text" @click="onExitInspection" class="mr-1"></v-btn>
                </template>
                <v-list-item-title class="text-body-2 font-weight-bold">{{ inspectionTitle }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">{{ store.inspectionType }} workspace</v-list-item-subtitle>
            </v-list-item>
            <v-divider v-if="store.isInspecting"></v-divider>

            <v-list density="compact" nav>
                <v-list-item to="/admin/dashboard" prepend-icon="mdi-view-dashboard" title="Dashboard" value="dashboard" :active="isDashboardActive"></v-list-item>
                <v-list-item to="/admin/administrators" prepend-icon="mdi-account-group" title="Administrators" value="administrators" :active="isAdministratorsActive"></v-list-item>
                <v-divider class="my-1"></v-divider>
                <v-list-item to="/admin/verifications/doctors" prepend-icon="mdi-doctor-check" title="Pending Doctors" value="pendingDoctors">
                    <template #append><v-chip v-if="pendingDoctorCount" size="x-small" color="warning" variant="flat">{{ pendingDoctorCount }}</v-chip></template>
                </v-list-item>
                <v-list-item to="/admin/verifications/facilities" prepend-icon="mdi-hospital-building" title="Pending Facilities" value="pendingFacilities">
                    <template #append><v-chip v-if="pendingFacilityCount" size="x-small" color="warning" variant="flat">{{ pendingFacilityCount }}</v-chip></template>
                </v-list-item>
                <v-list-item to="/admin/finance" prepend-icon="mdi-finance" title="Marketplace Finance" value="finance" :active="route.path === '/admin/finance'"></v-list-item>
                <v-list-item to="/admin/audit" prepend-icon="mdi-history" title="Audit Log" value="audit" :active="route.path === '/admin/audit'"></v-list-item>
                <template v-if="store.isInspecting && store.inspectionType === 'doctor'">
                    <v-divider class="my-1"></v-divider>
                    <v-list-subheader v-if="!rail" class="text-caption text-uppercase">Doctor Workspace</v-list-subheader>
                    <v-list-item :to="inspectPath('sessions')" prepend-icon="mdi-calendar-clock" title="Sessions" value="iDocSessions" :active="isSessionActive"></v-list-item>
                    <v-list-item :to="inspectPath('appointments')" prepend-icon="mdi-calendar-check" title="Appointments" value="iDocAppts" :active="isApptActive"></v-list-item>
                    <v-list-item :to="inspectPath('profile')" prepend-icon="mdi-card-account-details" title="Profile" value="iDocProfile" :active="isProfileActive"></v-list-item>
                </template>
                <template v-if="store.isInspecting && store.inspectionType === 'facility'">
                    <v-divider class="my-1"></v-divider>
                    <v-list-subheader v-if="!rail" class="text-caption text-uppercase">Facility Workspace</v-list-subheader>
                    <v-list-item :to="inspectPath('sessions')" prepend-icon="mdi-calendar-clock" title="Sessions" value="iFacSessions" :active="isSessionActive"></v-list-item>
                    <v-list-item :to="inspectPath('appointments')" prepend-icon="mdi-calendar-check" title="Appointments" value="iFacAppts" :active="isApptActive"></v-list-item>
                    <v-list-item :to="inspectPath('profile')" prepend-icon="mdi-domain" title="Profile" value="iFacProfile" :active="isProfileActive"></v-list-item>
                </template>
            </v-list>

            <template #append>
                <v-divider></v-divider>
                <v-list-item v-if="!rail" prepend-icon="mdi-chevron-left" title="Collapse" @click="rail = !rail"></v-list-item>
                <v-list-item v-else prepend-icon="mdi-chevron-right" @click="rail = !rail"></v-list-item>
                <v-divider></v-divider>
                <v-list-item class="pa-3">
                    <template #prepend><v-avatar size="36" color="red-darken-2"><span class="text-white text-caption font-weight-bold">{{ userInitials }}</span></v-avatar></template>
                    <v-list-item-title class="text-body-2 font-weight-medium">{{ auth.user && auth.user.name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">Super Admin</v-list-item-subtitle>
                    <template #append><v-btn icon="mdi-logout" size="small" variant="text" @click="handleLogout"></v-btn></template>
                </v-list-item>
            </template>
        </v-navigation-drawer>

        <v-app-bar color="surface" elevation="0" class="border-b d-md-none">
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
            <v-app-bar-title class="text-body-2 font-weight-bold text-red-darken-2">Control Centre</v-app-bar-title>
        </v-app-bar>

        <v-main class="bg-surface">
            <v-container fluid class="pa-4 pa-md-6">
                <router-view v-slot="{ Component }">
                    <transition name="fade" mode="out-in"><component :is="Component" /></transition>
                </router-view>
            </v-container>
        </v-main>
    </v-app>
</template>
<script setup>
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/authStore";
import { useAdminWorkspaceStore } from "../stores/adminWorkspaceStore";

const auth = useAuthStore();
const store = useAdminWorkspaceStore();
const route = useRoute();
const router = useRouter();
const drawer = ref(true);
const rail = ref(false);

const userInitials = computed(() => {
    if (!auth.user || !auth.user.name) return "?";
    return auth.user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
});

const inspectionTitle = computed(() => {
    if (!store.currentInspection || !store.currentInspection.data) return "";
    return store.inspectionType === "doctor"
        ? (store.currentInspection.data.doctor && store.currentInspection.data.doctor.name) || ""
        : (store.currentInspection.data.facility && store.currentInspection.data.facility.name) || "";
});

const isDashboardActive = computed(() => route.path === "/admin/dashboard" && !store.isInspecting);
const isAdministratorsActive = computed(() => route.path.startsWith("/admin/administrators"));
const isSessionActive = computed(() => route.path.includes("/sessions"));
const isApptActive = computed(() => route.path.includes("/appointments"));
const isProfileActive = computed(() => route.path.includes("/profile"));
const pendingDoctorCount = computed(() => store.dashboard && store.dashboard.counts && store.dashboard.counts.pending_doctor_verification);
const pendingFacilityCount = computed(() => store.dashboard && store.dashboard.counts && store.dashboard.counts.pending_facility_verification);

function inspectPath(section) {
    if (!store.isInspecting || !store.inspectionId) return "/admin/dashboard";
    return `/admin/inspect/${store.inspectionType}/${store.inspectionId}/${section}`;
}

function onExitInspection() { store.clearInspection(); router.push("/admin/dashboard"); }
async function handleLogout() { await auth.logout(); router.push("/"); }
</script>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>