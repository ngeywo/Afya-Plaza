<template>
    <v-app>
        <!-- Facility sidebar navigation -->
        <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="surface" class="border-e">
            <!-- Header -->
            <v-list-item class="pa-4" :prepend-icon="rail ? 'mdi-hospital-building' : undefined">
                <template v-if="!rail">
                    <div class="d-flex align-center">
                        <v-icon icon="mdi-hospital-building" color="secondary" class="mr-2"></v-icon>
                        <div>
                            <div class="text-subtitle-2 font-weight-bold text-secondary">Afya Plaza</div>
                            <div class="text-caption text-medium-emphasis">Facility Workspace</div>
                        </div>
                    </div>
                </template>
            </v-list-item>
            <v-divider></v-divider>

            <!-- Navigation items — Section 31 -->
            <v-list density="compact" nav>
                <v-list-item to="/facility/dashboard" prepend-icon="mdi-view-dashboard" title="Dashboard" value="dashboard" :active="route.path === '/facility/dashboard'"></v-list-item>
                <v-divider class="my-1"></v-divider>
                <v-list-subheader v-if="!rail" class="text-caption text-uppercase" style="letter-spacing:1px;">Operations</v-list-subheader>
                <v-list-item to="/facility/doctors" prepend-icon="mdi-doctor" title="My Doctors" value="doctors" :active="route.path.startsWith('/facility/doctors')"></v-list-item>
                <v-list-item to="/facility/clinic-sessions" prepend-icon="mdi-calendar-clock" title="Clinic Sessions" value="sessions" :active="route.path.startsWith('/facility/clinic-sessions')"></v-list-item>
                <v-list-item to="/facility/appointments" prepend-icon="mdi-calendar-account" title="Appointments" value="appointments" :active="route.path.startsWith('/facility/appointments')"></v-list-item>
                <v-divider class="my-1"></v-divider>
                <v-list-subheader v-if="!rail" class="text-caption text-uppercase" style="letter-spacing:1px;">Facility</v-list-subheader>
                <v-list-item to="/facility/profile" prepend-icon="mdi-domain" title="Profile" value="profile" :active="route.path === '/facility/profile'"></v-list-item>
                <v-list-item to="/facility/locations" prepend-icon="mdi-map-marker-multiple" title="Locations" value="locations" :active="route.path.startsWith('/facility/locations')"></v-list-item>
                <v-list-item to="/facility/staff" prepend-icon="mdi-account-group" title="Staff" value="staff" :active="route.path.startsWith('/facility/staff')"></v-list-item>
            </v-list>

            <v-spacer></v-spacer>
            <v-divider></v-divider>

            <!-- Collapse toggle -->
            <v-list-item prepend-icon="mdi-chevron-left" title="Collapse" @click="rail = !rail" v-if="!rail"></v-list-item>
            <v-list-item prepend-icon="mdi-chevron-right" @click="rail = !rail" v-else></v-list-item>
            <v-divider></v-divider>

            <!-- User section -->
            <v-list-item class="pa-3">
                <template #prepend>
                    <v-avatar size="36" color="secondary">
                        <span class="text-white text-caption font-weight-bold">{{ userInitials }}</span>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">{{ auth.user?.name }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">Facility</v-list-item-subtitle>
                <template #append>
                    <v-btn icon="mdi-logout" size="small" variant="text" @click="handleLogout"></v-btn>
                </template>
            </v-list-item>
        </v-navigation-drawer>

        <!-- Top app bar for mobile -->
        <v-app-bar color="surface" elevation="0" class="border-b d-md-none">
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
            <v-app-bar-title class="text-body-2 font-weight-bold text-secondary">Afya Plaza — Facility Workspace</v-app-bar-title>
        </v-app-bar>

        <!-- Main content -->
        <v-main class="bg-surface">
            <v-container fluid class="pa-4 pa-md-6">
                <router-view v-slot="{ Component }">
                    <transition name="fade" mode="out-in">
                        <component :is="Component" />
                    </transition>
                </router-view>
            </v-container>
        </v-main>
    </v-app>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/authStore";

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const drawer = ref(true);
const rail = ref(false);

const userInitials = computed(() => {
    if (!auth.user?.name) return "?";
    return auth.user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
});

async function handleLogout() {
    await auth.logout();
    router.push("/");
}
</script>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
