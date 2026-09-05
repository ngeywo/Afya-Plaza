<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">
            {{ store.error }}
        </v-alert>
        <v-progress-linear v-if="store.loading && !store.dashboard" indeterminate></v-progress-linear>

        <template v-if="store.dashboard">
            <!-- Doctor header -->
            <div class="d-flex align-center mb-6">
                <v-avatar size="56" color="primary" class="mr-4">
                    <span class="text-white text-h6 font-weight-bold">{{ doctorInitials }}</span>
                </v-avatar>
                <div>
                    <h1 class="text-h5 font-weight-bold">{{ store.dashboard.doctor.name }}</h1>
                    <div class="d-flex align-center mt-1">
                        <v-chip v-if="store.dashboard.doctor.is_verified" color="success" size="small" variant="tonal" prepend-icon="mdi-check-decagram" class="mr-2">Verified</v-chip>
                        <span class="text-body-2 text-medium-emphasis">Doctor Workspace</span>
                    </div>
                </div>
            </div>

            <!-- Today's clinics — Section 4, 42 -->
            <div class="mb-6">
                <h2 class="text-subtitle-1 font-weight-bold text-medium-emphasis mb-3 text-uppercase" style="letter-spacing: 0.5px;">Today — {{ store.dashboard.today.day }}</h2>

                <!-- No clinic today -->
                <v-card v-if="store.dashboard.today.sessions.length === 0" variant="outlined" class="pa-8 text-center">
                    <v-icon icon="mdi-calendar-remove" size="64" color="medium-emphasis" class="mb-3"></v-icon>
                    <h3 class="text-h6 font-weight-bold mb-2">No Clinic Today</h3>
                    <p class="text-body-2 text-medium-emphasis mb-4">You currently have no confirmed clinic session scheduled today.</p>
                    <v-btn color="primary" variant="tonal" :to="'/doctor/schedule'">
                        <v-icon icon="mdi-calendar" class="mr-1"></v-icon>View Schedule
                    </v-btn>
                </v-card>

                <!-- Today's session(s) -->
                <v-card v-else variant="outlined" class="mb-3" v-for="session in store.dashboard.today.sessions" :key="session.id">
                    <div class="pa-5">
                        <div class="d-flex align-start justify-space-between mb-4">
                            <div>
                                <div class="d-flex align-center mb-1">
                                    <v-icon icon="mdi-hospital-building" color="primary" class="mr-2"></v-icon>
                                    <span class="text-h6 font-weight-bold">{{ session.facility.name }}</span>
                                </div>
                                <div class="text-body-2 text-medium-emphasis ml-8">
                                    {{ session.facility.address }}, {{ session.facility.city }}
                                </div>
                            </div>
                            <v-chip :color="sessionStatusColor(session)" size="small" variant="tonal">
                                {{ sessionStatusLabel(session) }}
                            </v-chip>
                        </div>
                        <div class="d-flex align-center mb-4 ml-2">
                            <v-icon icon="mdi-clock-outline" size="20" class="mr-2" color="medium-emphasis"></v-icon>
                            <span class="text-h5 font-weight-bold">{{ session.start_time }} – {{ session.end_time }}</span>
                        </div>
                        <v-row dense class="mb-4">
                            <v-col cols="4">
                                <div class="text-caption text-medium-emphasis">Booked</div>
                                <div class="text-h6 font-weight-bold">{{ session.booked_appointments }}</div>
                            </v-col>
                            <v-col cols="4">
                                <div class="text-caption text-medium-emphasis">Available</div>
                                <div class="text-h6 font-weight-bold text-primary">{{ session.available_slots }}</div>
                            </v-col>
                            <v-col cols="4">
                                <div class="text-caption text-medium-emphasis">Capacity</div>
                                <div class="text-h6 font-weight-bold">{{ session.max_appointments ?? '∞' }}</div>
                            </v-col>
                        </v-row>
                        <div class="d-flex gap-4 mb-4">
                            <span class="text-caption" :class="session.doctor_confirmation === 'confirmed' ? 'text-success' : 'text-warning'">
                                <v-icon icon="mdi-doctor" size="14" class="mr-1"></v-icon>Doctor: {{ session.doctor_confirmation }}
                            </span>
                            <span class="text-caption" :class="session.facility_confirmation === 'confirmed' ? 'text-success' : 'text-warning'">
                                <v-icon icon="mdi-hospital-building" size="14" class="mr-1"></v-icon>Facility: {{ session.facility_confirmation }}
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <v-btn variant="tonal" color="primary" size="small" :to="`/doctor/clinics?session=${session.id}`">View Clinic</v-btn>
                            <v-btn variant="outlined" size="small" :to="`/doctor/appointments?session=${session.id}`">
                                {{ session.booked_appointments }} Appointments
                            </v-btn>
                        </div>
                    </div>
                    <v-divider v-if="store.dashboard.today.appointments.length > 0"></v-divider>
                    <div v-if="store.dashboard.today.appointments.length > 0" class="pa-5 pt-0">
                        <h3 class="text-subtitle-2 font-weight-bold mb-3">Today's Appointments</h3>
                        <div v-for="apt in store.dashboard.today.appointments" :key="apt.id" class="d-flex align-center py-2 border-b">
                            <span class="text-body-2 font-weight-medium mr-3" style="min-width: 52px;">{{ apt.start_time }}</span>
                            <v-avatar size="28" color="primary" class="mr-2">
                                <span class="text-white text-caption">{{ apt.patient?.name?.[0] ?? '?' }}</span>
                            </v-avatar>
                            <span class="text-body-2 font-weight-medium flex-grow-1">{{ apt.patient?.name }}</span>
                            <v-chip :color="aptStatusColor(apt.status)" size="x-small" variant="tonal" class="mr-2">{{ apt.status }}</v-chip>
                        </div>
                    </div>
                </v-card>
            </div>
            <!-- Phase 11: Today at a Glance -->
            <v-card color="primary" variant="tonal" class="mb-6 pa-4" v-if="clinicDayMetrics">
                <div class="d-flex align-center mb-2">
                    <v-icon icon="mdi-clipboard-text-clock" class="mr-2" color="primary"></v-icon>
                    <span class="text-subtitle-1 font-weight-bold">Today at a Glance</span>
                    <v-spacer />
                    <v-btn size="small" variant="text" to="/doctor/appointments">Full board &rarr;</v-btn>
                </div>
                <v-row dense>
                    <v-col cols="3"><div class="text-h6 font-weight-bold">{{ clinicDayMetrics.total }}</div><div class="text-caption">Total</div></v-col>
                    <v-col cols="3"><div class="text-h6 font-weight-bold text-teal">{{ clinicDayMetrics.checked_in }}</div><div class="text-caption">Checked in</div></v-col>
                    <v-col cols="3"><div class="text-h6 font-weight-bold text-amber">{{ clinicDayMetrics.in_progress }}</div><div class="text-caption">In progress</div></v-col>
                    <v-col cols="3"><div class="text-h6 font-weight-bold text-green">{{ clinicDayMetrics.completed }}</div><div class="text-caption">Completed</div></v-col>
                </v-row>
            </v-card>

            <!-- Metrics — Section 5 -->
            <!-- Metrics — Section 5 -->
            <v-row class="mb-6">
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4 text-center">
                        <div class="text-h4 font-weight-bold text-primary">{{ store.dashboard.metrics.today_clinics }}</div>
                        <div class="text-caption text-medium-emphasis mt-1">Today's Clinics</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4 text-center">
                        <div class="text-h4 font-weight-bold text-primary">{{ store.dashboard.metrics.today_appointments }}</div>
                        <div class="text-caption text-medium-emphasis mt-1">Today's Appointments</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4 text-center">
                        <div class="text-h4 font-weight-bold">{{ store.dashboard.metrics.upcoming_clinics }}</div>
                        <div class="text-caption text-medium-emphasis mt-1">Upcoming (14 days)</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4 text-center">
                        <div class="text-h4 font-weight-bold" :class="store.dashboard.metrics.pending_confirmation > 0 ? 'text-warning' : 'text-medium-emphasis'">{{ store.dashboard.metrics.pending_confirmation }}</div>
                        <div class="text-caption text-medium-emphasis mt-1">Pending Confirm</div>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Upcoming clinics -->
            <div v-if="store.dashboard.upcoming && store.dashboard.upcoming.length > 0">
                <h2 class="text-subtitle-1 font-weight-bold text-medium-emphasis mb-3 text-uppercase" style="letter-spacing: 0.5px;">Upcoming Clinics</h2>
                <v-card variant="outlined">
                    <v-list density="compact">
                        <v-list-item v-for="s in store.dashboard.upcoming" :key="s.id" :to="`/doctor/clinics?session=${s.id}`">
                            <template #prepend>
                                <div class="text-right mr-3" style="min-width: 80px;">
                                    <div class="text-caption text-medium-emphasis">{{ s.day }}</div>
                                    <div class="text-body-2 font-weight-bold">{{ s.start_time }}</div>
                                </div>
                            </template>
                            <v-list-item-title class="font-weight-medium">{{ s.facility.name }}</v-list-item-title>
const store = useDoctorWorkspaceStore();
const clinicDayMetrics = ref(null);

const doctorInitials = computed(() => {
                            <v-list-item-subtitle>{{ s.facility.city }} · {{ s.end_time }}</v-list-item-subtitle>
                            <template #append>
                                <v-chip :color="sessionStatusColor(s)" size="x-small" variant="tonal">{{ sessionStatusLabel(s) }}</v-chip>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useDoctorWorkspaceStore } from "../../stores/doctorWorkspaceStore";

const store = useDoctorWorkspaceStore();

const doctorInitials = computed(() => {
    const name = store.dashboard?.doctor?.name || '';
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
});

onMounted(async () => {
    await store.fetchDashboard();
    try {
        const data = await store.fetchClinicDay(new Date().toISOString().slice(0, 10));
        clinicDayMetrics.value = data?.metrics || null;
    } catch (e) {}
});

function sessionStatusColor(s) {
    if (s.status === 'cancelled') return 'error';
    if (s.is_confirmed) return 'success';
    return 'warning';
}

function sessionStatusLabel(s) {
    if (s.status === 'cancelled') return 'Cancelled';
    if (s.is_confirmed) return 'Confirmed';
    return 'Pending';
}

function aptStatusColor(status) {
    return {
        pending: 'warning',
        confirmed: 'success',
        checked_in: 'teal',
        in_progress: 'amber',
        completed: 'info',
        cancelled: 'error',
        no_show: 'deep-orange',
    }[status] || 'default';
}
</script>