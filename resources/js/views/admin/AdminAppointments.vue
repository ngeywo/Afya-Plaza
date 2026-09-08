<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = null">{{ message }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Appointments</h1>
                <p class="text-body-2 text-medium-emphasis">Platform-wide patient bookings. Exceptional cancellations are audited and notified.</p>
            </div>
            <div class="d-flex align-center ga-2" style="max-width: 420px">
                <v-text-field v-model="filters.search" label="Search patient or ref" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="load"></v-text-field>
                <v-btn color="primary" variant="tonal" @click="load">Search</v-btn>
            </div>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.patient="{ item }">
                    <div class="text-body-2 font-weight-medium">{{ item.patient ? item.patient.name : "—" }}</div>
                    <div v-if="item.patient && item.patient.phone" class="text-caption text-medium-emphasis">{{ item.patient.phone }}</div>
                </template>
                <template #item.doctor="{ item }"><span class="text-body-2">{{ item.doctor ? item.doctor.name : "—" }}</span></template>
                <template #item.when="{ item }"><div class="text-body-2">{{ item.appointment_date }}<div class="text-caption text-medium-emphasis">{{ item.start_time }}–{{ item.end_time }}</div></div></template>
                <template #item.status="{ item }">
                    <v-chip size="x-small" :color="statusColor(item.status)" variant="flat">{{ item.status }}</v-chip>
                </template>
                <template #item.amount_paid="{ item }"><span class="text-body-2">KES {{ Number(item.amount_paid || 0).toLocaleString() }}</span></template>
                <template #item.actions="{ item }">
                    <v-btn v-if="['pending', 'confirmed'].includes(item.status)" size="x-small" color="error" variant="outlined" @click="openCancel(item)">Cancel</v-btn>
                </template>
            </v-data-table>
        </v-card>
        <v-dialog v-model="cancelDialog.show" max-width="480" persistent>
            <v-card>
                <v-card-title class="text-h6">Cancel appointment {{ cancelDialog.number }}</v-card-title>
                <v-card-text>
                    <v-form ref="cancelForm">
                        <v-textarea v-model="cancelDialog.reason" label="Reason *" variant="outlined" density="compact" rows="3" :rules="[v => !!v || 'A reason is required']" counter="255" maxlength="255"></v-textarea>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="cancelDialog.show = false">Back</v-btn>
                    <v-btn color="error" :loading="cancelDialog.loading" @click="onCancel">Cancel appointment</v-btn>
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
const filters = ref({ search: "" });
const cancelDialog = ref({ show: false, id: null, number: "", reason: "", loading: false });

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getAdminAppointments({ search: filters.value.search || undefined, per_page: 50 });
        rows.value = res.data;
    } catch (e) {
        error.value = "Failed to load appointments.";
    } finally {
        loading.value = false;
    }
}

function openCancel(item) {
    cancelDialog.value = { show: true, id: item.id, number: item.appointment_number, reason: "", loading: false };
}

async function onCancel() {
    const { valid } = await cancelForm.value.validate();
    if (!valid) return;
    cancelDialog.value.loading = true;
    try {
        await adminService.cancelAdminAppointment(cancelDialog.value.id, cancelDialog.value.reason);
        message.value = "Appointment cancelled.";
        cancelDialog.value.show = false;
        await load();
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Could not cancel the appointment.";
    } finally {
        cancelDialog.value.loading = false;
    }
}

function statusColor(s) {
    return { pending: "warning", confirmed: "success", completed: "info", cancelled: "error", no_show: "grey" }[s] || "grey";
}

const headers = [
    { title: "Patient", key: "patient", sortable: false },
    { title: "Doctor", key: "doctor", sortable: false },
    { title: "Facility", key: "facility", sortable: false },
    { title: "When", key: "when", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Amount", key: "amount_paid", sortable: true },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>