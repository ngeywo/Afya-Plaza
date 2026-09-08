<template>
    <div>
        <AppErrorState
            v-if="store.error && !store.dashboard"
            title="We couldn't load the facility dashboard"
            :message="store.error"
            class="mb-4"
            @retry="store.fetchDashboard()"
        />
        <AppLoadingState v-else-if="store.loading && !store.dashboard" variant="board" :rows="2" />

        <template v-else-if="store.dashboard">
            <AppPageHeader :title="store.dashboard.facility.name" :subtitle="`Facility Workspace · ${store.dashboard.facility.city}${store.dashboard.facility.county ? ', ' + store.dashboard.facility.county : ''}`" icon="mdi-hospital-building">
                <template #actions>
                    <v-chip v-if="store.dashboard.facility.is_verified" color="success" variant="tonal" size="small" prepend-icon="mdi-check-decagram">Verified</v-chip>
                </template>
            </AppPageHeader>

            <!-- Key metrics -->
            <v-row class="mb-6" dense>
                <v-col cols="6" md="3">
                    <AppStatCard label="Today's Clinics" :value="store.dashboard.today.clinics.length" icon="mdi-hospital-building" tone="neutral" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="Today's Appointments" :value="store.dashboard.today.appointment_count" icon="mdi-calendar-check" tone="neutral" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard label="Confirmed" :value="store.dashboard.today.confirmed_clinics" icon="mdi-check-circle-outline" tone="success" color="success" />
                </v-col>
                <v-col cols="6" md="3">
                    <AppStatCard
                        label="Pending Confirm"
                        :value="store.dashboard.today.pending_confirmations"
                        icon="mdi-clock-alert-outline"
                        :tone="store.dashboard.today.pending_confirmations > 0 ? 'warning' : 'neutral'"
                        :color="store.dashboard.today.pending_confirmations > 0 ? 'warning' : 'neutral'"
                    />
                </v-col>
            </v-row>

            <!-- Today at a Glance (real clinic-day metrics) -->
            <v-row v-if="clinicDayMetrics" class="mb-6" dense>
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

            <!-- Today's specialists -->
            <div class="mb-6">
                <h2 class="section-title mb-3">Today's Specialists — {{ store.dashboard.today.day }}</h2>
                <EmptyState
                    v-if="store.dashboard.today.clinics.length === 0"
                    icon="mdi-calendar-remove-outline"
                    title="No clinicians scheduled today"
                    body="There are currently no doctors scheduled to practice at this facility today."
                    action-label="View Upcoming Clinics"
                    action-to="/facility/sessions"
                />
                <v-card v-for="session in store.dashboard.today.clinics" :key="session.id" variant="outlined" class="mb-3">
                    <div class="pa-5">
                        <div class="d-flex align-start justify-space-between gap-3 flex-wrap mb-4">
                            <div class="d-flex align-center">
                                <v-avatar size="42" color="primary" class="mr-3"><span class="text-white text-caption font-weight-bold">{{ doctorInitials(session.doctor?.name) }}</span></v-avatar>
                                <div>
                                    <div class="text-h6 font-weight-bold">{{ session.doctor?.name }}</div>
                                    <div class="text-body-2 text-medium-emphasis">{{ session.doctor?.specialties?.[0]?.name || '' }}<span v-if="session.location"> · {{ session.location.name }}</span></div>
                                </div>
                            </div>
                            <AppStatusChip :status="sessionStatusKey(session)" />
                        </div>
                        <div class="d-flex align-center mb-3" style="margin-left: 54px;">
                            <v-icon icon="mdi-clock-outline" size="20" class="mr-2" color="medium-emphasis"></v-icon>
                            <span class="text-h6 font-weight-bold">{{ session.start_time }} – {{ session.end_time }}</span>
                        </div>
                        <div class="d-flex align-center gap-2 flex-wrap" style="margin-left: 54px;">
                            <v-chip size="small" variant="tonal" class="mr-2" prepend-icon="mdi-account-group">{{ session.booked_appointments }} booked</v-chip>
                            <v-chip size="small" variant="outlined" prepend-icon="mdi-slot-machine">{{ session.available_slots }} slots available</v-chip>
                        </div>
                    </div>
                </v-card>
            </div>

            <!-- Tomorrow -->
            <div v-if="store.dashboard.tomorrow.clinics.length > 0" class="mb-6">
                <h2 class="section-title mb-3">Tomorrow</h2>
                <v-card variant="outlined">
                    <v-list density="compact">
                        <v-list-item v-for="s in store.dashboard.tomorrow.clinics" :key="s.id" :to="'/facility/sessions'">
                            <template #prepend>
                                <v-avatar size="32" color="primary"><span class="text-white text-caption">{{ doctorInitials(s.doctor?.name) }}</span></v-avatar>
                            </template>
                            <v-list-item-title class="font-weight-medium">{{ s.doctor?.name }}</v-list-item-title>
                            <v-list-item-subtitle>{{ s.day }} · {{ s.start_time }} – {{ s.end_time }}</v-list-item-subtitle>
                            <template #append>
                                <AppStatusChip :status="sessionStatusKey(s)" size="x-small" />
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </div>

            <!-- Pending confirmation -->
            <div v-if="store.dashboard.pending_confirmations.length > 0">
                <h2 class="section-title mb-3" style="color: var(--brand-warning);">Pending Confirmation</h2>
                <v-card variant="outlined">
                    <v-list density="compact">
                        <v-list-item v-for="s in store.dashboard.pending_confirmations" :key="s.id">
                            <template #prepend><v-icon icon="mdi-clock-alert-outline" color="warning"></v-icon></template>
                            <v-list-item-title class="font-weight-medium">{{ s.doctor?.name }}</v-list-item-title>
                            <v-list-item-subtitle>{{ s.day }} · {{ s.start_time }} – {{ s.end_time }}</v-list-item-subtitle>
                            <template #append>
                                <v-btn color="success" size="small" variant="tonal" class="mr-2" @click="handleConfirm(s)">Confirm</v-btn>
                                <v-btn color="error" size="small" variant="outlined" @click="showReject(s)">Reject</v-btn>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </div>
        </template>

        <AppConfirmDialog
            v-model="rejectDialog"
            title="Reject clinic session"
            :message="`Reject the session so the doctor can be notified. This action is permanent.`"
            confirm-label="Reject Session"
            :loading="rejecting"
            @confirm="handleReject"
        >
            <template #content>
                <v-textarea v-model="rejectReason" label="Reason for rejection" rows="3" hint="The reason is shared with the doctor." class="mt-4"></v-textarea>
            </template>
        </AppConfirmDialog>

        <v-snackbar v-model="snackbar" color="success" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import AppStatCard from "../../components/ui/AppStatCard.vue";
import AppStatusChip from "../../components/ui/AppStatusChip.vue";
import AppConfirmDialog from "../../components/ui/AppConfirmDialog.vue";
import AppLoadingState from "../../components/ui/AppLoadingState.vue";
import AppErrorState from "../../components/ui/AppErrorState.vue";
import EmptyState from "../../components/EmptyState.vue";

const store = useFacilityWorkspaceStore();
const rejectDialog = ref(false);
const rejectReason = ref("");
const rejectTarget = ref(null);
const rejecting = ref(false);
const snackbar = ref(false);
const snackText = ref("");
const clinicDayMetrics = ref(null);

onMounted(async () => {
    await store.fetchDashboard();
    try {
        const data = await store.fetchClinicDay(new Date().toISOString().slice(0, 10));
        clinicDayMetrics.value = data?.metrics || null;
    } catch (e) {}
});

function doctorInitials(name) { return name ? name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase() : "?"; }
function sessionStatusKey(s) { return s.status === "cancelled" ? "cancelled" : s.is_confirmed ? "confirmed" : "pending"; }
async function handleConfirm(session) {
    try { await store.confirmSession(session.id); snackText.value = "Clinic confirmed"; snackbar.value = true; await store.fetchDashboard(); } catch (e) {}
}
function showReject(session) { rejectTarget.value = session; rejectReason.value = ""; rejectDialog.value = true; }
async function handleReject() {
    if (!rejectReason.value.trim()) return;
    rejecting.value = true;
    try { await store.rejectSession(rejectTarget.value.id, rejectReason.value); snackText.value = "Clinic rejected"; snackbar.value = true; rejectDialog.value = false; await store.fetchDashboard(); } catch (e) {}
    finally { rejecting.value = false; }
}
</script>