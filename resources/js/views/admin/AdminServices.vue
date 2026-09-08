<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Services</h1>
                <p class="text-body-2 text-medium-emphasis">Every doctor-facility offering across the marketplace.</p>
            </div>
            <div class="d-flex align-center ga-2">
                <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="load"></v-text-field>
                <v-btn color="primary" variant="tonal" @click="load">Filter</v-btn>
            </div>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.service_name="{ item }"><span class="font-weight-medium">{{ item.service_name }}</span></template>
                <template #item.price="{ item }"><span class="text-body-2">KES {{ Number(item.price).toLocaleString() }}</span></template>
                <template #item.doctor="{ item }"><span class="text-body-2">{{ item.doctor ? item.doctor.name : "—" }}</span></template>
                <template #item.facility="{ item }"><span class="text-body-2">{{ item.facility ? item.facility.name : "—" }}</span></template>
                <template #item.is_active="{ item }">
                    <v-chip size="x-small" :color="item.is_active ? 'success' : 'grey'" variant="flat">{{ item.is_active ? "Active" : "Inactive" }}</v-chip>
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
const search = ref("");

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getServices({ search: search.value || undefined, per_page: 50 });
        rows.value = res.data;
    } catch (e) {
        error.value = "Failed to load services.";
    } finally {
        loading.value = false;
    }
}

const headers = [
    { title: "Service", key: "service_name", sortable: true },
    { title: "Price", key: "price", sortable: true },
    { title: "Duration", key: "duration_minutes", sortable: true },
    { title: "Doctor", key: "doctor", sortable: false },
    { title: "Facility", key: "facility", sortable: false },
    { title: "Status", key: "is_active", sortable: true },
];

onMounted(load);
</script>