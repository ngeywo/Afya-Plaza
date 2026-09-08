<template>
    <div>
        <AppErrorState
            v-if="store.error && !store.dashboard"
            title="We couldn't load the Control Centre"
            :message="store.error"
            class="mb-4"
            @retry="store.fetchDashboard()"
        />
        <AppLoadingState v-else-if="store.loading && !store.dashboard" variant="board" :rows="2" />

        <template v-else-if="store.dashboard">
            <AppPageHeader title="Control Centre" :subtitle="auth.isSuperAdmin ? 'Super Admin Dashboard' : 'Platform Admin Dashboard'" icon="mdi-shield-account" />

            <v-row class="mb-6" dense>
                <v-col cols="6" md="3" v-for="(tile, i) in tiles" :key="i">
                    <AppStatCard :label="tile.label" :value="store.dashboard.counts[tile.key] ?? 0" :icon="tile.icon" :tone="tile.tone" :color="tile.color" />
                </v-col>
            </v-row>

            <v-card v-if="attentionItems.length" variant="tonal" color="warning" class="mb-6">
                <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center">
                    <v-icon icon="mdi-alert-decagram" color="warning" class="mr-2"></v-icon>Requires Attention
                </v-card-title>
                <v-divider></v-divider>
                <v-list density="compact" color="transparent">
                    <v-list-item v-for="(item, i) in attentionItems" :key="i" :to="item.to" :prepend-icon="item.icon">
                        <template #title>
                            <span>{{ item.label }}</span>
                        </template>
                        <template #append>
                            <AppStatusChip status="pending" :label="`${item.count}`" variant="flat" size="small" />
                        </template>
                    </v-list-item>
                </v-list>
            </v-card>

            <v-card variant="outlined">
                <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center gap-2">
                    <v-icon icon="mdi-hospital-building" color="primary"></v-icon>Facilities Active Today
                </v-card-title>
                <v-divider></v-divider>
                <v-data-table :headers="facilityHeaders" :items="store.dashboard.facilities_today" :items-per-page="5" density="comfortable">
                    <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
                    <template #item.doctor_count="{ item }"><span>{{ item.doctor_count ?? 0 }}</span></template>
                    <template #item.session_count="{ item }"><v-chip size="small" color="primary" variant="tonal">{{ item.session_count }} sessions</v-chip></template>
                </v-data-table>
            </v-card>
        </template>
    </div>
</template>
<script setup>
import { computed, onMounted } from "vue";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
import { useAuthStore } from "../../stores/authStore";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import AppStatCard from "../../components/ui/AppStatCard.vue";
import AppStatusChip from "../../components/ui/AppStatusChip.vue";
import AppLoadingState from "../../components/ui/AppLoadingState.vue";
import AppErrorState from "../../components/ui/AppErrorState.vue";
const store = useAdminWorkspaceStore();
const auth = useAuthStore();
onMounted(() => { if (!store.dashboard) store.fetchDashboard(); });
const tiles = [
    { key: "total_doctors", label: "Total Doctors", icon: "mdi-doctor", tone: "neutral", color: "primary" },
    { key: "total_facilities", label: "Total Facilities", icon: "mdi-hospital-building", tone: "neutral" },
    { key: "todays_sessions", label: "Sessions Today", icon: "mdi-calendar-clock", tone: "neutral" },
    { key: "todays_appointments", label: "Appointments Today", icon: "mdi-calendar-check", tone: "neutral" },
    { key: "pending_doctor_verification", label: "Pending Doctor Verify", icon: "mdi-doctor-check", tone: "warning", color: "warning" },
    { key: "pending_facility_verification", label: "Pending Facility Verify", icon: "mdi-hospital-building", tone: "warning", color: "warning" },
    { key: "pending_doctor_confirmations", label: "Pending Doc Confirm", icon: "mdi-doctor", tone: "warning", color: "warning" },
    { key: "pending_facility_confirmations", label: "Pending Fac Confirm", icon: "mdi-hospital-building", tone: "warning", color: "warning" },
];
const facilityHeaders = [
    { title: "Facility", key: "name", sortable: true },
    { title: "City", key: "city", sortable: false },
    { title: "Doctors", key: "doctor_count", sortable: true },
    { title: "Sessions", key: "session_count", sortable: true },
];
const attentionItems = computed(() => {
    const c = store.dashboard.counts;
    const items = [];
    const add = (label, icon, count, to) => { if (count > 0) items.push({ label, icon, count, to }); };
    if (auth.isSuperAdmin) {
        add("Pending Doctor Verification", "mdi-doctor-check", c.pending_doctor_verification, { path: "/admin/verification-requests", query: { type: "doctor" } });
        add("Pending Facility Verification", "mdi-hospital-building", c.pending_facility_verification, { path: "/admin/verification-requests", query: { type: "facility" } });
    }
    add("Pending Doctor Confirmations", "mdi-doctor", c.pending_doctor_confirmations, { path: "/admin/sessions" });
    add("Pending Facility Confirmations", "mdi-hospital-building", c.pending_facility_confirmations, { path: "/admin/sessions" });
    return items;
});
</script>