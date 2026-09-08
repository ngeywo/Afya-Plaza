<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Doctor Relationships</h1>
                <p class="text-body-2 text-medium-emphasis">Governance of every doctor-facility contract.</p>
            </div>
            <div class="d-flex align-center ga-2" style="max-width: 320px">
                <v-select v-model="status" :items="statusOptions" label="Status" variant="outlined" density="compact" hide-details @update:model-value="load"></v-select>
            </div>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.doctor="{ item }"><span class="font-weight-medium">{{ item.doctor ? item.doctor.name : "—" }}</span></template>
                <template #item.facility="{ item }"><span class="text-body-2">{{ item.facility ? item.facility.name : "—" }}</span></template>
                <template #item.status="{ item }">
                    <v-chip size="x-small" :color="item.status_color || 'grey'" variant="flat">{{ item.status_label || item.status }}</v-chip>
                </template>
                <template #item.consultation_fee="{ item }"><span class="text-body-2">{{ item.consultation_fee ? "KES " + Number(item.consultation_fee).toLocaleString() : "—" }}</span></template>
                <template #item.actions="{ item }">
                    <div class="d-flex gap-1">
                        <v-btn v-if="['pending', 'invited', 'suspended', 'inactive'].includes(item.status)" size="x-small" color="success" variant="tonal" @click="act(item, 'approve')">Approve</v-btn>
                        <v-btn v-if="['active', 'inactive'].includes(item.status)" size="x-small" color="error" variant="outlined" @click="openReason(item, 'suspend')">Suspend</v-btn>
                        <v-btn v-if="['active', 'suspended'].includes(item.status)" size="x-small" color="warning" variant="outlined" @click="openReason(item, 'end')">End</v-btn>
                    </div>
                </template>
            </v-data-table>
        </v-card>
        <v-dialog v-model="reasonDialog.show" max-width="480" persistent>
            <v-card>
                <v-card-title class="text-h6">{{ reasonDialog.actionTitle }}</v-card-title>
                <v-card-text>
                    <v-form ref="reasonForm">
                        <v-text-field v-model="reasonDialog.reason" label="Reason *" variant="outlined" density="compact" :rules="[v => !!v || 'A reason is required for this action']" counter="255" maxlength="255"></v-text-field>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="reasonDialog.show = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="reasonDialog.loading" @click="confirmAction">Confirm</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { adminService } from "../../services/adminService";

const route = useRoute();
const loading = ref(false);
const error = ref(null);
const rows = ref([]);
const status = ref(route.query.status || "all");
const reasonForm = ref(null);
const statusOptions = [
    { title: "All statuses", value: "all" },
    { title: "Active", value: "active" },
    { title: "Pending", value: "pending" },
    { title: "Invited", value: "invited" },
    { title: "Suspended", value: "suspended" },
    { title: "Inactive", value: "inactive" },
    { title: "Declined", value: "declined" },
];

const reasonDialog = ref({ show: false, item: null, action: null, actionTitle: "", reason: "", loading: false });

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getRelationships({ status: status.value === "all" ? undefined : status.value, per_page: 50 });
        rows.value = res.data;
    } catch (e) {
        error.value = "Failed to load relationships.";
    } finally {
        loading.value = false;
    }
}

function openReason(item, action) {
    reasonDialog.value = {
        show: true,
        item,
        action,
        actionTitle: action === "suspend" ? "Suspend relationship" : "End relationship",
        reason: "",
        loading: false,
    };
}

function act(item, action) {
    reasonDialog.value = {
        show: true,
        item,
        action,
        actionTitle: action === "approve" ? "Approve relationship" : "Action",
        reason: "",
        loading: false,
    };
}

async function confirmAction() {
    const { item, action, reason } = reasonDialog.value;
    if (["suspend", "end"].includes(action)) {
        const { valid } = await reasonForm.value.validate();
        if (!valid) return;
    }
    reasonDialog.value.loading = true;
    try {
        await adminService.relationshipAction(item.id, action, { reason: reason || undefined });
        reasonDialog.value.show = false;
        await load();
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Action failed.";
    } finally {
        reasonDialog.value.loading = false;
    }
}

const headers = [
    { title: "Doctor", key: "doctor", sortable: false },
    { title: "Facility", key: "facility", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Fee (KES)", key: "consultation_fee", sortable: true },
    { title: "Bookings", key: "accepts_appointments", sortable: false },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>