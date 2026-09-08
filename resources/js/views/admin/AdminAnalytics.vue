<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Analytics</h1>
                <p class="text-body-2 text-medium-emphasis">Performance and revenue trends for the last {{ days }} days.</p>
            </div>
            <v-select v-model="days" :items="dayOptions" label="Period" variant="outlined" density="compact" hide-details class="flex-grow-0" style="max-width: 160px" @update:model-value="load"></v-select>
        </div>
        <v-row class="mb-4">
            <v-col cols="6" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Appointments</div><div class="text-h6 font-weight-bold">{{ appointments.total || 0 }}</div><div class="text-caption text-medium-emphasis">completion {{ appointments.completion_rate || 0 }}% · no-show {{ appointments.no_show_rate || 0 }}%</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Completed</div><div class="text-h6 font-weight-bold text-success">{{ appointments.completed || 0 }}</div><div class="text-caption text-medium-emphasis">cancelled {{ appointments.cancelled || 0 }}</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Revenue gross</div><div class="text-h6 font-weight-bold text-primary">KES {{ Number(revenue.gross || 0).toLocaleString() }}</div><div class="text-caption text-medium-emphasis">net KES {{ Number(revenue.net || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Commission</div><div class="text-h6 font-weight-bold">KES {{ Number(revenue.commission || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
        </v-row>
        <v-row class="mt-2">
            <v-col cols="12" md="6">
                <v-card variant="outlined" class="mb-4">
                    <v-card-title class="text-subtitle-1 font-weight-bold">Weekly appointments & revenue</v-card-title>
                    <v-card-text>
                        <v-list dense>
                            <v-list-item v-for="w in weekly" :key="w.week">
                                <v-list-item-title>{{ w.week }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">{{ w.appointments }} bookings · KES {{ Number(w.revenue || 0).toLocaleString() }}</v-list-item-subtitle>
                                <template #append><v-chip size="x-small" variant="tonal" color="primary">{{ w.appointments }}</v-chip></template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card variant="outlined" class="mb-4">
                    <v-card-title class="text-subtitle-1 font-weight-bold">Top doctors</v-card-title>
                    <v-data-table :headers="doctorHeaders" :items="topDoctors" density="comfortable" hide-default-footer>
                        <template #item.doctor="{ item }"><span class="font-weight-medium">{{ item.doctor }}</span></template>
                        <template #item.revenue="{ item }"><span class="text-body-2">KES {{ Number(item.revenue).toLocaleString() }}</span></template>
                    </v-data-table>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="12" md="6">
                <v-card variant="outlined">
                    <v-card-title class="text-subtitle-1 font-weight-bold">Top facilities</v-card-title>
                    <v-data-table :headers="facilityHeaders" :items="topFacilities" density="comfortable" hide-default-footer>
                        <template #item.facility="{ item }"><span class="font-weight-medium">{{ item.facility }}</span></template>
                        <template #item.revenue="{ item }"><span class="text-body-2">KES {{ Number(item.revenue).toLocaleString() }}</span></template>
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
const appointments = ref({});
const revenue = ref({});
const weekly = ref([]);
const topDoctors = ref([]);
const topFacilities = ref([]);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const data = await adminService.getAnalytics({ days: days.value });
        appointments.value = data.appointments || {};
        revenue.value = data.revenue || {};
        weekly.value = data.weekly_trend || [];
        topDoctors.value = data.top_doctors || [];
        topFacilities.value = data.top_facilities || [];
    } catch (e) {
        error.value = "Failed to load analytics.";
    } finally {
        loading.value = false;
    }
}

const doctorHeaders = [
    { title: "Doctor", key: "doctor", sortable: false },
    { title: "Bookings", key: "bookings", sortable: true },
    { title: "Revenue", key: "revenue", sortable: true },
];
const facilityHeaders = [
    { title: "Facility", key: "facility", sortable: false },
    { title: "Bookings", key: "bookings", sortable: true },
    { title: "Revenue", key: "revenue", sortable: true },
];

onMounted(load);
</script>