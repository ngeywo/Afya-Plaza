<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>

        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Reports</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Performance and financial overview for this facility.</p>
            </div>
            <div class="d-flex align-center ga-2">
                <v-select v-model="days" :items="[7, 30, 90]" label="Period" hide-details density="compact" style="max-width: 120px;" @update:model-value="load"></v-select>
                <v-btn color="primary" size="small" prepend-icon="mdi-file-delimited" :loading="exporting" @click="doExport">Export CSV</v-btn>
            </div>
        </div>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <template v-if="report">
            <v-row class="mb-4">
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">Completed</div>
                        <div class="text-h5 font-weight-bold">{{ report.appointments.completed }}</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">Cancelled</div>
                        <div class="text-h5 font-weight-bold">{{ report.appointments.cancelled }}</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">No-shows</div>
                        <div class="text-h5 font-weight-bold">{{ report.appointments.no_shows }}</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="3">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">Completion</div>
                        <div class="text-h5 font-weight-bold">{{ report.appointments.completion_rate }}%</div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mb-4">
                <v-col cols="6" md="4">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">Gross Revenue</div>
                        <div class="text-h5 font-weight-bold">KSh {{ Number(report.revenue.gross).toLocaleString() }}</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="4">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">Platform Fee</div>
                        <div class="text-h5 font-weight-bold">KSh {{ Number(report.revenue.commission).toLocaleString() }}</div>
                    </v-card>
                </v-col>
                <v-col cols="6" md="4">
                    <v-card variant="outlined" class="pa-4">
                        <div class="text-caption text-medium-emphasis text-uppercase">Net Revenue</div>
                        <div class="text-h5 font-weight-bold">KSh {{ Number(report.revenue.net).toLocaleString() }}</div>
                    </v-card>
                </v-col>
            </v-row>

            <v-card variant="outlined" class="mb-4 pa-4">
                <v-card-title class="text-subtitle-1 font-weight-bold px-0 pt-0">Completed Bookings (last {{ report.range.days }} days)</v-card-title>
                <v-sparkline v-if="chartData.length" :model-value="chartData" height="80" padding="12" stroke-linecap="round"></v-sparkline>
                <p v-else class="text-body-2 text-medium-emphasis mb-0">No completed bookings in this period.</p>
            </v-card>

            <v-card variant="outlined">
                <v-card-title class="text-subtitle-1 font-weight-bold">Top Doctors</v-card-title>
                <v-divider></v-divider>
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(d, i) in report.by_doctor" :key="i">
                            <td class="font-weight-medium">{{ d.doctor || '—' }}</td>
                            <td>{{ d.bookings }}</td>
                            <td>KSh {{ Number(d.revenue).toLocaleString() }}</td>
                        </tr>
                        <tr v-if="report.by_doctor.length === 0">
                            <td colspan="3" class="text-medium-emphasis text-body-2">No data for this period.</td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const report = ref(null);
const loading = ref(false);
const exporting = ref(false);
const error = ref(null);
const days = ref(30);

const chartData = computed(() => (report.value?.daily || []).map((d) => d.count));

async function load() {
    loading.value = true;
    error.value = null;
    try {
        report.value = await facilityWorkspaceService.getReports({ days: days.value });
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load reports.';
    } finally {
        loading.value = false;
    }
}

async function doExport() {
    exporting.value = true;
    error.value = null;
    try {
        const blob = await facilityWorkspaceService.exportReport();
        const url = URL.createObjectURL(new Blob([blob]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `facility-payments-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (e) {
        error.value = 'Failed to export report.';
    } finally {
        exporting.value = false;
    }
}

onMounted(load);
</script>