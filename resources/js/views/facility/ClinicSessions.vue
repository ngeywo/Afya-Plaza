<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>

        <AppPageHeader
            title="Clinic Sessions"
            subtitle="Schedule and manage clinics for your approved doctors."
            icon="mdi-hospital-box"
        >
            <template #actions>
                <v-btn-toggle v-model="filter" mandatory variant="outlined" density="comfortable" @update:model-value="reload">
                    <v-btn value="today">Today</v-btn>
                    <v-btn value="upcoming">Upcoming</v-btn>
                    <v-btn value="past">Past</v-btn>
                    <v-btn value="all">All</v-btn>
                </v-btn-toggle>
                <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">New Clinic Session</v-btn>
            </template>
        </AppPageHeader>

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
                    <div class="d-flex flex-column align-end">
                        <AppStatusChip :status="sessionStatus(s)" :label="sessionLabel(s)" class="mb-2" />
                        <div v-if="s.facility_confirmation !== 'confirmed' && s.status !== 'cancelled'" class="d-flex ga-2">
                            <v-btn color="success" size="small" variant="tonal" @click="confirm(s)">Confirm</v-btn>
                            <v-btn color="error" size="small" variant="outlined" @click="showRejectDialog(s)">Reject</v-btn>
                        </div>
                    </div>
                </div>
                <div v-if="s.cancellation_reason" class="text-caption text-error mt-2">Reason: {{ s.cancellation_reason }}</div>
            </div>
        </v-card>

        <AppConfirmDialog
            v-model="rejectDialog"
            title="Reject Clinic Session"
            :message="rejectTarget ? `Reject ${rejectTarget.doctor?.name} on ${rejectTarget.day} ${rejectTarget.start_time} – ${rejectTarget.end_time}?` : ''"
            confirm-label="Reject Session"
            confirm-color="error"
            confirm-icon="mdi-cancel"
            :loading="rejecting"
            @confirm="confirmReject"
        >
            <template #content>
                <v-textarea v-model="rejectReason" label="Reason (required)" rows="3" variant="outlined" density="comfortable"
                    class="mt-3" :error="rejectReasonError" :error-messages="rejectReasonError ? 'Please provide a reason' : ''" />
            </template>
        </AppConfirmDialog>

        <v-dialog v-model="createDialog" max-width="640" persistent>
            <v-card>
                <v-card-title class="text-h6 font-weight-bold pt-5 px-6">New Clinic Session</v-card-title>
                <v-card-text class="pt-0">
                    <p class="text-body-2 mb-4" style="color: var(--ink-500);">The session will be pending until the doctor confirms it.</p>
                    <v-alert v-if="formError" type="error" variant="tonal" density="comfortable" class="mb-4" closable @click:close="formError = ''">{{ formError }}</v-alert>
                    <v-form>
                        <v-autocomplete v-if="showFacilityPicker" v-model="form.facility_id" :items="facilityOptions" item-title="name" item-value="id" label="Facility *"
                            variant="outlined" density="comfortable" :loading="store.loading"
                            :error="!!formErrors.facility_id" :error-messages="formErrors.facility_id" class="mb-2"
                            @update:model-value="onFacilityChanged" />
                        <v-autocomplete v-model="form.doctor_id" :items="doctorOptions" item-title="name" item-value="id" label="Doctor *"
                            variant="outlined" density="comfortable" :loading="store.loading"
                            :error="!!formErrors.doctor_id" :error-messages="formErrors.doctor_id" class="mb-2" />
                        <v-text-field v-model="form.session_date" type="date" label="Date *" :min="todayMin"
                            variant="outlined" density="comfortable"
                            :error="!!formErrors.session_date" :error-messages="formErrors.session_date" class="mb-2" />
                        <v-row dense>
                            <v-col cols="6">
                                <v-text-field v-model="form.start_time" type="time" label="Start *" variant="outlined" density="comfortable"
                                    :error="!!formErrors.start_time" :error-messages="formErrors.start_time" />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field v-model="form.end_time" type="time" label="End *" variant="outlined" density="comfortable"
                                    :error="!!formErrors.end_time" :error-messages="formErrors.end_time" />
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="6">
                                <v-text-field v-model.number="form.slot_duration_minutes" label="Slot duration (min)" type="number" min="10" max="120"
                                    variant="outlined" density="comfortable" hint="e.g. 30" class="mb-2" />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field v-model.number="form.max_appointments" label="Max patients" type="number" min="1"
                                    variant="outlined" density="comfortable" hint="Leave blank for unlimited" class="mb-2" />
                            </v-col>
                        </v-row>
                        <v-text-field v-model.number="form.consultation_fee" label="Consultation fee (KES)" type="number" min="0"
                            variant="outlined" density="comfortable" class="mb-2" />
                        <v-textarea v-model="form.notes" label="Notes" rows="2" variant="outlined" density="comfortable" class="mb-2" />
                    </v-form>
                </v-card-text>
                <v-card-actions class="pa-4 pt-0">
                    <v-spacer></v-spacer>
                    <v-btn variant="tonal" :disabled="creating" @click="createDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="creating" @click="submitCreate">Schedule session</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar" :color="snackColor" variant="tonal">{{ snackText }}</v-snackbar>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import AppStatusChip from "../../components/ui/AppStatusChip.vue";
import AppConfirmDialog from "../../components/ui/AppConfirmDialog.vue";
import { useNotifier } from "../../composables/useNotifier";

const store = useFacilityWorkspaceStore();
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();

const filter = ref("upcoming");
const rejectDialog = ref(false);
const rejectReason = ref("");
const rejectReasonError = ref(false);
const rejectTarget = ref(null);
const rejecting = ref(false);

const createDialog = ref(false);
const creating = ref(false);
const formError = ref('');
const todayMin = new Date().toISOString().slice(0, 10);

const form = reactive({
    facility_id: null,
    doctor_id: null,
    session_date: '',
    start_time: '',
    end_time: '',
    slot_duration_minutes: 30,
    max_appointments: null,
    consultation_fee: null,
    notes: '',
});
const formErrors = reactive({ facility_id: '', doctor_id: '', session_date: '', start_time: '', end_time: '' });

const doctorOptions = computed(() => store.doctors.map(d => ({
    id: d.id,
    name: d.name + ((d.specialties?.[0]?.name) ? ` — ${d.specialties[0].name}` : ''),
})));

const facilityOptions = computed(() => store.managedFacilities.map(f => ({
    id: f.id,
    name: f.name + (f.is_primary ? '  (primary)' : '') + (f.city ? ` — ${f.city}` : ''),
})));

const showFacilityPicker = computed(() => store.managedFacilities.length > 1);

async function loadDoctorsForFacility() {
    store.doctors = [];
    if (!form.facility_id) return;
    try { await store.fetchDoctors({ facility_id: form.facility_id }); } catch (e) { /* surfaced elsewhere */ }
}

function onFacilityChanged() {
    form.doctor_id = null;
    loadDoctorsForFacility();
}

onMounted(() => { reload(); });

function reload() { store.fetchSessions({ filter: filter.value }); }

function sessionStatus(s) {
    if (s.status === "cancelled") return "cancelled";
    if (s.facility_confirmation === "rejected") return "rejected";
    return s.is_confirmed ? "confirmed" : "pending";
}
function sessionLabel(s) {
    if (s.status === "cancelled") return "Cancelled";
    if (s.facility_confirmation === "rejected") return "Rejected";
    if (s.is_confirmed) return "Confirmed";
    return "Pending doctor confirmation";
}

async function confirm(s) {
    try {
        await store.confirmSession(s.id);
        notify('Clinic session confirmed.');
        reload();
    } catch (e) {
        notifyError(e.response?.data?.error || 'Failed to confirm session');
    }
}

function showRejectDialog(s) {
    rejectTarget.value = s;
    rejectReason.value = "";
    rejectReasonError.value = false;
    rejectDialog.value = true;
}

async function confirmReject() {
    if (!rejectReason.value.trim()) {
        rejectReasonError.value = true;
        return;
    }
    rejectReasonError.value = false;
    rejecting.value = true;
    try {
        await store.rejectSession(rejectTarget.value.id, rejectReason.value);
        rejectDialog.value = false;
        notify('Clinic session rejected.');
        reload();
    } catch (e) {
        notifyError(e.response?.data?.error || 'Failed to reject session');
    } finally {
        rejecting.value = false;
    }
}

function resetForm() {
    form.facility_id = store.dashboard?.facility?.id || null;
    form.doctor_id = null;
    form.session_date = '';
    form.start_time = '';
    form.end_time = '';
    form.slot_duration_minutes = 30;
    form.max_appointments = null;
    form.consultation_fee = null;
    form.notes = '';
    Object.keys(formErrors).forEach(k => formErrors[k] = '');
    formError.value = '';
}

async function openCreateDialog() {
    resetForm();
    if (store.managedFacilities.length === 0) {
        try { await store.fetchManagedFacilities(); } catch (e) { /* surfaced elsewhere */ }
    }
    if (!form.facility_id && store.managedFacilities.length > 0) {
        form.facility_id = store.managedFacilities.find(f => f.is_primary)?.id ?? store.managedFacilities[0].id;
    }
    if (!store.doctors.length || !form.facility_id) {
        await loadDoctorsForFacility();
    }
    createDialog.value = true;
}

function validateForm() {
    Object.keys(formErrors).forEach(k => formErrors[k] = '');
    let ok = true;
    if (!form.doctor_id) { formErrors.doctor_id = 'Select a doctor'; ok = false; }
    if (!form.session_date) { formErrors.session_date = 'Choose a date'; ok = false; }
    if (!form.start_time) { formErrors.start_time = 'Enter start time'; ok = false; }
    if (!form.end_time) { formErrors.end_time = 'Enter end time'; ok = false; }
    if (ok && form.end_time <= form.start_time) { formErrors.end_time = 'End must be after start'; ok = false; }
    return ok;
}

async function submitCreate() {
    formError.value = '';
    if (!validateForm()) return;
    if (!form.facility_id) {
        formError.value = 'Unable to determine your facility. Please refresh and try again.';
        return;
    }
    creating.value = true;
    try {
        await store.createSession({ ...form, facility_id: form.facility_id });
        createDialog.value = false;
        notify('Clinic session scheduled — awaiting doctor confirmation.');
        reload();
    } catch (e) {
        const data = e.response?.data;
        if (data?.collision) {
            formError.value = `${data.error} — ${data.collision.facility} (${data.collision.time})`;
        } else {
            formError.value = data?.error || data?.message || 'Failed to schedule session';
        }
    } finally {
        creating.value = false;
    }
}
</script>