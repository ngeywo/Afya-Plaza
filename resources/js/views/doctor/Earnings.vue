<template>
  <div>
<AppPageHeader title="Earnings &amp; Payouts" subtitle="Track your consultation earnings and request payouts." icon="mdi-cash-multiple">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-cash-fast" @click="showPayoutDialog = true" :disabled="!canRequestPayout" :loading="store.loading">
          Request Payout
        </v-btn>
      </template>
    </AppPageHeader>
    <v-alert v-if="store.error" type="error" closable class="mb-4" @click:close="store.clearError()">
      {{ store.error }}
    </v-alert>

    <v-row dense>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Available Balance</div>
          <div class="text-h5 font-weight-bold text-success mt-1">{{ formatCurrency(store.summary?.available) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">Ready to withdraw</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Pending</div>
          <div class="text-h5 font-weight-bold text-warning mt-1">{{ formatCurrency(store.summary?.pending) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">Awaiting availability</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Paid Out</div>
          <div class="text-h5 font-weight-bold text-primary mt-1">{{ formatCurrency(store.summary?.paid_out) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">Lifetime total</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Reversed</div>
          <div class="text-h5 font-weight-bold text-error mt-1">{{ formatCurrency(store.summary?.reversed) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">Refunds &amp; chargebacks</div>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="flat" class="border mt-6">
      <v-card-title class="text-body-1 font-weight-bold d-flex align-center">
        <v-icon icon="mdi-receipt-text-outline" class="mr-2" size="small"></v-icon>
        Recent Earnings
        <v-spacer></v-spacer>
        <v-text-field v-model="filter" density="compact" hide-details variant="outlined" placeholder="Search" prepend-inner-icon="mdi-magnify" style="max-width:240px"></v-text-field>
      </v-card-title>
      <v-divider></v-divider>
      <v-data-table :headers="headers" :items="filteredEarnings" :items-per-page="20" :loading="store.loading">
        <template #item.status="{ item }">
          <v-chip :color="statusColor(item.status)" size="small" variant="flat" label>{{ statusLabel(item.status) }}</v-chip>
        </template>
        <template #item.gross_amount="{ item }"><span class="font-weight-medium">{{ formatCurrency(item.gross_amount) }}</span></template>
        <template #item.commission_amount="{ item }"><span class="text-medium-emphasis">&#8722;{{ formatCurrency(item.commission_amount) }}</span></template>
        <template #item.net_amount="{ item }"><span class="font-weight-bold text-success">{{ formatCurrency(item.net_amount) }}</span></template>
        <template #item.commission_rate_snapshot="{ item }"><span v-if="item.commission_rate_snapshot">{{ formatPercent(item.commission_rate_snapshot) }}</span></template>
        <template #item.created_at="{ item }"><span class="text-caption">{{ formatDate(item.created_at) }}</span></template>
        <template #no-data>
          <div class="text-center py-8">
            <v-icon icon="mdi-cash-remove" size="64" color="grey-lighten-1"></v-icon>
            <div class="text-body-2 text-medium-emphasis mt-2">No earnings yet.</div>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="showPayoutDialog" max-width="480">
      <v-card>
        <v-card-title class="text-body-1 font-weight-bold">Request Payout</v-card-title>
        <v-card-text>
          <p class="mb-3">Move <strong class="text-success">{{ formatCurrency(store.summary?.available) }}</strong> into a payout request.</p>
          <v-alert v-if="payoutError" type="error" variant="tonal" density="compact" class="mb-2">{{ payoutError }}</v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showPayoutDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="payoutLoading" @click="onRequestPayout">Confirm</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useFinanceStore } from "../../stores/financeStore";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";

const store = useFinanceStore();
const showPayoutDialog = ref(false);
const payoutLoading = ref(false);
const payoutError = ref(null);
const filter = ref("");

const headers = [
  { title: "Status", key: "status", sortable: true },
  { title: "Date", key: "created_at", sortable: true },
  { title: "Description", key: "description", sortable: false },
  { title: "Plan", key: "plan_slug", sortable: false },
  { title: "Rate", key: "commission_rate_snapshot", sortable: false, align: "end" },
  { title: "Gross", key: "gross_amount", sortable: true, align: "end" },
  { title: "Commission", key: "commission_amount", sortable: true, align: "end" },
  { title: "Net", key: "net_amount", sortable: true, align: "end" },
];

const filteredEarnings = computed(() => {
  if (!filter.value) return store.earnings;
  const f = filter.value.toLowerCase();
  return store.earnings.filter(e =>
    (e.description || "").toLowerCase().includes(f) ||
    (e.plan_slug || "").toLowerCase().includes(f)
  );
});

const canRequestPayout = computed(() => parseFloat(store.summary?.available) > 0);

onMounted(() => { store.fetchEarnings(); });

function formatCurrency(value) {
  if (value == null) return "—";
  const n = parseFloat(value);
  if (isNaN(n)) return "—";
  return new Intl.NumberFormat("en-KE", { style: "currency", currency: "KES" }).format(n);
}
function formatPercent(value) {
  if (!value) return "—";
  return `${(parseFloat(value) * 100).toFixed(1)}%`;
}
function formatDate(iso) {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("en-KE", { day: "2-digit", month: "short", year: "numeric" });
}
function statusColor(s) {
  return { available: "success", pending: "warning", paid_out: "primary", reversed: "error" }[s] || "grey";
}
function statusLabel(s) {
  return { available: "Available", pending: "Pending", paid_out: "Paid Out", reversed: "Reversed" }[s] || s;
}
async function onRequestPayout() {
  payoutError.value = null;
  payoutLoading.value = true;
  try {
    await store.requestPayout();
    showPayoutDialog.value = false;
  } catch (e) {
    payoutError.value = e.response?.data?.error || "Payout request failed.";
  } finally {
    payoutLoading.value = false;
  }
}
</script>
