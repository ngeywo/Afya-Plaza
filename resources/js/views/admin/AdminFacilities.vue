<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Facilities</h1>
                <p class="text-body-2 text-medium-emphasis">All facilities with operational and verification state.</p>
            </div>
            <div class="d-flex align-center ga-2">
                <v-text-field v-model="filters.search" label="Search" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="load"></v-text-field>
                <v-btn color="primary" variant="tonal" @click="load">Filter</v-btn>
            </div>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.name="{ item }"><div class="font-weight-medium">{{ item.name }}</div></template>
                <template #item.type="{ item }"><span class="text-body-2">{{ item.type }} · {{ item.city }}</span></template>
                <template #item.verification_status="{ item }">
                    <v-chip size="x-small" color="success" variant="flat" v-if="item.verification_status === 'verified'">Verified</v-chip>
                    <v-chip size="x-small" color="warning" variant="flat" v-else-if="item.verification_status === 'pending'">Pending</v-chip>
                    <v-chip size="x-small" color="error" variant="flat" v-else>{{ item.verification_status || "—" }}</v-chip>
                </template>
                <template #item.counts="{ item }"><span class="text-body-2">{{ item.admin_count }} admins · {{ item.doctor_count }} doctors</span></template>
                <template #item.actions="{ item }">
                    <v-btn size="small" variant="text" icon="mdi-eye-outline" title="Inspect" @click="$router.push(`/admin/inspect/facility/${item.id}`)"></v-btn>
                </template>
            </v-data-table>
        </v-card>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { adminService } from "../../services/adminService";

const loading = ref(false);
const error = ref(null);
const rows = ref([]);
const filters = ref({ search: "" });

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getFacilities({ search: filters.value.search || undefined, per_page: 50 });
        rows.value = res.data;
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Failed to load facilities.";
    } finally {
        loading.value = false;
    }
}

const headers = [
    { title: "Facility", key: "name", sortable: true },
    { title: "Type · City", key: "type", sortable: false },
    { title: "Status", key: "verification_status", sortable: true },
    { title: "Admins · Doctors", key: "counts", sortable: false },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>