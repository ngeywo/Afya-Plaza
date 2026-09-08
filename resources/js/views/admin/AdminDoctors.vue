<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Doctors</h1>
                <p class="text-body-2 text-medium-emphasis">Every doctor on the platform, with verification state.</p>
            </div>
            <div class="d-flex align-center ga-2">
                <v-text-field v-model="filters.search" label="Search" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details @keyup.enter="load" clearable></v-text-field>
                <v-select v-model="filters.verified" :items="verifiedOptions" label="Status" variant="outlined" density="compact" hide-details class="flex-grow-0" style="max-width: 200px"></v-select>
                <v-btn color="primary" variant="tonal" @click="load">Filter</v-btn>
            </div>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.display_name="{ item }">
                    <div class="d-flex align-center">
                        <div>
                            <div class="font-weight-medium">{{ item.display_name }}</div>
                            <a class="text-caption text-primary" href="#" @click.prevent="$router.push(`/admin/inspect/doctor/${item.id}`)">View workspace</a>
                        </div>
                    </div>
                </template>
                <template #item.specialties="{ item }"><span class="text-body-2">{{ (item.specialties || []).join(", ") || "—" }}</span></template>
                <template #item.verification_status="{ item }">
                    <v-chip size="x-small" :color="verificationColor(item.verification_status)" variant="flat">{{ item.verification_status || "—" }}</v-chip>
                </template>
                <template #item.consultation_fee="{ item }">
                    <span class="text-body-2">{{ item.consultation_fee ? "KES " + Number(item.consultation_fee).toLocaleString() : "—" }}</span>
                </template>
                <template #item.facility_count="{ item }"><span class="text-body-2">{{ item.facility_count }}</span></template>
                <template #item.created_at="{ item }"><span class="text-caption">{{ formatDate(item.created_at) }}</span></template>
                <template #item.actions="{ item }">
                    <v-btn size="small" variant="text" icon="mdi-eye-outline" title="Inspect" @click="$router.push(`/admin/inspect/doctor/${item.id}`)"></v-btn>
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
const filters = ref({ search: "", verified: "all" });
const verifiedOptions = [
    { title: "All", value: "all" },
    { title: "Verified", value: "verified" },
    { title: "Unverified", value: "unverified" },
    { title: "Pending", value: "pending" },
    { title: "Suspended", value: "suspended" },
    { title: "Rejected", value: "rejected" },
];

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getDoctors({
            search: filters.value.search || undefined,
            verified: filters.value.verified,
            per_page: 50,
        });
        rows.value = res.data;
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Failed to load doctors.";
    } finally {
        loading.value = false;
    }
}

function verificationColor(s) {
    return { verified: "success", pending: "warning", suspended: "error", rejected: "error", unverified: "grey" }[s] || "grey";
}

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString() : "—";
}

const headers = [
    { title: "Doctor", key: "display_name", sortable: true },
    { title: "Specialties", key: "specialties", sortable: false },
    { title: "Status", key: "verification_status", sortable: true },
    { title: "Fee (KES)", key: "consultation_fee", sortable: true },
    { title: "Facilities", key: "facility_count", sortable: true },
    { title: "Joined", key: "created_at", sortable: true },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>