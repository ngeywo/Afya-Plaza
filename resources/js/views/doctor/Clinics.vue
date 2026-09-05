<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4 flex-wrap">
            <div>
                <h1 class="text-h5 font-weight-bold">My Clinics</h1>
                <p class="text-body-2 text-medium-emphasis mt-1">Upcoming and recent clinic sessions across your facilities.</p>
            </div>
            <v-btn-toggle v-model="statusFilter" mandatory density="compact" color="primary" divided>
                <v-btn value="" size="small">All</v-btn>
                <v-btn value="confirmed" size="small">Confirmed</v-btn>
                <v-btn value="pending" size="small">Pending</v-btn>
                <v-btn value="cancelled" size="small">Cancelled</v-btn>
            </v-btn-toggle>
        </div>

        <v-progress-linear v-if="store.loading && store.clinics.length === 0" indeterminate></v-progress-linear>

        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">
            {{ store.error }}
        </v-alert>

        <v-card v-if="!store.loading && store.clinics.length === 0" variant="outlined" class="pa-12 text-center">
            <v-icon icon="mdi-calendar-blank-outline" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h2 class="text-h6 font-weight-bold mb-2">No clinics found</h2>
            <p class="text-body-2 text-medium-emphasis">You currently have no clinic sessions scheduled in this period.</p>
        </v-card>

        <v-row v-else>
            <v-col v-for="s in store.clinics" :key="s.id" cols="12" md="6" lg="4">
                <v-card variant="outlined" class="pa-4 h-100">
                    <div class="d-flex align-start justify-space-between mb-2">
                        <div>
                            <div class="text-caption text-medium-emphasis">{{ s.day }}</div>
                            <div class="text-h6 font-weight-bold">{{ s.facility.name }}</div>
                            <div class="text-body-2 text-medium-emphasis">{{ s.facility.city }}</div>
                        </div>
                        <v-chip :color="statusColor(s)" size="small" variant="tonal">
                            {{ statusLabel(s) }}
                        </v-chip>
                    </div>
                    <v-divider class="my-3"></v-divider>
                    <div class="d-flex align-center mb-2">
                        <v-icon icon="mdi-clock-outline" size="18" class="mr-2" color="medium-emphasis"></v-icon>
                        <span class="text-body-2 font-weight-medium">{{ s.start_time }} – {{ s.end_time }}</span>
                    </div>
                    <div class="d-flex align-center mb-2">
                        <v-icon icon="mdi-account-multiple" size="18" class="mr-2" color="medium-emphasis"></v-icon>
                        <span class="text-body-2">{{ s.booked_appointments }} / {{ s.max_appointments ?? '∞' }} booked</span>
                    </div>
                    <div class="d-flex align-center mb-3">
                        <v-icon icon="mdi-cash" size="18" class="mr-2" color="medium-emphasis"></v-icon>
                        <span class="text-body-2">KSh {{ s.consultation_fee }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <v-btn variant="outlined" size="small" block :to="`/doctor/appointments?session=${s.id}`">Appointments</v-btn>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { useDoctorWorkspaceStore } from "../../stores/doctorWorkspaceStore";

const store = useDoctorWorkspaceStore();
const statusFilter = ref('');

function load() {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;
    store.fetchClinics(params);
}

onMounted(load);
watch(statusFilter, load);

function statusColor(s) {
    if (s.status === 'cancelled') return 'error';
    if (s.is_confirmed) return 'success';
    return 'warning';
}

function statusLabel(s) {
    if (s.status === 'cancelled') return 'Cancelled';
    if (s.is_confirmed) return 'Confirmed';
    return 'Pending';
}
</script>
