<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading" indeterminate></v-progress-linear>
        <h1 class="text-h5 font-weight-bold mb-4">Verification Queue</h1>
        <v-tabs v-model="tab" color="primary" class="mb-4">
            <v-tab value="doctors">Pending Doctors<v-chip v-if="store.pendingDoctors.length" size="x-small" color="warning" class="ml-2">{{ store.pendingDoctors.length }}</v-chip></v-tab>
            <v-tab value="facilities">Pending Facilities<v-chip v-if="store.pendingFacilities.length" size="x-small" color="warning" class="ml-2">{{ store.pendingFacilities.length }}</v-chip></v-tab>
        </v-tabs>
        <v-dialog v-model="rejectDialog.show" max-width="500" persistent>
            <v-card>
                <v-card-title class="text-h6">Reject {{ rejectDialog.type === 'doctor' ? 'Doctor' : 'Facility' }}</v-card-title>
                <v-card-text>
                    <v-form ref="rejectForm">
                        <v-text-field v-model="rejectDialog.reason" label="Reason *" variant="outlined" density="compact" :rules="[v => !!v || 'Reason is required']" counter="255" maxlength="255"></v-text-field>
                        <v-textarea v-model="rejectDialog.notes" label="Additional notes" variant="outlined" density="compact" rows="2"></v-textarea>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="rejectDialog.show = false">Cancel</v-btn>
                    <v-btn color="error" :loading="rejectDialog.loading" @click="onRejectConfirm">Reject</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="suspendDialog.show" max-width="500" persistent>
            <v-card>
                <v-card-title class="text-h6">Suspend {{ suspendDialog.type === 'doctor' ? 'Doctor' : 'Facility' }}</v-card-title>
                <v-card-text>
                    <v-form ref="suspendForm">
                        <v-text-field v-model="suspendDialog.reason" label="Reason *" variant="outlined" density="compact" :rules="[v => !!v || 'Reason is required']" counter="255" maxlength="255"></v-text-field>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="suspendDialog.show = false">Cancel</v-btn>
                    <v-btn color="warning" :loading="suspendDialog.loading" @click="onSuspendConfirm">Suspend</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-tabs-window v-model="tab">
            <v-tabs-window-item value="doctors">
                <v-card v-if="!store.pendingDoctors.length && !store.loading" variant="outlined"><div class="pa-8 text-center"><v-icon icon="mdi-check-circle" size="64" color="success" class="mb-3"></v-icon><h3 class="text-h6 font-weight-bold mb-2">All Caught Up!</h3><p class="text-body-2 text-medium-emphasis">No pending doctor verifications.</p></div></v-card>
                <v-card v-else variant="outlined">
                    <v-data-table :headers="doctorHeaders" :items="store.pendingDoctors" :items-per-page="20" density="comfortable">
                        <template #item.name="{ item }">{{ item.display_name }}</template>
                        <template #item.specialties="{ item }"><span class="text-body-2">{{ item.specialties && item.specialties.map(s => s.name).join(", ") }}</span></template>
                        <template #item.qualifications="{ item }"><span class="text-caption">{{ item.qualifications }}</span></template>
                        <template #item.actions="{ item }">
                            <div class="d-flex gap-1 flex-wrap">
                                <v-btn size="small" color="success" variant="tonal" :loading="verifyingId === item.id" @click="onVerifyDoctor(item.id)">Verify</v-btn>
                                <v-btn size="small" color="warning" variant="outlined" :loading="verifyingId === item.id" @click="openSuspendDialog('doctor', item.id)">Suspend</v-btn>
                                <v-btn size="small" color="error" variant="outlined" :loading="verifyingId === item.id" @click="openRejectDialog('doctor', item.id)">Reject</v-btn>
                            </div>
                        </template>
                    </v-data-table>
                </v-card>
            </v-tabs-window-item>
            <v-tabs-window-item value="facilities">
                <v-card v-if="!store.pendingFacilities.length && !store.loading" variant="outlined"><div class="pa-8 text-center"><v-icon icon="mdi-check-circle" size="64" color="success" class="mb-3"></v-icon><h3 class="text-h6 font-weight-bold mb-2">All Caught Up!</h3><p class="text-body-2 text-medium-emphasis">No pending facility verifications.</p></div></v-card>
                <v-card v-else variant="outlined">
                    <v-data-table :headers="facilityHeaders" :items="store.pendingFacilities" :items-per-page="20" density="comfortable">
                        <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
                        <template #item.city="{ item }"><span class="text-body-2 text-medium-emphasis">{{ item.city }}</span></template>
                        <template #item.actions="{ item }">
                            <div class="d-flex gap-1 flex-wrap">
                                <v-btn size="small" color="success" variant="tonal" :loading="verifyingId === item.id" @click="onVerifyFacility(item.id)">Verify</v-btn>
                                <v-btn size="small" color="warning" variant="outlined" :loading="verifyingId === item.id" @click="openSuspendDialog('facility', item.id)">Suspend</v-btn>
                                <v-btn size="small" color="error" variant="outlined" :loading="verifyingId === item.id" @click="openRejectDialog('facility', item.id)">Reject</v-btn>
                            </div>
                        </template>
                    </v-data-table>
                </v-card>
            </v-tabs-window-item>
        </v-tabs-window>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
import { adminService } from "../../services/adminService";

const store = useAdminWorkspaceStore();
const route = useRoute();
const tab = ref(route.path.includes("/facilities") ? "facilities" : "doctors");
const verifyingId = ref(null);
const rejectForm = ref(null);
const suspendForm = ref(null);
const rejectDialog = ref({ show: false, type: null, id: null, reason: "", notes: "", loading: false });
const suspendDialog = ref({ show: false, type: null, id: null, reason: "", loading: false });

onMounted(() => {
    if (!store.pendingDoctors.length) store.fetchPendingDoctors();
    if (!store.pendingFacilities.length) store.fetchPendingFacilities();
});

function openRejectDialog(type, id) {
    rejectDialog.value = { show: true, type, id, reason: "", notes: "", loading: false };
}

function openSuspendDialog(type, id) {
    suspendDialog.value = { show: true, type, id, reason: "", loading: false };
}

async function onRejectConfirm() {
    const form = rejectForm.value;
    const { valid } = await form.validate();
    if (!valid) return;
    const { type, id, reason, notes } = rejectDialog.value;
    verifyingId.value = id;
    rejectDialog.value.loading = true;
    try {
        if (type === "doctor") {
            await adminService.rejectDoctor(id, { reason, notes });
            store.pendingDoctors = store.pendingDoctors.filter(d => d.id !== id);
        } else {
            await adminService.rejectFacility(id, { reason, notes });
            store.pendingFacilities = store.pendingFacilities.filter(f => f.id !== id);
        }
        rejectDialog.value.show = false;
    } finally {
        verifyingId.value = null;
        rejectDialog.value.loading = false;
    }
}

async function onSuspendConfirm() {
    const form = suspendForm.value;
    const { valid } = await form.validate();
    if (!valid) return;
    const { type, id, reason } = suspendDialog.value;
    verifyingId.value = id;
    suspendDialog.value.loading = true;
    try {
        if (type === "doctor") {
            await adminService.suspendDoctor(id, { reason });
            store.pendingDoctors = store.pendingDoctors.filter(d => d.id !== id);
        } else {
            await adminService.suspendFacility(id, { reason });
            store.pendingFacilities = store.pendingFacilities.filter(f => f.id !== id);
        }
        suspendDialog.value.show = false;
    } finally {
        verifyingId.value = null;
        suspendDialog.value.loading = false;
    }
}

const doctorHeaders = [
    { title: "Name", key: "name", sortable: true },
    { title: "Specialties", key: "specialties", sortable: false },
    { title: "Qualifications", key: "qualifications", sortable: false },
    { title: "Experience", key: "years_of_experience", sortable: true },
    { title: "Fee (KES)", key: "consultation_fee", sortable: true },
    { title: "Actions", key: "actions", sortable: false, align: "end" },
];
const facilityHeaders = [
    { title: "Name", key: "name", sortable: true },
    { title: "City", key: "city", sortable: true },
    { title: "Email", key: "email", sortable: true },
    { title: "Phone", key: "phone", sortable: false },
    { title: "Actions", key: "actions", sortable: false, align: "end" },
];

async function onVerifyDoctor(id) {
    verifyingId.value = id;
    try { await store.verifyDoctor(id); }
    finally { verifyingId.value = null; }
}
async function onVerifyFacility(id) {
    verifyingId.value = id;
    try { await store.verifyFacility(id); }
    finally { verifyingId.value = null; }
}
</script>