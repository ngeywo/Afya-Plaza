<template>
    <div>
        <AppErrorState
            v-if="store.error && !store.dashboard"
            title="We couldn't load your dashboard"
            :message="store.error"
            class="mb-4"
            @retry="store.fetchDashboard()"
        />
        <AppLoadingState v-else-if="store.loading && !store.dashboard" variant="board" :rows="2" />

        <template v-else-if="store.dashboard">
            <AppPageHeader :title="store.dashboard.doctor.name" :subtitle="`Doctor Workspace${store.dashboard.today.day ? ' · ' + store.dashboard.today.day : ''}`" icon="mdi-stethoscope">
                <template #actions>
                    <v-chip v-if="store.dashboard.doctor.is_verified" color="success" variant="tonal" size="small" prepend-icon="mdi-check-decagram">Verified</v-chip>
                </template>
            </AppPageHeader>

            <!-- WHERE I AM WORKING TODAY — signature Afya Plaza moment -->
            <v-card color="primary" theme="dark" rounded="xl" class="mb-6 overflow-hidden">
                <v-card-text class="pa-6 pa-md-8">
                    <div class="d-flex align-center gap-2 mb-4">
                        <v-icon icon="mdi-map-marker-radius" size="20"></v-icon>
                        <h2 class="text-subtitle-1 font-weight-bold text-uppercase" style="letter-spacing: 0.12em;">Where I am working today</h2>
                    </div>

                    <template v-if="store.dashboard.today.sessions.length">
                        <div
                            v-for="(session, idx) in store.dashboard.today.sessions"
                            :key="session.id"
                            class="today-clinic-block"
                            :class="{ 'mt-4': idx > 0 }"
                        >
                            <div class="d-flex align-center justify-space-between gap-3 flex-wrap mb-3">
                                <div class="d-flex align-center gap-3">
                                    <div class="d-inline-flex align-center justify-center rounded-lg" style="width: 46px; height: 46px; background: rgba(255,255,255,0.14);">
                                        <v-icon icon="mdi-hospital-building" size="24" color="white"></v-icon>
                                    </div>
                                    <div>
                                        <div class="text-h6 font-weight-bold">{{ session.facility.name }}</div>
                                        <div class="text-body-2" style="opacity: 0.85;">{{ session.facility.address }}<span v-if="session.facility.city">, {{ session.facility.city }}</span></div>
                                    </div>
                                </div>
                                <AppStatusChip :status="sessionStatusKey(session)" variant="flat" label="" />
                            </div>

                            <div class="d-flex align-center gap-2 mb-4">
                                <v-icon icon="mdi-clock-outline" size="22" color="white"></v-icon>
                                <span class="text-h5 font-weight-bold">{{ session.start_time }} – {{ session.end_time }}</span>
                            </div>

                            <v-row dense class="mb-4">
                                <v-col cols="4">
                                    <div class="text-caption" style="opacity: 0.8;">Booked</div>
                                    <div class="text-h6 font-weight-bold">{{ session.booked_appointments }}</div>
                                </v-col>
                                <v-col cols="4">
                                    <div class="text-caption" style="opacity: 0.8;">Available</div>
                                    <div class="text-h6 font-weight-bold">{{ session.available_slots }}</div>
                                </v-col>
                                <v-col cols="4">
                                    <div class="text-caption" style="opacity: 0.8;">Capacity</div>
                                    <div class="text-h6 font-weight-bold">{{ session.max_appointments ?? '∞' }}</div>
                                </v-col>
                            </v-row>

                            <div class="d-flex gap-2 flex-wrap">
                                <v-btn color="white" class="text-primary" variant="flat" size="small" :to="`/doctor/clinics?session=${session.id}`">View Clinic</v-btn>
                                <v-btn color="white" variant="outlined" size="small" :to="`/doctor/appointments?session=${session.id}`">
                                    {{ session.booked_appointments }} Appointments
                                </v-btn>
                            </div>
                        </div>
                    </template>

                    <div v-else class="d-flex align-center gap-3">
                        <div class="d-inline-flex align-center justify-center rounded-lg" style="width: 56px; height: 56px; background: rgba(255,255,255,0.14);">
                            <v-icon icon="mdi-calendar-remove" size="28" color="white"></v-icon>
                        </div>
                        <div class="flex-grow-1">
                            <h3 class="text-h6 font-weight-bold mb-1">No clinic today</h3>
                            <p class="text-body-2 mb-0" style="opacity: 0.85;">You have no confirmed session scheduled for today.</p>
                        </div>
                        <v-btn color="white" class="text-primary" variant="flat" to="/doctor/schedule" prepend-icon="mdi-calendar">View Schedule</v-btn>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Today at a Glance (real clinic-day metrics) -->
            <v-row v-if="clinicDayMetrics" class="mb-2" dense>
                <v-col cols="6" md="3">
                    <AppStatCard label="Total Today" :value="clinicDayMetrics.total" icon="mdi-clipboard-text-outline" tone="neutral" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="Checked In" :value="clinicDayMetrics.checked_in" icon="mdi-account-check-outline" tone="success" color="success" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="In Progress" :value="clinicDayMetrics.in_progress" icon="mdi-stethoscope" tone="info" color="info" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="Completed" :value="clinicDayMetrics.completed" icon="mdi-check-circle-outline" tone="success" />
                </v-col>
            </v-row>

            <!-- Key metrics -->
            <h2 class="section-title mb-3">At a Glance</h2>
            <v-row class="mb-6" dense>
                <v-col cols="6" md="3">
                    <AppStatCard label="Today's Clinics" :value="store.dashboard.metrics.today_clinics" icon="mdi-hospital-building" tone="neutral" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="Today's Appointments" :value="store.dashboard.metrics.today_appointments" icon="mdi-calendar-check" tone="neutral" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="Upcoming (14 days)" :value="store.dashboard.metrics.upcoming_clinics" icon="mdi-calendar-month" tone="neutral" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard
                        label="Pending Confirm"
                        :value="store.dashboard.metrics.pending_confirmation"
                        icon="mdi-clock-alert-outline"
                        :tone="store.dashboard.metrics.pending_confirmation > 0 ? 'warning' : 'neutral'"
                        :color="store.dashboard.metrics.pending_confirmation > 0 ? 'warning' : 'neutral'"
                    />
                </v-col>
            </v-row>

            <!-- Upcoming clinics -->
            <div v-if="store.dashboard.upcoming && store.dashboard.upcoming.length">
                <v-row class="align-center mb-3">
                    <v-col>
                        <h2 class="section-title mb-0">Upcoming Clinics</h2>
                    </v-col>
                    <v-col cols="auto">
                        <v-btn variant="text" size="small" color="primary" to="/doctor/schedule" append-icon="mdi-arrow-right">Full schedule</v-btn>
                    </v-col>
                </v-row>
                <v-card variant="outlined">
                    <v-list density="compact">
                        <v-list-item v-for="s in store.dashboard.upcoming" :key="s.id" :to="`/doctor/clinics?session=${s.id}`">
                            <template #prepend>
                                <div class="text-right mr-3" style="min-width: 92px;">
                                    <div class="text-caption text-medium-emphasis">{{ s.day }}</div>
                                    <div class="text-body-2 font-weight-bold text-primary">{{ s.start_time }}</div>
                                </div>
                            </template>
                            <v-list-item-title class="font-weight-medium">{{ s.facility.name }}</v-list-item-title>
                            <v-list-item-subtitle>{{ s.facility.city }} · ends {{ s.end_time }}</v-list-item-subtitle>
                            <template #append>
                                <AppStatusChip :status="sessionStatusKey(s)" size="x-small" />
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useDoctorWorkspaceStore } from "../../stores/doctorWorkspaceStore";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import AppStatCard from "../../components/ui/AppStatCard.vue";
import AppStatusChip from "../../components/ui/AppStatusChip.vue";
import AppLoadingState from "../../components/ui/AppLoadingState.vue";
import AppErrorState from "../../components/ui/AppErrorState.vue";

const store = useDoctorWorkspaceStore();
const clinicDayMetrics = ref(null);

onMounted(async () => {
    await store.fetchDashboard();
    try {
        const data = await store.fetchClinicDay(new Date().toISOString().slice(0, 10));
        clinicDayMetrics.value = data?.metrics || null;
    } catch (e) {}
});

function sessionStatusKey(s) {
    if (s.status === 'cancelled') return 'cancelled';
    if (s.is_confirmed) return 'confirmed';
    return 'pending';
}
</script>