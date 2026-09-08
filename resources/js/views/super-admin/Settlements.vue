<template>
    <div class="settlements-page">
        <AppPageHeader title="Settlements" subtitle="Manage financial settlements" icon="mdi-bank-transfer" />
        <v-row class="mb-4" dense>
            <v-col cols="6" md="3"><AppStatCard label="Pending" :value="summary?.pending_count || 0" icon="mdi-clock-outline" tone="warning" /></v-col>
            <v-col cols="6" md="3"><AppStatCard label="Approved" :value="formatCurrency(summary?.total_approved || 0)" icon="mdi-check-circle-outline" tone="success" /></v-col>
            <v-col cols="6" md="3"><AppStatCard label="Paid Out" :value="formatCurrency(summary?.total_paid || 0)" icon="mdi-cash-check" tone="neutral" /></v-col>
            <v-col cols="6" md="3"><AppStatCard label="Pending Amount" :value="formatCurrency(summary?.pending_amount || 0)" icon="mdi-cash-clock" tone="info" /></v-col>
        </v-row>
        <v-card variant="outlined">
            <v-card-text>
                <v-data-table :headers="headers" :items="settlements" :loading="loading" :items-length="meta.total" class="elevation-0">
                    <template #item.doctor_name="{ item }"><span class="font-weight-medium">{{ item.doctor_name }}</span></template>
                    <template #item.amount="{ item }">{{ formatCurrency(item.amount) }}</template>
                    <template #item.status="{ item }"><AppStatusChip :status="item.status" size="small" /></template>
                    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
                    <template #item.actions="{ item }">
                        <div v-if="item.status === 'pending'" class="d-flex gap-1">
                            <v-btn size="small" color="success" variant="tonal" append-icon="mdi-check" @click="approveSettlement(item)">Approve</v-btn>
                            <v-btn size="small" color="error" variant="outlined" append-icon="mdi-close" @click="rejectSettlement(item)">Reject</v-btn>
                        </div>
                    </template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-bank-outline" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No settlements found.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>
        <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import api from "../../services/api";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import AppStatCard from "../../components/ui/AppStatCard.vue";
import AppStatusChip from "../../components/ui/AppStatusChip.vue";
import { useNotifier } from "../../composables/useNotifier";
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();
const settlements = ref([]);
const summary = ref({});
const loading = ref(false);
const meta = ref({ total: 0 });
const headers = [
    { title: "Doctor", key: "doctor_name" },
    { title: "Amount", key: "amount" },
    { title: "Status", key: "status" },
    { title: "Period", key: "period" },
    { title: "Created", key: "created_at" },
    { title: "Actions", key: "actions", sortable: false },
];
async function fetchSettlements() { loading.value = true; try { const { data } = await api.get("/admin/settlements"); settlements.value = data.data; meta.value = data.meta; } catch (err) { console.error(err); } finally { loading.value = false; } }
async function fetchSummary() { try { const { data } = await api.get("/admin/settlements/summary"); summary.value = data.data; } catch (err) { console.error(err); } }
async function approveSettlement(item) { try { await api.post(`/admin/settlements/${item.id}/approve`); notify("Settlement approved"); fetchSettlements(); fetchSummary(); } catch (err) { notifyError(err.response?.data?.error || "Error approving"); } }
async function rejectSettlement(item) { try { await api.post(`/admin/settlements/${item.id}/reject`); notify("Settlement rejected"); fetchSettlements(); fetchSummary(); } catch (err) { notifyError(err.response?.data?.error || "Error rejecting"); } }
function formatCurrency(v) { return new Intl.NumberFormat("en-KE", { style: "currency", currency: "KES" }).format(v); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : "-"; }
onMounted(() => { fetchSettlements(); fetchSummary(); });
</script>