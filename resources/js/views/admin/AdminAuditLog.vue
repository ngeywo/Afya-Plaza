<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading && !store.auditLogs.length" indeterminate></v-progress-linear>
        <div class="d-flex align-center mb-4">
            <h1 class="text-h5 font-weight-bold">Audit Log</h1>
            <v-spacer></v-spacer>
            <v-chip size="small" color="grey" variant="tonal">{{ total }} events</v-chip>
        </div>
        <!-- Filters -->
        <v-card variant="outlined" class="mb-4">
            <v-card-text class="d-flex gap-3 flex-wrap">
                <v-select v-model="filterResource" :items="resourceOptions" label="Resource" density="compact" hide-details clearable style="max-width:180px;"></v-select>
                <v-select v-model="filterAction" :items="actionOptions" label="Action" density="compact" hide-details clearable style="max-width:180px;"></v-select>
                <v-text-field v-model="filterActor" label="Actor ID" density="compact" hide-details clearable style="max-width:140px;" type="number"></v-text-field>
                <v-text-field v-model="filterFrom" label="From" type="date" density="compact" hide-details style="max-width:160px;"></v-text-field>
                <v-text-field v-model="filterTo" label="To" type="date" density="compact" hide-details style="max-width:160px;"></v-text-field>
                <v-btn color="primary" variant="tonal" size="small" @click="applyFilters" class="mt-1">Apply</v-btn>
                <v-btn size="small" @click="clearFilters" class="mt-1">Clear</v-btn>
            </v-card-text>
        </v-card>
        <!-- Table -->
        <v-card variant="outlined">
            <v-data-table
                :headers="headers"
                :items="store.auditLogs"
                :items-length="total"
                :server-items-length="total"
                :items-per-page="25"
                density="comfortable"
                @update:options="onPageChange"
            >
                <template #item.action="{ item }">
                    <v-chip size="x-small" :color="actionColor(item.action)" variant="tonal">{{ item.action }}</v-chip>
                </template>
                <template #item.resource_type="{ item }">
                    <span class="text-body-2">{{ item.resource_type }}</span>
                </template>
                <template #item.resource_label="{ item }">
                    <span class="text-body-2 font-weight-medium">{{ item.resource_label || `ID: ${item.resource_id}` }}</span>
                </template>
                <template #item.actor="{ item }">
                    <span class="text-body-2">{{ item.actor?.name || \'System\' }}</span>
                </template>
                <template #item.created_at="{ item }">
                    <span class="text-body-2 text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
                </template>
                <template #item.reason="{ item }">
                    <v-tooltip v-if="item.reason" location="top">
                        <template #activator="{ props }">
                            <span v-bind="props" class="text-body-2 text-truncate d-inline-block" style="max-width:150px;">{{ item.reason }}</span>
                        </template>
                        <span>{{ item.reason }}</span>
                    </v-tooltip>
                    <span v-else class="text-caption text-medium-emphasis">—</span>
                </template>
                <template #item.details="{ item }">
                    <v-btn size="x-small" variant="text" @click="showDetails(item)">Details</v-btn>
                </template>
            </v-data-table>
        </v-card>
        <!-- Detail Dialog -->
        <v-dialog v-model="detailDialog" max-width="560">
            <v-card v-if="selectedLog">
                <v-card-title class="text-subtitle-1 font-weight-bold">Audit Event</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <v-list density="compact">
                        <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Action</v-list-item-title><v-list-item-subtitle><v-chip size="x-small" :color="actionColor(selectedLog.action)" variant="tonal">{{ selectedLog.action }}</v-chip></v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Actor</v-list-item-title><v-list-item-subtitle>{{ selectedLog.actor?.name || \'System\' }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Resource</v-list-item-title><v-list-item-subtitle>{{ selectedLog.resource_type }} &mdash; ID {{ selectedLog.resource_id }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Timestamp</v-list-item-title><v-list-item-subtitle>{{ formatDate(selectedLog.created_at) }}</v-list-item-subtitle></v-list-item>
                        <v-list-item v-if="selectedLog.reason"><v-list-item-title class="text-caption text-medium-emphasis">Reason</v-list-item-title><v-list-item-subtitle>{{ selectedLog.reason }}</v-list-item-subtitle></v-list-item>
                        <v-list-item v-if="selectedLog.before && Object.keys(selectedLog.before).length">
                            <v-list-item-title class="text-caption text-medium-emphasis">Before</v-list-item-title>
                            <v-list-item-subtitle><pre class="text-caption" style="background:#f5f5f5;padding:8px;border-radius:4px;overflow:auto;">{{ JSON.stringify(selectedLog.before, null, 2) }}</pre></v-list-item-subtitle>
                        </v-list-item>
                        <v-list-item v-if="selectedLog.after && Object.keys(selectedLog.after).length">
                            <v-list-item-title class="text-caption text-medium-emphasis">After</v-list-item-title>
                            <v-list-item-subtitle><pre class="text-caption" style="background:#e8f5e9;padding:8px;border-radius:4px;overflow:auto;">{{ JSON.stringify(selectedLog.after, null, 2) }}</pre></v-list-item-subtitle>
                        </v-list-item>
                    </v-list>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="detailDialog = false">Close</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from "vue";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
const store = useAdminWorkspaceStore();
const filterResource = ref("");
const filterAction = ref("");
const filterActor = ref("");
const filterFrom = ref("");
const filterTo = ref("");
const currentPage = ref(1);
const detailDialog = ref(false);
const selectedLog = ref(null);
const resourceOptions = ["", "doctor", "facility", "doctor_facility", "clinic_session"];
const actionOptions = ["", "doctor.verified", "doctor.rejected", "doctor.suspended", "doctor.unsuspended", "doctor.unverified", "facility.verified", "facility.rejected", "facility.suspended", "facility.unsuspended", "facility.unverified"];
const headers = [
    { title: "Action", key: "action", sortable: false },
    { title: "Resource", key: "resource_type", sortable: true },
    { title: "Entity", key: "resource_label", sortable: false },
    { title: "Actor", key: "actor", sortable: false },
    { title: "When", key: "created_at", sortable: true },
    { title: "Reason", key: "reason", sortable: false },
    { title: "Details", key: "details", sortable: false, align: "end" },
];
const total = computed(() => store.auditMeta?.total ?? store.auditLogs.length);
function actionColor(a) {
    if (a?.includes(".verified") || a?.includes(".unsuspended")) return "success";
    if (a?.includes(".rejected") || a?.includes(".suspended") || a?.includes(".unverified")) return "error";
    return "grey";
}
function formatDate(d) {
    if (!d) return "—";
    return new Date(d).toLocaleString("en-KE", { dateStyle: "medium", timeStyle: "short" });
}
function applyFilters() {
    const params = {};
    if (filterResource.value) params.resource_type = filterResource.value;
    if (filterAction.value) params.action = filterAction.value;
    if (filterActor.value) params.actor_id = filterActor.value;
    if (filterFrom.value) params.from = filterFrom.value;
    if (filterTo.value) params.to = filterTo.value;
    params.page = currentPage.value;
    store.fetchAuditLogs(params);
}
function clearFilters() {
    filterResource.value = ""; filterAction.value = ""; filterActor.value = ""; filterFrom.value = ""; filterTo.value = "";
    store.fetchAuditLogs({});
}
function onPageChange(opts) {
    currentPage.value = opts.page;
    applyFilters();
}
function showDetails(log) {
    selectedLog.value = log;
    detailDialog.value = true;
}
onMounted(() => { if (!store.auditLogs.length) store.fetchAuditLogs({}); });
</script>
