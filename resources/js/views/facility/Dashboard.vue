<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading && !store.dashboard" indeterminate></v-progress-linear>
        <div v-if="store.dashboard">
            <div class="d-flex align-center mb-6">
                <v-avatar size="56" color="secondary" class="mr-4"><v-icon icon="mdi-hospital-building" color="white" size="28"></v-icon></v-avatar>
                <div>
                    <h1 class="text-h5 font-weight-bold">{{ store.dashboard.facility.name }}</h1>
                    <div class="d-flex align-center mt-1">
                        <v-chip v-if="store.dashboard.facility.is_verified" color="success" size="small" variant="tonal" prepend-icon="mdi-check-decagram" class="mr-2">Verified</v-chip>
                        <span class="text-body-2 text-medium-emphasis">{{ store.dashboard.facility.city }}, {{ store.dashboard.facility.county }}</span>
                    </div>
                </div>
            </div>
            <v-row class="mb-6">
                <v-col cols="6" md="3"><v-card variant="outlined" class="pa-4 text-center"><div class="text-h4 font-weight-bold text-primary">{{ store.dashboard.today.clinics.length }}</div><div class="text-caption text-medium-emphasis mt-1">Today''s Clinics</div></v-card></v-col>
                <v-col cols="6" md="3"><v-card variant="outlined" class="pa-4 text-center"><div class="text-h4 font-weight-bold">{{ store.dashboard.today.appointment_count }}</div><div class="text-caption text-medium-emphasis mt-1">Today''s Appointments</div></v-card></v-col>
                <v-col cols="6" md="3"><v-card variant="outlined" class="pa-4 text-center"><div class="text-h4 font-weight-bold text-success">{{ store.dashboard.today.confirmed_clinics }}</div><div class="text-caption text-medium-emphasis mt-1">Confirmed</div></v-card></v-col>
                <v-col cols="6" md="3"><v-card variant="outlined" class="pa-4 text-center"><div class="text-h4 font-weight-bold" :class="store.dashboard.today.pending_confirmations > 0 ? 'text-warning' : 'text-medium-emphasis'">{{ store.dashboard.today.pending_confirmations }}</div><div class="text-caption text-medium-emphasis mt-1">Pending Confirm</div></v-card></v-col>
            </v-row>
            <v-card v-if="store.dashboard.today.clinics.length === 0" variant="outlined" class="pa-8 text-center mb-6">
                <v-icon icon="mdi-calendar-remove" size="64" color="medium-emphasis" class="mb-3"></v-icon>
                <h3 class="text-h6 font-weight-bold mb-2">No Clinics Today</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">There are currently no doctors scheduled to practice at this facility today.</p>
                <v-btn color="secondary" variant="tonal" to="/facility/clinic-sessions"><v-icon icon="mdi-calendar-clock" class="mr-1"></v-icon>View Upcoming Clinics</v-btn>
            </v-card>
            <div v-if="store.dashboard.today.clinics.length > 0" class="mb-6">
                <h2 class="text-subtitle-1 font-weight-bold text-medium-emphasis mb-3 text-uppercase" style="letter-spacing:0.5px;">Today''s Specialists — {{ store.dashboard.today.day }}</h2>
                <v-card v-for="session in store.dashboard.today.clinics" :key="session.id" variant="outlined" class="mb-3">
                    <div class="pa-5">
                        <div class="d-flex align-start justify-space-between mb-4">
                            <div class="d-flex align-center">
                                <v-avatar size="40" color="primary" class="mr-3"><span class="text-white text-caption font-weight-bold">{{ doctorInitials(session.doctor?.name) }}</span></v-avatar>
                                <div>
                                    <div class="text-h6 font-weight-bold">{{ session.doctor?.name }}</div>
                                    <div class="text-body-2 text-medium-emphasis">{{ session.doctor?.specialties?.[0]?.name || '' }}<span v-if="session.location"> · {{ session.location.name }}</span></div>
                                </div>
                            </div>
                            <v-chip :color="sessionStatusColor(session)" size="small" variant="tonal">{{ sessionStatusLabel(session) }}</v-chip>
                        </div>
                        <div class="d-flex align-center ml-13 mb-3">
                            <v-icon icon="mdi-clock-outline" size="20" class="mr-2" color="medium-emphasis"></v-icon>
                            <span class="text-h6 font-weight-bold">{{ session.start_time }} – {{ session.end_time }}</span>
                        </div>
                        <div class="d-flex align-center ml-13">
                            <v-chip size="small" variant="tonal" class="mr-3" prepend-icon="mdi-account-group">{{ session.booked_appointments }} booked</v-chip>
                            <v-chip size="small" variant="outlined" prepend-icon="mdi-slot-machine">{{ session.available_slots }} slots available</v-chip>
                        </div>
                    </div>
                </v-card>
            </div>
            <div v-if="store.dashboard.tomorrow.clinics.length > 0" class="mb-6">
                <h2 class="text-subtitle-1 font-weight-bold text-medium-emphasis mb-3 text-uppercase" style="letter-spacing:0.5px;">Tomorrow</h2>
                <v-card variant="outlined"><v-list density="compact"><v-list-item v-for="s in store.dashboard.tomorrow.clinics" :key="s.id" :to="'/facility/clinic-sessions'"><template #prepend><v-avatar size="32" color="primary"><span class="text-white text-caption">{{ doctorInitials(s.doctor?.name) }}</span></v-avatar></template><v-list-item-title class="font-weight-medium">{{ s.doctor?.name }}</v-list-item-title><v-list-item-subtitle>{{ s.day }} · {{ s.start_time }} – {{ s.end_time }}</v-list-item-subtitle><template #append><v-chip :color="sessionStatusColor(s)" size="x-small" variant="tonal">{{ sessionStatusLabel(s) }}</v-chip></template></v-list-item></v-list></v-card>
            </div>
            </div>
            <!-- Phase 11: Today at a Glance -->
            <v-card color="primary" variant="tonal" class="mb-6 pa-4" v-if="clinicDayMetrics">
                <div class="d-flex align-center mb-2">
                    <v-icon icon="mdi-clipboard-text-clock" class="mr-2" color="primary"></v-icon>
                    <span class="text-subtitle-1 font-weight-bold">Today at a Glance</span>
                    <v-spacer />
                    <v-btn size="small" variant="text" to="/facility/appointments">Full board &rarr;</v-btn>
                </div>
                <v-row dense>
                    <v-col cols="3"><div class="text-h6 font-weight-bold">{{ clinicDayMetrics.total }}</div><div class="text-caption">Total</div></v-col>
                    <v-col cols="3"><div class="text-h6 font-weight-bold text-teal">{{ clinicDayMetrics.checked_in }}</div><div class="text-caption">Checked in</div></v-col>
                    <v-col cols="3"><div class="text-h6 font-weight-bold text-amber">{{ clinicDayMetrics.in_progress }}</div><div class="text-caption">In progress</div></v-col>
                    <v-col cols="3"><div class="text-h6 font-weight-bold text-green">{{ clinicDayMetrics.completed }}</div><div class="text-caption">Completed</div></v-col>
                </v-row>
            </v-card>
            <div v-if="store.dashboard.pending_confirmations.length > 0">
                <h2 class="text-subtitle-1 font-weight-bold text-warning mb-3 text-uppercase" style="letter-spacing:0.5px;">Pending Confirmation</h2>
                <v-card variant="outlined"><v-list density="compact"><v-list-item v-for="s in store.dashboard.pending_confirmations" :key="s.id"><template #prepend><v-icon icon="mdi-clock-alert-outline" color="warning"></v-icon></template><v-list-item-title class="font-weight-medium">{{ s.doctor?.name }}</v-list-item-title><v-list-item-subtitle>{{ s.day }} · {{ s.start_time }} – {{ s.end_time }}</v-list-item-subtitle><template #append><v-btn color="success" size="small" variant="tonal" class="mr-2" @click="handleConfirm(s)">Confirm</v-btn><v-btn color="error" size="small" variant="outlined" @click="showReject(s)">Reject</v-btn></template></v-list-item></v-list></v-card>
            </div>
        <v-dialog v-model="rejectDialog" max-width="500"><v-card><v-card-title class="text-h6">Reject Clinic Session</v-card-title><v-card-text><v-textarea v-model="rejectReason" label="Reason for rejection" rows="3"></v-textarea></v-card-text><v-card-actions><v-spacer></v-spacer><v-btn @click="rejectDialog = false">Cancel</v-btn><v-btn color="error" :loading="rejecting" @click="handleReject">Reject</v-btn></v-card-actions></v-card></v-dialog>
        <v-snackbar v-model="snackbar" color="success" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
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
function sessionStatusColor(s) { return s.status === "cancelled" ? "error" : s.is_confirmed ? "success" : "warning"; }
function sessionStatusLabel(s) { return s.status === "cancelled" ? "Cancelled" : s.is_confirmed ? "Confirmed" : "Pending"; }
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
