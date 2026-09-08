<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Commissions</h1>
                <p class="text-body-2 text-medium-emphasis">Platform commission across the last {{ days }} days.</p>
            </div>
            <v-select v-model="days" :items="dayOptions" label="Period" variant="outlined" density="compact" hide-details class="flex-grow-0" style="max-width: 160px" @update:model-value="load"></v-select>
        </div>
        <v-row class="mb-4">
            <v-col cols="12" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Commission</div><div class="text-h6 font-weight-bold text-primary">KES {{ Number(summary.commission || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
            <v-col cols="12" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Gross volume</div><div class="text-h6 font-weight-bold">KES {{ Number(summary.gross_volume || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
            <v-col cols="12" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Effective rate</div><div class="text-h6 font-weight-bold">{{ summary.effective_rate || 0 }}%</div></v-card-text></v-card></v-col>
            <v-col cols="12" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Transactions</div><div class="text-h6 font-weight-bold">{{ summary.count || 0 }}</div></v-card-text></v-card></v-col>
        </v-row>
        <v-row>
            <v-col cols="12" md="6">
                <v-card variant="outlined" class="mb-4">
                    <v-card-title class="text-subtitle-1 font-weight-bold">By facility</v-card-title>
                    <v-data-table :headers="breakdownHeaders" :items="byFacility" density="comfortable" hide-default-footer>
                        <template #item.facility="{ item }"><span class="font-weight-medium">{{ item.facility }}</span></template>
                        <template #item.commission="{ item }"><span class="text-body-2 text-primary">KES {{ Number(item.commission).toLocaleString() }}</span></template>
                        <template #item.volume="{ item }"><span class="text-body-2">KES {{ Number(item.volume).toLocaleString() }}</span></template>
                    </v-data-table>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card variant="outlined">
                    <v-card-title class="text-subtitle-1 font-weight-bold">By doctor</v-card-title>
                    <v-data-table :headers="doctorHeaders" :items="byDoctor" density="comfortable" hide-default-footer>
                        <template #item.doctor="{ item }"><span class="font-weight-medium">{{ item.doctor }}</span></template>
                        <template #item.commission="{ item }"><span class="text-body-2 text-primary">KES {{ Number(item.commission).toLocaleString() }}</span></template>
                        <template #item.count="{ item }"><span class="text-body-2">{{ item.count }}</span></template>
                    </v-data-table>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { adminService } from "../../services/adminService";

const loading = ref(false);
const error = ref(null);
const days = ref(90);
const dayOptions = [{ title: "30 days", value: 30 }, { title: "90 days", value: 90 }, { title: "180 days", value: 180 }, { title: "365 days", value: 365 }];
const summary = ref({});
const byFacility = ref([]);
const byDoctor = ref([]);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const data = await adminService.getCommissions({ days: days.value });
        summary.value = data.summary || {};
        byFacility.value = data.by_facility || [];
        byDoctor.value = data.by_doctor || [];
    } catch (e) {
        error.value = "Failed to load commissions.";
    } finally {
        loading.value = false;
    }
}

const breakdownHeaders = [
    { title: "Facility", key: "facility", sortable: false },
    { title: "Commission", key: "commission", sortable: true },
    { title: "Volume", key: "volume", sortable: true },
];
const doctorHeaders = [
    { title: "Doctor", key: "doctor", sortable: false },
    { title: "Commission", key: "commission", sortable: true },
    { title: "Count", key: "count", sortable: true },
];

onMounted(load);
</script>