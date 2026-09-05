<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h1 class="text-h5 font-weight-bold text-red-darken-2">Payout Management</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">Review and process provider payout requests.</p>
      </div>
      <v-btn variant="outlined" size="small" prepend-icon="mdi-refresh" @click="loadPayouts" :loading="loading">Refresh</v-btn>
    </div>

    <v-row dense class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Pending</div>
          <div class="text-h5 font-weight-bold text-warning mt-1">{{ summary.pending }}</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Processing</div>
          <div class="text-h5 font-weight-bold text-info mt-1">{{ summary.processing }}</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Total Paid</div>
          <div class="text-h5 font-weight-bold text-success mt-1">{{ formatCurrency(summary.totalPaid) }}</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="flat" class="pa-4 border">
          <div class="text-caption text-medium-emphasis">Rejected</div>
          <div class="text-h5 font-weight-bold text-error mt-1">{{ summary.rejected }}</div>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="flat" class="border mb-4">
      <v-card-text>
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-select v-model="filters.status" :items="statusOptions" item-title="label" item-value="value"
              label="Status" density="compact" variant="outlined" hide-details clearable @update:model-value="loadPayouts" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card variant="flat" class="border">
      <v-card-title class="text-body-1">Payout Requests ({{ meta.total || 0 }})</v-card-title>
      <v-divider></v-divider>
      <v-data-table :headers="headers" :items="payouts" :items-per-page="15" :loading="loading" :total-items="meta.total" class="elevation-0">
        <template #item.status="{ item }"><v-chip :color="statusColor(item.status)" size="small" variant="flat">{{ statusLabel(item.status) }}</v-chip></template>
        <template #item.doctor="{ item }">{{ item.doctor?.name || '---' }}</template>
        <template #item.amount="{ item }"><span class="font-weight-bold">{{ formatCurrency(item.amount) }}</span></template>
        <template #item.requested_at="{ item }">{{ formatDate(item.requested_at) }}</template>
        <template #item.paid_at="{ item }">{{ item.paid_at ? formatDate(item.paid_at) : '---' }}</template>
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <template v-if="item.status === 'requested'">
              <v-btn size="x-small" color="success" variant="tonal" @click="approvePayout(item)" :loading="actionLoading === item.id + '-approve'">Approve</v-btn>
              <v-btn size="x-small" color="error" variant="tonal" @click="showRejectDialog(item)">Reject</v-btn>
            </template>
            <template v-else-if="['requested', 'rejected'].includes(item.status)">
              <v-btn size="x-small" color="warning" variant="tonal" @click="cancelPayout(item)" :loading="actionLoading === item.id + '-cancel'">Cancel</v-btn>
            </template>
            <v-btn size="x-small" variant="text" @click="viewDetails(item)">Details</v-btn>
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-8"><v-icon icon="mdi-cash-off" size="48" color="grey-lighten-1"></v-icon><div class="text-body-2 text-medium-emphasis mt-2">No payout requests found.</div></div>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="rejectDialog" max-width="480">
      <v-card>
        <v-card-title class="text-body-1 font-weight-bold">Reject Payout</v-card-title>
        <v-card-text>
          <p class="mb-3">Reject payout <strong>{{ selectedPayout?.reference }}</strong> of <strong>{{ formatCurrency(selectedPayout?.amount) }}</strong>?</p>
          <v-textarea v-model="rejectReason" label="Reason" variant="outlined" rows="2" />
        </v-card-text>
        <v-card-actions><v-spacer></v-spacer><v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn><v-btn color="error" @click="confirmReject">Reject</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="detailsDialog" max-width="500">
      <v-card v-if="selectedPayout">
        <v-card-title class="text-body-1 font-weight-bold">Payout {{ selectedPayout.reference }}</v-card-title>
        <v-card-text>
          <v-list density="compact">
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Doctor</v-list-item-title><v-list-item-subtitle>{{ selectedPayout.doctor?.name }}</v-list-item-subtitle></v-list-item>
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Amount</v-list-item-title><v-list-item-subtitle class="text-success font-weight-bold">{{ formatCurrency(selectedPayout.amount) }}</v-list-item-subtitle></v-list-item>
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Status</v-list-item-title><v-list-item-subtitle><v-chip :color="statusColor(selectedPayout.status)" size="small">{{ statusLabel(selectedPayout.status) }}</v-chip></v-list-item-subtitle></v-list-item>
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Requested</v-list-item-title><v-list-item-subtitle>{{ formatDate(selectedPayout.requested_at) }}</v-list-item-subtitle></v-list-item>
            <v-list-item v-if="selectedPayout.rejection_reason"><v-list-item-title class="text-caption text-medium-emphasis">Reason</v-list-item-title><v-list-item-subtitle>{{ selectedPayout.rejection_reason }}</v-list-item-subtitle></v-list-item>
          </v-list>
        </v-card-text>
        <v-card-actions><v-spacer></v-spacer><v-btn variant="text" @click="detailsDialog = false">Close</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import { financeService } from "../../services/financeService";

const loading = ref(false);
const payouts = ref([]);
const meta = ref({ total: 0 });
const actionLoading = ref('');
const filters = reactive({ status: '' });
const statusOptions = [
  { label: 'All', value: '' },
  { label: 'Requested', value: 'requested' },
  { label: 'Processing', value: 'processing' },
  { label: 'Paid', value: 'paid' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Cancelled', value: 'cancelled' },
];
const headers = [
  { title: 'Status', key: 'status' },
  { title: 'Reference', key: 'reference' },
  { title: 'Doctor', key: 'doctor' },
  { title: 'Amount', key: 'amount', align: 'end' },
  { title: 'Requested', key: 'requested_at' },
  { title: 'Paid At', key: 'paid_at' },
  { title: 'Actions', key: 'actions', align: 'end' },
];
const summary = computed(() => ({
  pending: payouts.value.filter(x => x.status === 'requested').length,
  processing: payouts.value.filter(x => x.status === 'processing').length,
  totalPaid: payouts.value.filter(x => x.status === 'paid').reduce((s, x) => s + parseFloat(x.amount || 0), 0),
  rejected: payouts.value.filter(x => ['rejected', 'cancelled'].includes(x.status)).length,
}));
const rejectDialog = ref(false);
const selectedPayout = ref(null);
const rejectReason = ref('');
const detailsDialog = ref(false);

async function loadPayouts() {
  loading.value = true;
  try {
    const r = await financeService.getAdminPayouts({ status: filters.status || undefined });
    payouts.value = r.data;
    meta.value = r.meta;
  } catch (e) {
    alert(e.response?.data?.error || 'Failed to load');
  } finally {
    loading.value = false;
  }
}
async function approvePayout(p) {
  if (!confirm('Approve payout ' + p.reference + '?')) return;
  actionLoading.value = p.id + '-approve';
  try {
    await financeService.approvePayout(p.id);
    await loadPayouts();
  } catch (e) {
    alert(e.response?.data?.error || 'Failed');
  } finally {
    actionLoading.value = '';
  }
}
function showRejectDialog(p) {
  selectedPayout.value = p;
  rejectReason.value = '';
  rejectDialog.value = true;
}
async function confirmReject() {
  if (!selectedPayout.value) return;
  try {
    await financeService.rejectPayout(selectedPayout.value.id, rejectReason.value);
    rejectDialog.value = false;
    await loadPayouts();
  } catch (e) {
    alert(e.response?.data?.error || 'Failed');
  }
}
async function cancelPayout(p) {
  if (!confirm('Cancel payout ' + p.reference + '?')) return;
  actionLoading.value = p.id + '-cancel';
  try {
    await financeService.cancelPayout(p.id);
    await loadPayouts();
  } catch (e) {
    alert(e.response?.data?.error || 'Failed');
  } finally {
    actionLoading.value = '';
  }
}
function viewDetails(p) {
  selectedPayout.value = p;
  detailsDialog.value = true;
}
function formatCurrency(v) {
  if (v == null) return '---';
  const n = parseFloat(v);
  if (isNaN(n)) return '---';
  return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(n);
}
function formatDate(iso) {
  if (!iso) return '---';
  return new Date(iso).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' });
}
function statusColor(s) {
  return { requested: 'warning', processing: 'info', paid: 'success', rejected: 'error', cancelled: 'grey' }[s] || 'grey';
}
function statusLabel(s) {
  return { requested: 'Requested', processing: 'Processing', paid: 'Paid', rejected: 'Rejected', cancelled: 'Cancelled' }[s] || s;
}
onMounted(() => loadPayouts());
</script>

<style scoped>
.gap-1 { gap: 4px; }
</style>