<template>
  <div>
    <AppPageHeader title="Payout Management" subtitle="Review and process provider payout requests." icon="mdi-cash-multiple">
      <template #actions>
        <v-btn variant="outlined" size="small" prepend-icon="mdi-refresh" @click="loadPayouts" :loading="loading">Refresh</v-btn>
      </template>
    </AppPageHeader>

    <v-row dense class="mb-4">
      <v-col cols="6" md="3">
        <AppStatCard label="Pending" :value="summary.pending" icon="mdi-clock-outline" tone="warning" />
      </v-col>
      <v-col cols="6" md="3">
        <AppStatCard label="Processing" :value="summary.processing" icon="mdi-progress-clock" tone="info" />
      </v-col>
      <v-col cols="6" md="3">
        <AppStatCard label="Total Paid" :value="formatCurrency(summary.totalPaid)" icon="mdi-cash-check" tone="success" />
      </v-col>
      <v-col cols="6" md="3">
        <AppStatCard label="Rejected" :value="summary.rejected" icon="mdi-cancel" tone="error" />
      </v-col>
    </v-row>

    <v-card variant="outlined" class="mb-4">
      <v-card-text>
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-select v-model="filters.status" :items="statusOptions" item-title="label" item-value="value"
              label="Status" density="compact" variant="outlined" hide-details clearable @update:model-value="loadPayouts" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center gap-2">
        <v-icon icon="mdi-cash-multiple" color="primary"></v-icon>Payout Requests ({{ meta.total || 0 }})
      </v-card-title>
      <v-divider></v-divider>
      <v-data-table :headers="headers" :items="payouts" :items-per-page="15" :loading="loading" :total-items="meta.total" class="elevation-0">
        <template #item.status="{ item }"><AppStatusChip :status="item.status" size="small" /></template>
        <template #item.doctor="{ item }">{{ item.doctor?.name || '---' }}</template>
        <template #item.amount="{ item }"><span class="font-weight-bold">{{ formatCurrency(item.amount) }}</span></template>
        <template #item.requested_at="{ item }">{{ formatDate(item.requested_at) }}</template>
        <template #item.paid_at="{ item }">{{ item.paid_at ? formatDate(item.paid_at) : '---' }}</template>
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <template v-if="item.status === 'requested'">
              <v-btn size="small" color="success" variant="tonal" @click="openApprove(item)" :loading="actionLoading === item.id + '-approve'">Approve</v-btn>
              <v-btn size="small" color="error" variant="outlined" @click="showRejectDialog(item)">Reject</v-btn>
            </template>
            <template v-else-if="['requested', 'rejected'].includes(item.status)">
              <v-btn size="small" color="warning" variant="tonal" @click="openCancel(item)" :loading="actionLoading === item.id + '-cancel'">Cancel</v-btn>
            </template>
            <v-btn size="small" variant="text" @click="viewDetails(item)">Details</v-btn>
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-8"><v-icon icon="mdi-cash-off" size="48" color="grey-lighten-1"></v-icon><div class="text-body-2 text-medium-emphasis mt-2">No payout requests found.</div></div>
        </template>
      </v-data-table>
    </v-card>

    <AppConfirmDialog v-model="approveDialog" title="Approve payout" :message="`Approve payout ${approveTarget?.reference ?? ''} of ${formatCurrency(approveTarget?.amount)}?`" confirm-label="Approve" confirm-color="success" confirm-icon="mdi-check" :loading="actionLoading === (approveTarget?.id ?? '') + '-approve'" @confirm="confirmApprove" />
    <AppConfirmDialog v-model="cancelDialog" title="Cancel payout" :message="`Cancel payout ${cancelTarget?.reference ?? ''}?`" confirm-label="Cancel Payout" confirm-color="warning" confirm-icon="mdi-cash-remove" :loading="actionLoading === (cancelTarget?.id ?? '') + '-cancel'" @confirm="confirmCancel" />
    <AppConfirmDialog v-model="rejectDialog" title="Reject payout" confirm-label="Reject" confirm-color="error" confirm-icon="mdi-cancel" @confirm="confirmReject">
      <template #content>
        <div class="mt-1">Reject payout <strong>{{ selectedPayout?.reference }}</strong> of <strong>{{ formatCurrency(selectedPayout?.amount) }}</strong>?</div>
        <v-textarea v-model="rejectReason" label="Reason" variant="outlined" rows="2" class="mt-3" />
      </template>
    </AppConfirmDialog>
    <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>

    <v-dialog v-model="detailsDialog" max-width="500">
      <v-card v-if="selectedPayout">
        <v-card-title class="text-body-1 font-weight-bold">Payout {{ selectedPayout.reference }}</v-card-title>
        <v-card-text>
          <v-list density="compact">
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Doctor</v-list-item-title><v-list-item-subtitle>{{ selectedPayout.doctor?.name }}</v-list-item-subtitle></v-list-item>
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Amount</v-list-item-title><v-list-item-subtitle class="text-success font-weight-bold">{{ formatCurrency(selectedPayout.amount) }}</v-list-item-subtitle></v-list-item>
            <v-list-item><v-list-item-title class="text-caption text-medium-emphasis">Status</v-list-item-title><v-list-item-subtitle><AppStatusChip :status="selectedPayout.status" size="x-small" /></v-list-item-subtitle></v-list-item>
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
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import AppStatCard from "../../components/ui/AppStatCard.vue";
import AppStatusChip from "../../components/ui/AppStatusChip.vue";
import AppConfirmDialog from "../../components/ui/AppConfirmDialog.vue";
import { useNotifier } from "../../composables/useNotifier";

const { snackbar, snackText, snackColor, notifyError } = useNotifier();

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
const approveDialog = ref(false);
const cancelDialog = ref(false);
const selectedPayout = ref(null);
const approveTarget = ref(null);
const cancelTarget = ref(null);
const rejectReason = ref('');
const detailsDialog = ref(false);

async function loadPayouts() {
  loading.value = true;
  try {
    const r = await financeService.getAdminPayouts({ status: filters.status || undefined });
    payouts.value = r.data;
    meta.value = r.meta;
  } catch (e) {
    notifyError(e.response?.data?.error || 'Failed to load');
  } finally {
    loading.value = false;
  }
}
function openApprove(p) { approveTarget.value = p; approveDialog.value = true; }
function openCancel(p) { cancelTarget.value = p; cancelDialog.value = true; }
async function confirmApprove() {
  const p = approveTarget.value;
  if (!p) return;
  actionLoading.value = p.id + '-approve';
  try {
    await financeService.approvePayout(p.id);
    approveDialog.value = false;
    await loadPayouts();
  } catch (e) {
    notifyError(e.response?.data?.error || 'Failed');
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
  actionLoading.value = selectedPayout.value.id + '-reject';
  try {
    await financeService.rejectPayout(selectedPayout.value.id, rejectReason.value);
    rejectDialog.value = false;
    await loadPayouts();
  } catch (e) {
    notifyError(e.response?.data?.error || 'Failed');
  } finally {
    actionLoading.value = '';
  }
}
async function confirmCancel() {
  const p = cancelTarget.value;
  if (!p) return;
  actionLoading.value = p.id + '-cancel';
  try {
    await financeService.cancelPayout(p.id);
    cancelDialog.value = false;
    await loadPayouts();
  } catch (e) {
    notifyError(e.response?.data?.error || 'Failed');
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
onMounted(() => loadPayouts());
</script>

<style scoped>
.gap-1 { gap: 4px; }
</style>