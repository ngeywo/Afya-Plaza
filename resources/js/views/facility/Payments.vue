<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>

        <div class="mb-4">
            <h1 class="text-h5 font-weight-bold mb-0">Payments</h1>
            <p class="text-body-2 text-medium-emphasis mb-0">Money received by this facility from patient bookings.</p>
        </div>

        <v-row class="mb-4">
            <v-col cols="6" md="3">
                <v-card variant="outlined" class="pa-4">
                    <div class="text-caption text-uppercase text-medium-emphasis">Paid</div>
                    <div class="text-h5 font-weight-bold">{{ totals.paid }}</div>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card variant="outlined" class="pa-4">
                    <div class="text-caption text-uppercase text-medium-emphasis">Pending</div>
                    <div class="text-h5 font-weight-bold">{{ totals.pending }}</div>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card variant="outlined" class="pa-4">
                    <div class="text-caption text-uppercase text-medium-emphasis">Failed</div>
                    <div class="text-h5 font-weight-bold">{{ totals.failed }}</div>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card variant="outlined" class="pa-4">
                    <div class="text-caption text-uppercase text-medium-emphasis">Net Received</div>
                    <div class="text-h5 font-weight-bold">KSh {{ totals.received.toLocaleString() }}</div>
                </v-card>
            </v-col>
        </v-row>

        <v-card variant="outlined" class="mb-4 pa-3 d-flex flex-wrap ga-3 align-center">
            <v-text-field v-model="from" label="From" type="date" hide-details density="compact" style="max-width: 180px;"></v-text-field>
            <v-text-field v-model="to" label="To" type="date" hide-details density="compact" style="max-width: 180px;"></v-text-field>
            <v-select v-model="status" :items="statusOptions" label="Status" hide-details density="compact" clearable style="max-width: 200px;"></v-select>
            <v-btn color="primary" size="small" prepend-icon="mdi-filter-variant" @click="load">Apply</v-btn>
        </v-card>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <v-card v-if="!loading && payments.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-cash-multiple" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No payments found</h3>
            <p class="text-body-2 text-medium-emphasis">Payments for this facility will appear here.</p>
        </v-card>

        <v-card v-if="!loading && payments.length > 0" variant="outlined">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Booking</th>
                        <th>Gross</th>
                        <th>Commission</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments" :key="p.id">
                        <td class="text-body-2">{{ p.reference }}</td>
                        <td class="font-weight-medium">{{ p.doctor }}</td>
                        <td>KSh {{ Number(p.gross_amount).toLocaleString() }}</td>
                        <td class="text-caption">- KSh {{ Number(p.commission_amount).toLocaleString() }}</td>
                        <td class="font-weight-medium">KSh {{ Number(p.net_amount).toLocaleString() }}</td>
                        <td><v-chip :color="statusColor(p.status)" size="x-small" variant="tonal">{{ p.status }}</v-chip></td>
                        <td class="text-caption">{{ formatDate(p.created_at) }}</td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const payments = ref([]);
const loading = ref(false);
const error = ref(null);
const from = ref('');
const to = ref('');
const status = ref(null);
const statusOptions = ['paid', 'pending', 'failed', 'cancelled', 'refunded', 'expired'];

const totals = ref({ paid: 0, pending: 0, failed: 0, received: 0 });

function statusColor(s) {
    if (s === 'paid') return 'success';
    if (s === 'pending' || s === 'processing') return 'warning';
    if (s === 'failed' || s === 'cancelled') return 'error';
    return 'default';
}

function formatDate(d) {
    return d ? new Date(d).toLocaleString() : '—';
}

function computeTotals() {
    const t = { paid: 0, pending: 0, failed: 0, received: 0 };
    for (const p of payments.value) {
        if (p.status === 'paid') t.paid += 1;
        if (p.status === 'pending') t.pending += 1;
        if (p.status === 'failed' || p.status === 'cancelled') t.failed += 1;
        if (p.status === 'paid') t.received += Number(p.net_amount || 0);
    }
    totals.value = t;
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        payments.value = await facilityWorkspaceService.getPayments({ from: from.value, to: to.value, status: status.value });
        computeTotals();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load payments.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>