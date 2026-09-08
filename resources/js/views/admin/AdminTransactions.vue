<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex flex-wrap align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Transactions</h1>
                <p class="text-body-2 text-medium-emphasis">The platform ledger — patient payments, settlements and doctor payouts.</p>
            </div>
            <v-select v-model="type" :items="typeOptions" label="Type" variant="outlined" density="compact" hide-details class="flex-grow-0" style="max-width: 220px" @update:model-value="load"></v-select>
        </div>
        <v-row class="mb-4">
            <v-col cols="12" md="4"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">In (net)</div><div class="text-h6 font-weight-bold text-success">KES {{ Number(totals.in || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
            <v-col cols="12" md="4"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Out</div><div class="text-h6 font-weight-bold text-error">KES {{ Number(totals.out || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
            <v-col cols="12" md="4"><v-card variant="outlined"><v-card-text><div class="text-caption text-medium-emphasis">Commission</div><div class="text-h6 font-weight-bold text-primary">KES {{ Number(totals.commission || 0).toLocaleString() }}</div></v-card-text></v-card></v-col>
        </v-row>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="rows" :loading="loading" :items-per-page="25" density="comfortable">
                <template #item.type="{ item }"><v-chip size="x-small" :color="item.type === 'payment' ? 'info' : item.type === 'settlement' ? 'primary' : 'secondary'" variant="flat">{{ item.type }}</v-chip></template>
                <template #item.description="{ item }">
                    <div class="text-body-2">{{ item.description }}</div>
                    <div class="text-caption text-medium-emphasis">{{ item.reference }}</div>
                </template>
                <template #item.direction="{ item }">
                    <v-chip size="x-small" :color="item.direction === 'in' ? 'success' : 'error'" variant="flat">{{ item.direction === "in" ? "▼ In" : "▲ Out" }}</v-chip>
                </template>
                <template #item.amounts="{ item }">
                    <div class="text-body-2">Net <strong>KES {{ Number(item.net).toLocaleString() }}</strong></div>
                    <div class="text-caption text-medium-emphasis">Comm KES {{ Number(item.commission).toLocaleString() }}</div>
                </template>
                <template #item.status="{ item }"><span class="text-body-2">{{ item.status }}</span></template>
                <template #item.created_at="{ item }"><span class="text-caption">{{ formatDate(item.created_at) }}</span></template>
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
const totals = ref({});
const type = ref("all");
const typeOptions = [{ title: "All", value: "all" }, { title: "Payments", value: "payment" }, { title: "Settlements", value: "settlement" }, { title: "Payouts", value: "payout" }];

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getTransactions({ type: type.value === "all" ? undefined : type.value });
        rows.value = res.data;
        totals.value = res.totals || {};
    } catch (e) {
        error.value = "Failed to load transactions.";
    } finally {
        loading.value = false;
    }
}

function formatDate(d) {
    return d ? new Date(d).toLocaleString() : "—";
}

const headers = [
    { title: "Type", key: "type", sortable: true },
    { title: "Description", key: "description", sortable: false },
    { title: "Direction", key: "direction", sortable: true },
    { title: "Amounts", key: "amounts", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "When", key: "created_at", sortable: true },
];

onMounted(load);
</script>