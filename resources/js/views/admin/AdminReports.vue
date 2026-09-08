<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Reports</h1>
                <p class="text-body-2 text-medium-emphasis">Platform-wide operational snapshot for the last {{ days }} days.</p>
            </div>
            <v-select v-model="days" :items="dayOptions" label="Period" variant="outlined" density="compact" hide-details class="flex-grow-0" style="max-width: 160px" @update:model-value="load"></v-select>
        </div>
        <v-row>
            <v-col v-for="c in countCards" :key="c.key" cols="6" md="3">
                <v-card variant="outlined"><v-card-text>
                    <div class="text-caption text-medium-emphasis">{{ c.label }}</div>
                    <div class="text-h6 font-weight-bold" :class="c.color">{{ c.value !== undefined ? Number(c.value).toLocaleString() : "—" }}</div>
                </v-card-text></v-card>
            </v-col>
        </v-row>
        <v-row class="mt-2">
            <v-col cols="12">
                <v-card variant="outlined">
                    <v-card-title class="text-subtitle-1 font-weight-bold">Daily appointments ({{ days }} days)</v-card-title>
                    <v-sheet height="320" class="pa-2">
                        <div v-if="daily.length" class="d-flex align-end" style="height: 260px; column-gap: 2px">
                            <div v-for="d in daily" :key="d.date" class="d-flex flex-column align-center justify-end" style="flex:1; min-width: 0">
                                <div class="text-caption font-weight-bold">{{ d.count }}</div>
                                <div class="bar" :style="'height:' + barHeight(d.count) + 'px'"></div>
                            </div>
                        </div>
                        <div v-else class="text-center text-medium-emphasis py-10">No data for this period.</div>
                    </v-sheet>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>
<style scoped>
    .bar { width: 70%; background: rgb(var(--v-theme-primary)); border-radius: 3px 3px 0 0; min-height: 2px; }
</style>
<script setup>
import { ref, computed, onMounted } from "vue";
import { adminService } from "../../services/adminService";

const loading = ref(false);
const error = ref(null);
const days = ref(30);
const dayOptions = [{ title: "30 days", value: 30 }, { title: "90 days", value: 90 }, { title: "365 days", value: 365 }];
const counts = ref({});
const daily = ref([]);

const countCards = computed(() => [
    { key: "total_facilities", label: "Facilities", value: counts.value.total_facilities },
    { key: "total_doctors", label: "Doctors", value: counts.value.total_doctors },
    { key: "total_patients", label: "Patients", value: counts.value.total_patients },
    { key: "appointments_today", label: "Appointments today", value: counts.value.appointments_today },
    { key: "appointments_this_month", label: "Appointments this month", value: counts.value.appointments_this_month },
    { key: "active_subscriptions", label: "Active subscriptions", value: counts.value.active_subscriptions },
    { key: "revenue", label: "Revenue (KES)", value: counts.value.revenue, color: "text-success" },
    { key: "commissions", label: "Commissions (KES)", value: counts.value.commissions, color: "text-primary" },
    { key: "pending_doctor_verifications", label: "Pending doctor verifications", value: counts.value.pending_doctor_verifications, color: "text-warning" },
    { key: "pending_facility_verifications", label: "Pending facility verifications", value: counts.value.pending_facility_verifications, color: "text-warning" },
    { key: "cancelled_clinics", label: "Cancelled clinics today", value: counts.value.cancelled_clinics, color: "text-error" },
    { key: "problem_appointments", label: "Problem appointments today", value: counts.value.problem_appointments, color: "text-error" },
]);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const data = await adminService.getReports({ days: days.value });
        counts.value = data.counts || {};
        daily.value = (data.daily_appointments || []).slice(-30);
    } catch (e) {
        error.value = "Failed to load reports.";
    } finally {
        loading.value = false;
    }
}

function barHeight(c) {
    const max = Math.max(...daily.value.map(d => d.count), 1);
    return Math.max(2, Math.round((c / max) * 220));
}

onMounted(load);
</script>