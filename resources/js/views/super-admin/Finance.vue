<template>
  <div>
<AppPageHeader title="Marketplace Finance" subtitle="Platform revenue, payouts, and refunds." icon="mdi-chart-box">
      <template #actions><v-btn variant="outlined" size="small" prepend-icon="mdi-refresh" @click="loadAll" :loading="loading">Refresh</v-btn></template>
    </AppPageHeader>

    <v-row dense>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Gross Volume</div>
          <div class="text-h5 font-weight-bold mt-1">{{ formatCurrency(marketplace?.gross_volume) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">{{ marketplace?.appointment_count || 0 }} paid appointments</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Platform Revenue</div>
          <div class="text-h5 font-weight-bold text-success mt-1">{{ formatCurrency(marketplace?.platform_revenue) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">Commission earned</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Pending Payouts</div>
          <div class="text-h5 font-weight-bold text-warning mt-1">{{ formatCurrency(marketplace?.pending_payouts) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">Awaiting disbursement</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Refunds</div>
          <div class="text-h5 font-weight-bold text-error mt-1">{{ formatCurrency(marketplace?.refund_total) }}</div>
          <div class="text-caption text-medium-emphasis mt-1">{{ marketplace?.refund_count || 0 }} refunds</div>
        </v-card>
      </v-col>
    </v-row>

    <v-tabs v-model="tab" color="primary" class="mt-6" density="compact">
      <v-tab value="payments"><v-icon icon="mdi-credit-card-outline" class="mr-1" size="small"></v-icon>Payments</v-tab>
      <v-tab value="plans"><v-icon icon="mdi-tag-outline" class="mr-1" size="small"></v-icon>Plans</v-tab>
    </v-tabs>

    <v-window v-model="tab" class="mt-4">
      <v-window-item value="payments">
        <v-card variant="flat" class="border">
          <v-card-title class="text-body-2 d-flex align-center">
            <span>Payments ({{ meta.total || 0 }})</span>
            <v-spacer></v-spacer>
            <v-text-field v-model="filters.status" density="compact" hide-details variant="outlined" placeholder="Filter status" prepend-inner-icon="mdi-filter" style="max-width:200px"></v-text-field>
          </v-card-title>
          <v-divider></v-divider>
          <v-data-table :headers="paymentHeaders" :items="payments" :items-per-page="20" :loading="loading" :total-items="meta.total">
            <template #item.status="{ item }"><v-chip :color="pStatusColor(item.status)" size="small" variant="flat">{{ item.status }}</v-chip></template>
            <template #item.gross_amount="{ item }"><span class="font-weight-medium">{{ formatCurrency(item.gross_amount) }}</span></template>
            <template #item.commission_amount="{ item }"><span class="text-success">+{{ formatCurrency(item.commission_amount) }}</span></template>
            <template #item.net_amount="{ item }"><span class="font-weight-bold">{{ formatCurrency(item.net_amount) }}</span></template>
            <template #item.commission_rule_source="{ item }"><span class="text-caption">{{ item.commission_rule_source || '—' }}</span></template>
            <template #item.created_at="{ item }"><span class="text-caption">{{ formatDate(item.created_at) }}</span></template>
            <template #no-data><div class="text-center py-8 text-medium-emphasis">No payments found.</div></template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <v-window-item value="plans">
        <v-card variant="flat" class="border">
          <v-card-title class="text-body-2">Subscription Plans</v-card-title>
          <v-divider></v-divider>
          <v-table density="compact">
            <thead><tr>
              <th>Plan</th><th>Type</th><th>Commission</th><th>Monthly Price</th><th>Active Subscriptions</th>
            </tr></thead>
            <tbody>
              <tr v-for="plan in plans" :key="plan.id">
                <td><strong>{{ plan.name }}</strong><br><span class="text-caption text-medium-emphasis">{{ plan.slug }}</span></td>
                <td>{{ plan.commission_type === 1 ? 'Percentage' : 'Fixed' }}</td>
                <td>{{ plan.commission_type === 1 ? (plan.default_commission_rate * 100).toFixed(1) + '%' : formatCurrency(plan.fixed_commission_amount) }}</td>
                <td>{{ plan.monthly_price ? formatCurrency(plan.monthly_price) : 'Free' }}</td>
                <td><v-chip size="small" color="primary" variant="flat">{{ plan.active_subscriptions }}</v-chip></td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>
    </v-window>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from "vue";
import { financeService } from "../../services/financeService";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";

const tab = ref("payments");
const loading = ref(false);
const marketplace = ref(null);
const payments = ref([]);
const plans = ref([]);
const meta = ref({ total: 0 });

const filters = reactive({ status: "" });

const paymentHeaders = [
  { title: "Status", key: "status", sortable: false },
  { title: "Reference", key: "reference", sortable: false },
  { title: "Doctor", key: "doctor.name", sortable: false },
  { title: "Gross", key: "gross_amount", sortable: false, align: "end" },
  { title: "Commission", key: "commission_amount", sortable: false, align: "end" },
  { title: "Net", key: "net_amount", sortable: false, align: "end" },
  { title: "Source", key: "commission_rule_source", sortable: false },
  { title: "Date", key: "created_at", sortable: false },
];

async function loadMarketplace() {
  try {
    marketplace.value = await financeService.getMarketplace();
  } catch (e) {
    console.error("Failed to load marketplace", e);
  }
}

async function loadPayments() {
  loading.value = true;
  try {
    const res = await financeService.getPayments({ status: filters.status || undefined });
    payments.value = res.data;
    meta.value = res.meta;
  } catch (e) {
    console.error("Failed to load payments", e);
  } finally {
    loading.value = false;
  }
}

async function loadPlans() {
  try {
    plans.value = await financeService.getPlans();
  } catch (e) {
    console.error("Failed to load plans", e);
  }
}

async function loadAll() {
  loading.value = true;
  await Promise.all([loadMarketplace(), loadPayments(), loadPlans()]);
  loading.value = false;
}

watch(() => filters.status, () => loadPayments());
onMounted(() => loadAll());

function formatCurrency(value) {
  if (value == null) return "—";
  const n = parseFloat(value);
  if (isNaN(n)) return "—";
  return new Intl.NumberFormat("en-KE", { style: "currency", currency: "KES" }).format(n);
}
function formatDate(iso) {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("en-KE", { day: "2-digit", month: "short", year: "numeric" });
}
function pStatusColor(s) {
  return { paid: "success", pending: "warning", processing: "info", failed: "error", refunded: "grey" }[s] || "grey";
}
</script>
