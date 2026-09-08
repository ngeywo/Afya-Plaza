<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>

        <div class="mb-4">
            <h1 class="text-h5 font-weight-bold mb-0">Transactions</h1>
            <p class="text-body-2 text-medium-emphasis mb-0">A statement of all financial activity for this facility.</p>
        </div>

        <v-card variant="outlined" class="mb-4 pa-4 d-flex align-center">
            <div class="mr-6">
                <div class="text-caption text-medium-emphasis text-uppercase">Total In</div>
                <div class="text-h5 font-weight-bold">KSh {{ Number(totals.in || 0).toLocaleString() }}</div>
            </div>
            <div>
                <div class="text-caption text-medium-emphasis text-uppercase">Entries</div>
                <div class="text-h5 font-weight-bold">{{ totals.count || 0 }}</div>
            </div>
        </v-card>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <v-card v-if="!loading && rows.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-swap-horizontal" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No transactions</h3>
            <p class="text-body-2 text-medium-emphasis">Payments and settlements for this facility will appear here.</p>
        </v-card>

        <v-card v-if="!loading && rows.length > 0" variant="outlined">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Fee</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id">
                        <td><v-chip :color="row.type === 'payment' ? 'primary' : 'secondary'" size="x-small" variant="tonal">{{ row.type }}</v-chip></td>
                        <td class="text-body-2">{{ row.reference }}</td>
                        <td class="text-body-2">{{ row.description }}</td>
                        <td>KSh {{ Number(row.gross).toLocaleString() }}</td>
                        <td class="text-caption">- KSh {{ Number(row.fee || 0).toLocaleString() }}</td>
                        <td class="font-weight-medium">KSh {{ Number(row.net).toLocaleString() }}</td>
                        <td><v-chip :color="statusColor(row.status)" size="x-small" variant="tonal">{{ row.status }}</v-chip></td>
                        <td class="text-caption">{{ formatDate(row.created_at) }}</td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const rows = ref([]);
const totals = ref({});
const loading = ref(false);
const error = ref(null);

function statusColor(s) {
    if (s === 'paid' || s === 'completed') return 'success';
    if (s === 'pending') return 'warning';
    return 'default';
}

function formatDate(d) {
    return d ? new Date(d).toLocaleString() : '—';
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const response = await facilityWorkspaceService.getTransactions();
        rows.value = response.data || [];
        totals.value = response.totals || {};
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load transactions.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>