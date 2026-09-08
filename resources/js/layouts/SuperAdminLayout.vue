<template>
    <v-app>
        <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="surface" class="border-e">
            <v-list-item class="pa-4">
                <template #prepend v-if="rail"><v-icon icon="mdi-stethoscope" color="primary"></v-icon></template>
                <template v-if="!rail">
                    <div class="drawer-brand">
                        <div class="drawer-brand-icon"><v-icon icon="mdi-stethoscope" size="20"></v-icon></div>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold text-primary" style="line-height: 1.2;">Afya Plaza</div>
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
                <v-list-subheader v-if="!rail" class="nav-group-label">Workspace</v-list-subheader>
                <v-list-item to="/admin/dashboard" prepend-icon="mdi-view-dashboard" title="Dashboard" value="dashboard" :active="isDashboardActive"></v-list-item>
                <v-divider class="my-1"></v-divider>

                <v-list-subheader v-if="!rail" class="nav-group-label">Administration</v-list-subheader>
                <v-list-item to="/admin/administrators" prepend-icon="mdi-account-group" title="Administrators" value="administrators" :active="isAdministratorsActive"></v-list-item>
                <v-list-item to="/admin/users" prepend-icon="mdi-account-multiple" title="Users" value="users" :active="route.path === '/admin/users'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/roles" prepend-icon="mdi-shield-account" title="Roles" value="roles" :active="route.path === '/admin/roles'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/privileges" prepend-icon="mdi-account-star" title="Privileges" value="privileges" :active="route.path === '/admin/privileges'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/permissions" prepend-icon="mdi-key" title="Permissions" value="permissions" :active="route.path === '/admin/permissions'"></v-list-item>
                <v-divider class="my-1"></v-divider>

                <v-list-subheader v-if="!rail" class="nav-group-label">Marketplace</v-list-subheader>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/marketplace/doctors" prepend-icon="mdi-doctor" title="Doctors" value="admDoctors" :active="route.path === '/admin/marketplace/doctors'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/marketplace/facilities" prepend-icon="mdi-hospital-building" title="Facilities" value="admFacilities" :active="route.path === '/admin/marketplace/facilities'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/specialties" prepend-icon="mdi-creation" title="Specialties" value="admSpecialties" :active="route.path === '/admin/specialties'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/services" prepend-icon="mdi-clipboard-pulse" title="Services" value="admServices" :active="route.path === '/admin/services'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/relationships" prepend-icon="mdi-link-variant" title="Doctor Relationships" value="admRelationships" :active="isRelationshipsActive"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/sessions" prepend-icon="mdi-calendar-clock" title="Clinic Sessions" value="admSessions" :active="route.path === '/admin/sessions'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/appointments" prepend-icon="mdi-calendar-check" title="Appointments" value="admAppointments" :active="route.path === '/admin/appointments'"></v-list-item>
                <v-divider class="my-1"></v-divider>

                <v-list-subheader v-if="!rail" class="nav-group-label">Verification</v-list-subheader>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/verification-requests?type=doctor" prepend-icon="mdi-doctor-check" title="Doctor Verification" value="vDoctors">
                    <template #append><v-chip v-if="pendingDoctorCount" size="x-small" color="warning" variant="flat">{{ pendingDoctorCount }}</v-chip></template>
                </v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/verification-requests?type=facility" prepend-icon="mdi-hospital-box" title="Facility Verification" value="vFacilities">
                    <template #append><v-chip v-if="pendingFacilityCount" size="x-small" color="warning" variant="flat">{{ pendingFacilityCount }}</v-chip></template>
                </v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/relationships?status=pending" prepend-icon="mdi-link-variant" title="Relationship Verification" value="vRelationships" :active="isRelationshipsPendingActive"></v-list-item>
                <v-divider class="my-1"></v-divider>

                <v-list-subheader v-if="!rail" class="nav-group-label">Finance</v-list-subheader>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/plans" prepend-icon="mdi-tag-multiple" title="Plans" value="plans" :active="route.path === '/admin/plans'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/subscriptions" prepend-icon="mdi-bank" title="Subscriptions" value="subscriptions" :active="route.path === '/admin/subscriptions'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/transactions" prepend-icon="mdi-swap-horizontal" title="Transactions" value="admTransactions" :active="route.path === '/admin/transactions'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/commissions" prepend-icon="mdi-percent" title="Commissions" value="admCommissions" :active="route.path === '/admin/commissions'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/payouts" prepend-icon="mdi-cash-multiple" title="Payouts" value="payouts" :active="route.path === '/admin/payouts'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/settlements" prepend-icon="mdi-bank-transfer" title="Settlements" value="settlements" :active="route.path === '/admin/settlements'"></v-list-item>
                <v-divider class="my-1"></v-divider>

                <v-list-subheader v-if="!rail" class="nav-group-label">Insights</v-list-subheader>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/reports" prepend-icon="mdi-file-chart" title="Reports" value="admReports" :active="route.path === '/admin/reports'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/analytics" prepend-icon="mdi-chart-line" title="Analytics" value="admAnalytics" :active="route.path === '/admin/analytics'"></v-list-item>
                <v-divider class="my-1"></v-divider>

                <v-list-subheader v-if="!rail" class="nav-group-label">System</v-list-subheader>
                <v-list-item to="/admin/notifications" prepend-icon="mdi-bell" title="Notifications" value="admNotifications" :active="route.path === '/admin/notifications'"></v-list-item>
                <v-list-item v-if="auth.isSuperAdmin" to="/admin/settings" prepend-icon="mdi-cog" title="Settings" value="admSettings" :active="route.path === '/admin/settings'"></v-list-item>
                <v-list-item to="/admin/audit" prepend-icon="mdi-history" title="Audit Logs" value="audit" :active="route.path === '/admin/audit'"></v-list-item>
                <template v-if="store.isInspecting && store.inspectionType === 'doctor'">
                    <v-divider class="my-1"></v-divider>
                    <v-list-subheader v-if="!rail" class="nav-group-label">Doctor Workspace</v-list-subheader>
                    <v-list-item :to="inspectPath('sessions')" prepend-icon="mdi-calendar-clock" title="Sessions" value="iDocSessions" :active="isSessionActive"></v-list-item>
                    <v-list-item :to="inspectPath('appointments')" prepend-icon="mdi-calendar-check" title="Appointments" value="iDocAppts" :active="isApptActive"></v-list-item>
                    <v-list-item :to="inspectPath('profile')" prepend-icon="mdi-card-account-details" title="Profile" value="iDocProfile" :active="isProfileActive"></v-list-item>
                </template>
                <template v-if="store.isInspecting && store.inspectionType === 'facility'">
                    <v-divider class="my-1"></v-divider>
                    <v-list-subheader v-if="!rail" class="nav-group-label">Facility Workspace</v-list-subheader>
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
                    <template #prepend><v-avatar size="36" color="primary"><span class="text-white text-caption font-weight-bold">{{ userInitials }}</span></v-avatar></template>
                    <v-list-item-title class="text-body-2 font-weight-medium">{{ auth.user && auth.user.name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">{{ auth.isSuperAdmin ? "Super Admin" : "Platform Admin" }}</v-list-item-subtitle>
                    <template #append><v-btn icon="mdi-logout" size="small" variant="text" @click="handleLogout"></v-btn></template>
                </v-list-item>
            </template>
        </v-navigation-drawer>

        <v-app-bar color="surface" elevation="0" class="border-b d-md-none">
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
            <v-app-bar-title class="text-body-2 font-weight-bold text-primary">Control Centre</v-app-bar-title>
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
const isRelationshipsActive = computed(() => route.path === "/admin/relationships" && route.query.status !== "pending");
const isRelationshipsPendingActive = computed(() => route.path === "/admin/relationships" && route.query.status === "pending");
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