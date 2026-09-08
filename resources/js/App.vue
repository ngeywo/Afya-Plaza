<template>
    <v-app>
        <v-app-bar color="surface" elevation="1" class="border-b">
            <v-container class="d-flex align-center pa-0">
                <router-link to="/" class="text-decoration-none d-flex align-center">
                    <v-icon icon="mdi-stethoscope" color="primary" size="28" class="mr-2"></v-icon>
                    <span class="text-h6 font-weight-bold text-primary">Afya Plaza</span>
                </router-link>
                <v-spacer></v-spacer>
                <v-btn variant="text" to="/search" prepend-icon="mdi-magnify" class="d-none d-md-flex">Find Doctors</v-btn>
                <v-btn variant="text" to="/search?specialty=orthopaedics" class="d-none d-md-flex">Specialties</v-btn>
                <v-btn variant="text" to="/my-doctors" prepend-icon="mdi-doctor" class="d-none d-md-flex" v-if="auth.isAuthenticated">My Doctors</v-btn>
                <v-btn variant="text" to="/my-appointments" prepend-icon="mdi-calendar-check" class="d-none d-md-flex" v-if="auth.isAuthenticated">My Appointments</v-btn>

                <!-- Not authenticated -->
                <template v-if="!auth.isAuthenticated">
                    <v-btn variant="text" to="/login">Sign in</v-btn>
                    <v-btn color="primary" variant="flat" to="/register" class="ml-2">Sign up</v-btn>
                </template>

                <!-- Authenticated -->
                <template v-else>
                    <NotificationBell v-if="auth.isPatient" class="mr-1" />
                    <v-btn v-if="auth.isDoctor" color="primary" variant="tonal" to="/doctor/dashboard" class="mr-2 d-none d-md-flex" prepend-icon="mdi-doctor">Doctor Workspace</v-btn>
                    <v-btn v-if="auth.isFacility" color="secondary" variant="tonal" to="/facility/dashboard" class="mr-2 d-none d-md-flex" prepend-icon="mdi-hospital-building">Facility Workspace</v-btn>
                    <v-btn v-if="auth.isPlatformOperator" color="secondary" variant="tonal" to="/admin/dashboard" class="mr-2 d-none d-md-flex" prepend-icon="mdi-shield-account">Control Centre</v-btn>
                    <v-menu>
                        <template v-slot:activator="{ props }">
                            <v-btn icon v-bind="props"><v-avatar size="32" color="primary"><span class="text-white font-weight-bold">{{ userInitials }}</span></v-avatar></v-btn>
                        </template>
                        <v-list density="compact">
                            <v-list-item>
                                <v-list-item-title class="font-weight-medium">{{ auth.user?.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">{{ (auth.user?.roles || []).join(", ") }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-divider></v-divider>
                            <v-list-item v-if="auth.isDoctor" to="/doctor/dashboard" prepend-icon="mdi-doctor"><v-list-item-title>Doctor Workspace</v-list-item-title></v-list-item>
                            <v-list-item v-if="auth.isFacility" to="/facility/dashboard" prepend-icon="mdi-hospital-building"><v-list-item-title>Facility Workspace</v-list-item-title></v-list-item>
                            <v-list-item v-if="auth.isPlatformOperator" to="/admin/dashboard" prepend-icon="mdi-shield-account"><v-list-item-title>Control Centre</v-list-item-title></v-list-item>
                            <v-list-item v-if="auth.isPatient" to="/my-doctors" prepend-icon="mdi-doctor"><v-list-item-title>My Doctors</v-list-item-title></v-list-item>
                            <v-list-item to="/my-appointments" prepend-icon="mdi-calendar-check"><v-list-item-title>My Appointments</v-list-item-title></v-list-item>
                            <v-divider></v-divider>
                            <v-list-item @click="handleLogout" prepend-icon="mdi-logout"><v-list-item-title>Sign out</v-list-item-title></v-list-item>
                        </v-list>
                    </v-menu>
                </template>
            </v-container>
        </v-app-bar>

        <v-main class="bg-surface">
            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </v-main>

        <v-footer color="surface" class="border-t py-8">
            <v-container>
                <v-row>
                    <v-col cols="12" md="4">
                        <div class="d-flex align-center mb-3">
                            <v-icon icon="mdi-stethoscope" color="primary" size="24" class="mr-2"></v-icon>
                            <span class="text-h6 font-weight-bold">Afya Plaza</span>
                        </div>
                        <p class="text-body-2 text-medium-emphasis">The doctor-centric healthcare marketplace.</p>
                    </v-col>
                    <v-col cols="6" md="2">
                        <div class="text-subtitle-2 font-weight-bold mb-2">For Patients</div>
                        <ul class="list-unstyled">
                            <li><router-link to="/search" class="text-body-2 text-medium-emphasis">Find a Doctor</router-link></li>
                            <li><router-link to="/login" class="text-body-2 text-medium-emphasis">Sign In</router-link></li>
                        </ul>
                    </v-col>
                    <v-col cols="6" md="2">
                        <div class="text-subtitle-2 font-weight-bold mb-2">For Doctors</div>
                        <ul class="list-unstyled"><li><a href="#" class="text-body-2 text-medium-emphasis">List Your Practice</a></li></ul>
                    </v-col>
                </v-row>
                <v-divider class="my-6"></v-divider>
                <div class="text-caption text-medium-emphasis">© {{ new Date().getFullYear() }} Afya Plaza. All rights reserved.</div>
            </v-container>
        </v-footer>
    </v-app>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "./stores/authStore";
import NotificationBell from "./components/NotificationBell.vue";

const auth = useAuthStore();
const router = useRouter();

const userInitials = computed(() => {
    if (!auth.user?.name) return "?";
    return auth.user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
});

async function handleLogout() {
    await auth.logout();
    router.push("/");
}

onMounted(() => {
    if (auth.token) auth.fetchMe();
});
</script>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
