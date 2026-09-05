<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading && !store.dashboard" indeterminate></v-progress-linear>
        <template v-if="store.dashboard">
            <div class="d-flex align-center mb-6">
                <v-icon icon="mdi-shield-account" color="red-darken-2" size="40" class="mr-3"></v-icon>
                <div>
                    <h1 class="text-h5 font-weight-bold">Control Centre</h1>
                    <div class="text-caption text-medium-emphasis">Super Admin Dashboard</div>
                </div>
            </div>
            <v-row class="mb-6">
                <v-col cols="6" md="3" v-for="(tile, i) in tiles" :key="i">
                    <v-card variant="outlined" class="pa-4 h-100">
                        <div class="d-flex align-center justify-space-between mb-2">
                            <span class="text-caption text-medium-emphasis text-uppercase" style="letter-spacing:0.5px;">{{ tile.label }}</span>
                            <v-icon :icon="tile.icon" :color="tile.color" size="20"></v-icon>
                        </div>
                        <div class="text-h4 font-weight-bold">{{ store.dashboard.counts[tile.key] ?? 0 }}</div>
                    </v-card>
                </v-col>
            </v-row>
            <v-card variant="outlined">
                <v-card-title class="text-subtitle-1 font-weight-bold">Facilities Active Today</v-card-title>
                <v-divider></v-divider>
                <v-data-table :headers="facilityHeaders" :items="store.dashboard.facilities_today" :items-per-page="5" density="comfortable">
                    <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
                    <template #item.session_count="{ item }"><v-chip size="small" color="primary" variant="tonal">{{ item.session_count }} sessions</v-chip></template>
                </v-data-table>
            </v-card>
        </template>
    </div>
</template>
<script setup>
import { onMounted } from "vue";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
const store = useAdminWorkspaceStore();
onMounted(() => { if (!store.dashboard) store.fetchDashboard(); });
const tiles = [
    { key: "total_doctors", label: "Total Doctors", icon: "mdi-doctor", color: "primary" },
    { key: "total_facilities", label: "Total Facilities", icon: "mdi-hospital-building", color: "secondary" },
    { key: "todays_sessions", label: "Sessions Today", icon: "mdi-calendar-clock", color: "info" },
    { key: "todays_appointments", label: "Appointments Today", icon: "mdi-calendar-check", color: "success" },
    { key: "pending_doctor_verification", label: "Pending Doctor Verify", icon: "mdi-doctor-check", color: "warning" },
    { key: "pending_facility_verification", label: "Pending Facility Verify", icon: "mdi-hospital-building", color: "warning" },
    { key: "pending_doctor_confirmations", label: "Pending Doc Confirm", icon: "mdi-doctor", color: "orange" },
    { key: "pending_facility_confirmations", label: "Pending Fac Confirm", icon: "mdi-hospital-building", color: "orange" },
];
const facilityHeaders = [
    { title: "Facility", key: "name", sortable: true },
    { title: "City", key: "city", sortable: false },
    { title: "Doctors", key: "doctor_count", sortable: true },
    { title: "Sessions", key: "session_count", sortable: true },
];
</script>
