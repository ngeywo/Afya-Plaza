<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>

        <div class="d-flex align-center mb-4">
            <h1 class="text-h5 font-weight-bold flex-grow-1">Clinic Sessions</h1>
            <v-btn-toggle v-model="filter" mandatory variant="outlined" density="comfortable" @update:model-value="reload">
                <v-btn value="today">Today</v-btn>
                <v-btn value="upcoming">Upcoming</v-btn>
                <v-btn value="past">Past</v-btn>
                <v-btn value="all">All</v-btn>
            </v-btn-toggle>
        </div>

        <v-progress-linear v-if="store.loading && store.sessions.length === 0" indeterminate></v-progress-linear>

        <v-card v-if="!store.loading && store.sessions.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-calendar-blank" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No clinic sessions</h3>
            <p class="text-body-2 text-medium-emphasis">No clinic sessions match this filter.</p>
        </v-card>

        <v-card v-for="s in store.sessions" :key="s.id" variant="outlined" class="mb-3">
            <div class="pa-4">
                <div class="d-flex align-center">
                    <div class="text-right mr-4" style="min-width:90px;">
                        <div class="text-caption text-medium-emphasis">{{ s.day }}</div>
                        <div class="text-body-2 font-weight-bold">{{ s.date }}</div>
                    </div>
                    <v-divider vertical class="mr-4"></v-divider>
                    <div class="flex-grow-1">
                        <div class="text-h6 font-weight-bold">{{ s.doctor?.name }}</div>
                        <div class="text-body-2 text-medium-emphasis">
                            {{ s.doctor?.specialties?.[0]?.name || '' }}
                            <span v-if="s.location"> · {{ s.location.name }}</span>
                        </div>
                        <div class="d-flex align-center mt-2">
                            <v-icon icon="mdi-clock-outline" size="16" class="mr-1" color="medium-emphasis"></v-icon>
                            <span class="text-body-2">{{ s.start_time }} – {{ s.end_time }}</span>
                            <v-chip class="ml-3" size="x-small" variant="outlined">{{ s.booked_appointments }} / {{ s.max_appointments || '∞' }}</v-chip>
                        </div>
                    </div>
                    <div class="text-right">
                        <v-chip :color="statusColor(s)" size="small" variant="tonal" class="mb-2 d-block">{{ statusLabel(s) }}</v-chip>
                        <v-btn v-if="s.facility_confirmation !== 'confirmed' && s.status !== 'cancelled'" color="success" size="small" variant="tonal" class="mr-1" @click="confirm(s)">Confirm</v-btn>
                        <v-btn v-if="s.facility_confirmation !== 'rejected' && s.status !== 'cancelled'" color="error" size="small" variant="outlined" @click="showReject(s)">Reject</v-btn>
                    </div>
                </div>
                <div v-if="s.cancellation_reason" class="text-caption text-error mt-2">Reason: {{ s.cancellation_reason }}</div>
            </div>
        </v-card>

        <v-dialog v-model="rejectDialog" max-width="500">
            <v-card>
                <v-card-title class="text-h6">Reject Clinic Session</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-3">Reject {{ rejectTarget?.doctor?.name }} on {{ rejectTarget?.day }} {{ rejectTarget?.start_time }} – {{ rejectTarget?.end_time }}?</p>
                    <v-textarea v-model="rejectReason" label="Reason" rows="3"></v-textarea>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="rejectDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="rejecting" @click="reject">Reject</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
const store = useFacilityWorkspaceStore();
const filter = ref("upcoming");
const rejectDialog = ref(false);
const rejectReason = ref("");
const rejectTarget = ref(null);
const rejecting = ref(false);

onMounted(() => { reload(); });

function reload() { store.fetchSessions({ filter: filter.value }); }

function statusColor(s) { return s.status === "cancelled" ? "error" : s.is_confirmed ? "success" : "warning"; }
function statusLabel(s) {
    if (s.status === "cancelled") return "Cancelled";
    if (s.facility_confirmation === "rejected") return "Rejected";
    if (s.is_confirmed) return "Confirmed";
    return "Pending";
}

async function confirm(s) {
    try { await store.confirmSession(s.id); reload(); } catch (e) {}
}
function showReject(s) { rejectTarget.value = s; rejectReason.value = ""; rejectDialog.value = true; }
async function reject() {
    if (!rejectReason.value.trim()) return;
    rejecting.value = true;
    try { await store.rejectSession(rejectTarget.value.id, rejectReason.value); rejectDialog.value = false; reload(); } catch (e) {}
    finally { rejecting.value = false; }
}
</script>
