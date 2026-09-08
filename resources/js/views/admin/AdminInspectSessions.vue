<template>
    <div>
        <div v-if="store.isInspecting" class="mb-4">
            <div class="d-flex align-center mb-1">
                <v-icon icon="mdi-shield-account" color="red-darken-2" size="20" class="mr-2"></v-icon>
                <span class="text-caption text-medium-emphasis">{{ operatorLabel }} — Inspecting</span>
            </div>
            <h1 class="text-h5 font-weight-bold">{{ workspaceName }}</h1>
        </div>
        <v-alert v-if="!store.isInspecting" type="warning" variant="tonal" class="mb-4">No workspace selected. <v-btn to="/admin/dashboard" variant="text" size="small">Go to Dashboard</v-btn></v-alert>
        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
        <v-card v-if="store.isInspecting && !loading" variant="outlined">
            <v-card-title class="text-subtitle-1 font-weight-bold">Clinic Sessions</v-card-title>
            <v-divider></v-divider>
            <v-data-table :headers="headers" :items="sessions" :items-per-page="20" density="comfortable">
                <template #item.status="{ item }"><v-chip size="small" :color="statusColor(item.status)" variant="tonal">{{ item.status }}</v-chip></template>
                <template #item.start_time="{ item }">{{ item.start_time }} – {{ item.end_time }}</template>
            </v-data-table>
        </v-card>
    </div>
</template>
<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
import { useAuthStore } from "../../stores/authStore";
const store = useAdminWorkspaceStore();
const auth = useAuthStore();
const operatorLabel = computed(() => auth.isSuperAdmin ? "Super Admin" : "Platform Admin");
const sessions = ref([]);
const loading = ref(false);
const error = ref(null);
const workspaceName = computed(() => {
    if (!store.currentInspection || !store.currentInspection.data) return "...";
    return store.inspectionType === "doctor"
        ? (store.currentInspection.data.doctor && store.currentInspection.data.doctor.name) || "..."
        : (store.currentInspection.data.facility && store.currentInspection.data.facility.name) || "...";
});
async function load() {
    if (!store.isInspecting || !store.inspectionId) return;
    loading.value = true; error.value = null;
    try {
        const res = store.inspectionType === "doctor"
            ? await store.inspectDoctorSessions(store.inspectionId)
            : await store.inspectFacilitySessions(store.inspectionId);
        sessions.value = res.data || [];
    } catch (e) { error.value = e.response?.data?.message || "Failed to load sessions"; }
    finally { loading.value = false; }
}
onMounted(load);
watch(() => store.inspectionId, load);
const headers = [
    { title: "Date", key: "session_date", sortable: true },
    { title: "Time", key: "start_time", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Doc. Conf.", key: "doctor_confirmation", sortable: true },
    { title: "Fac. Conf.", key: "facility_confirmation", sortable: true },
    { title: "Booked", key: "booked_appointments", sortable: false },
];
function statusColor(s) { return s === "confirmed" ? "success" : s === "pending" ? "warning" : "error"; }
</script>
