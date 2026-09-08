<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = null">{{ message }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Clinic Sessions</h1>
                <p class="text-body-2 text-medium-emphasis">Platform-wide clinic sessions. Overrides require a reason and notify everyone affected.</p>
            </div>
            <div class="d-flex align-center ga-2">
                <v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" density="compact" hide-details class="flex-grow-0" style="max-width: 200px" @update:model-value="load"></v-select>
                <v-btn color="primary" variant="tonal" @click="load">Refresh</v-btn>
            </div>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.doctor="{ item }"><span class="font-weight-medium">{{ item.doctor ? item.doctor.name : "—" }}</span></template>
                <template #item.facility="{ item }"><span class="text-body-2">{{ item.facility ? item.facility.name : "—" }}</span></template>
                <template #item.when="{ item }">
                    <div class="text-body-2">{{ item.session_date }}<div class="text-caption text-medium-emphasis">{{ item.start_time }}–{{ item.end_time }}</div></div>
                </template>
                <template #item.status="{ item }">
                    <v-chip size="x-small" :color="statusColor(item.status)" variant="flat">{{ item.status }}</v-chip>
                </template>
                <template #item.booked="{ item }"><span class="text-body-2">{{ item.booked_appointments }}/{{ item.max_appointments }}</span></template>
                <template #item.actions="{ item }">
                    <v-btn v-if="['pending', 'confirmed'].includes(item.status)" size="x-small" color="error" variant="outlined" @click="openCancel(item)">Cancel</v-btn>
                </template>
            </v-data-table>
        </v-card>
        <v-dialog v-model="cancelDialog.show" max-width="480" persistent>
            <v-card>
                <v-card-title class="text-h6">Cancel clinic session</v-card-title>
                <v-card-text>
                    <p class="text-body-2 text-medium-emphasis mb-3">This cancels the session and any live bookings. Patients and the doctor are notified; the action is audited.</p>
                    <v-form ref="cancelForm">
                        <v-textarea v-model="cancelDialog.reason" label="Reason *" variant="outlined" density="compact" rows="3" :rules="[v => !!v || 'A reason is required']" counter="255" maxlength="255"></v-textarea>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="cancelDialog.show = false">Back</v-btn>
                    <v-btn color="error" :loading="cancelDialog.loading" @click="onCancel">Cancel session</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { adminService } from "../../services/adminService";

const loading = ref(false);
const error = ref(null);
const message = ref(null);
const rows = ref([]);
const cancelForm = ref(null);
const filters = ref({ status: "all" });
const statusOptions = [
    { title: "All", value: "all" },
    { title: "Pending", value: "pending" },
    { title: "Confirmed", value: "confirmed" },
    { title: "Cancelled", value: "cancelled" },
    { title: "Completed", value: "completed" },
];
const cancelDialog = ref({ show: false, id: null, reason: "", loading: false });

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getAdminSessions({ status: filters.value.status === "all" ? undefined : filters.value.status, per_page: 50 });
        rows.value = res.data;
    } catch (e) {
        error.value = "Failed to load sessions.";
    } finally {
        loading.value = false;
    }
}

function openCancel(item) {
    cancelDialog.value = { show: true, id: item.id, reason: "", loading: false };
}

async function onCancel() {
    const { valid } = await cancelForm.value.validate();
    if (!valid) return;
    cancelDialog.value.loading = true;
    try {
        const res = await adminService.cancelAdminSession(cancelDialog.value.id, cancelDialog.value.reason);
        message.value = res.message || "Session cancelled.";
        cancelDialog.value.show = false;
        await load();
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Could not cancel the session.";
    } finally {
        cancelDialog.value.loading = false;
    }
}

function statusColor(s) {
    return { pending: "warning", confirmed: "success", cancelled: "error", completed: "info" }[s] || "grey";
}

const headers = [
    { title: "Doctor", key: "doctor", sortable: false },
    { title: "Facility", key: "facility", sortable: false },
    { title: "When", key: "when", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Booked", key: "booked", sortable: false },
    { title: "Fee", key: "consultation_fee", sortable: true },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>