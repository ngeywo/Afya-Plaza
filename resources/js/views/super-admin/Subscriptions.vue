<template>
    <div class="subscriptions-page">
        <AppPageHeader title="Subscriptions" subtitle="Facility subscription accounts" icon="mdi-bank">
            <template #actions>
                <v-select v-model="statusFilter" :items="statusOptions" label="Status filter" clearable hide-details density="compact" class="w-56" @update:model-value="fetchSubscriptions"></v-select>
            </template>
        </AppPageHeader>
        <v-card>
            <v-card-text>
                <v-data-table :headers="headers" :items="subscriptions" :loading="loading" :items-length="meta.total" class="elevation-0">
                    <template #item.facility="{ item }">
                        <div class="font-weight-medium">{{ item.facility?.name || "-" }}</div>
                        <div class="text-caption text-medium-emphasis">{{ item.facility?.slug || "" }}</div>
                    </template>
                    <template #item.plan="{ item }"><div>{{ item.plan?.name || "-" }} <span class="text-caption text-medium-emphasis">v{{ item.plan_version }}</span></div></template>
                    <template #item.status="{ item }"><v-chip :color="statusColor(item.status)" size="small" :variant="item.operational ? 'flat' : 'tonal'">{{ item.status_label }}</v-chip></template>
                    <template #item.amount="{ item }">{{ formatMoney(item.currency, item.effective_amount) }}</template>
                    <template #item.next_billing_date="{ item }">{{ formatDate(item.next_billing_date) }}</template>
                    <template #item.actions="{ item }">
                        <v-btn icon="mdi-bank-check" size="small" variant="text" color="success" title="Verify payment" @click="openVerify(item)"></v-btn>
                        <v-btn icon="mdi-open-in-new" size="small" variant="text" title="Adjust status" @click="openAdjust(item)"></v-btn>
                        <v-btn icon="mdi-eye" size="small" variant="text" title="Details" @click="openDetails(item)"></v-btn>
                    </template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-bank-minus" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No subscriptions found.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <v-dialog v-model="verifyDialog" max-width="480">
            <v-card><v-card-title>Verify Payment</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-3"><strong>{{ verifyTarget?.facility?.name }}</strong> — {{ verifyTarget?.plan?.name }} ({{ formatMoney(verifyTarget?.currency, verifyTarget?.effective_amount) }})</p>
                    <v-text-field v-model="verifyForm.reference" label="Payment Reference *" class="mb-2"></v-text-field>
                    <v-select v-model="verifyForm.payment_method" :items="['cash', 'cheque', 'bank_transfer', 'mpesa', 'other']" label="Payment Method *" class="mb-2"></v-select>
                    <v-textarea v-model="verifyForm.notes" label="Notes" rows="2"></v-textarea>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="verifyDialog = false">Cancel</v-btn><v-btn color="success" @click="submitVerify">Verify Payment</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="adjustDialog" max-width="480">
            <v-card><v-card-title>Adjust Subscription Status</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-3"><strong>{{ adjustTarget?.facility?.name }}</strong> — currently {{ adjustTarget?.status_label }}</p>
                    <v-select v-model="adjustForm.status" :items="statusOptions" label="New Status *" class="mb-2"></v-select>
                    <v-textarea v-model="adjustForm.reason" label="Reason" rows="2" hint="Saved to the subscription event log"></v-textarea>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="adjustDialog = false">Cancel</v-btn><v-btn color="primary" @click="submitAdjust">Apply</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="detailsDialog" max-width="640">
            <v-card><v-card-title>Subscription Details</v-card-title>
                <v-card-text>
                    <v-row v-if="details" dense>
                        <v-col cols="6"><div class="text-caption text-medium-emphasis">Facility</div><div class="font-weight-medium">{{ details.data?.facility?.name || "-" }}</div></v-col>
                        <v-col cols="6"><div class="text-caption text-medium-emphasis">Plan</div><div class="font-weight-medium">{{ details.data?.plan?.name || "-" }} v{{ details.data?.plan_version }}</div></v-col>
                        <v-col cols="6"><div class="text-caption text-medium-emphasis">Status</div><div><v-chip :color="statusColor(details.data?.status)" size="x-small">{{ details.data?.status_label }}</v-chip></div></v-col>
                        <v-col cols="6"><div class="text-caption text-medium-emphasis">Billing Cycle</div><div class="font-weight-medium">{{ details.data?.billing_cycle }}</div></v-col>
                        <v-col cols="6"><div class="text-caption text-medium-emphasis">Next Billing</div><div>{{ formatDate(details.data?.next_billing_date) }}</div></v-col>
                        <v-col cols="6"><div class="text-caption text-medium-emphasis">Last Paid</div><div>{{ formatMoney(details.data?.plan_snapshot?.currency, details.data?.last_amount_paid) }}</div></v-col>
                        <v-col cols="12"><v-divider class="my-1"></v-divider></v-col>
                        <v-col cols="12"><div class="text-caption text-medium-emphasis mb-1">Plan Limits (snapshot)</div>
                            <v-chip v-for="(val, key) in limitChips" :key="key" size="small" variant="tonal" class="mr-1 mb-1">{{ key }}: {{ val ?? "∞" }}</v-chip>
                        </v-col>
                        <v-col cols="12"><v-divider class="my-1"></v-divider></v-col>
                        <v-col cols="12"><div class="text-caption text-medium-emphasis mb-1">Event Log</div>
                            <v-list dense v-if="details.data?.events?.length">
                                <v-list-item v-for="(e, i) in details.data.events" :key="i" class="py-0">
                                    <v-list-item-title class="text-body-2">{{ e.event }} <span class="text-caption">→ {{ e.to_status }}</span></v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">{{ e.actor || "System" }} · {{ formatDate(e.created_at) }}<span v-if="e.reason"> · {{ e.reason }}</span></v-list-item-subtitle>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-caption text-medium-emphasis">No events recorded.</div>
                        </v-col>
                    </v-row>
                    <div v-else class="pa-4 text-center text-body-2 text-medium-emphasis">Loading…</div>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="detailsDialog = false">Close</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from "vue";
import { adminService } from "../../services/adminService";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import { useNotifier } from "../../composables/useNotifier";
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();
const subscriptions = ref([]);
const details = ref(null);
const loading = ref(false);
const meta = ref({ total: 0 });
const statusFilter = ref(null);
const verifyDialog = ref(false);
const adjustDialog = ref(false);
const detailsDialog = ref(false);
const verifyTarget = ref(null);
const adjustTarget = ref(null);
const verifyForm = ref({ reference: "", payment_method: "mpesa", notes: "" });
const adjustForm = ref({ status: "active", reason: "" });
const statusOptions = ["pending_payment", "trial", "active", "past_due", "grace_period", "suspended", "cancelled", "expired"];
const headers = [
    { title: "Facility", key: "facility" },
    { title: "Plan", key: "plan", sortable: false },
    { title: "Status", key: "status", sortable: false },
    { title: "Cycle", key: "billing_cycle" },
    { title: "Amount", key: "amount", sortable: false },
    { title: "Next Billing", key: "next_billing_date" },
    { title: "Actions", key: "actions", sortable: false },
];
const limitChips = computed(() => { const p = details.value?.data?.plan_snapshot || {}; return { Drs: p.max_doctors, Staff: p.max_staff, Locations: p.max_locations, "Bookings/mo": p.max_monthly_bookings, SMS: p.max_sms, Storage: p.max_storage_mb, Trial: p.trial_days }; });
async function fetchSubscriptions() { loading.value = true; try { const { data } = await adminService.getSubscriptions({ status: statusFilter.value || undefined }); subscriptions.value = data.data; meta.value = data.meta; } catch (err) { console.error(err); } finally { loading.value = false; } }
function openVerify(item) { verifyTarget.value = item; verifyForm.value = { reference: "", payment_method: "mpesa", notes: "" }; verifyDialog.value = true; }
function openAdjust(item) { adjustTarget.value = item; adjustForm.value = { status: item.status, reason: "" }; adjustDialog.value = true; }
async function openDetails(item) { detailsDialog.value = true; details.value = null; try { const { data } = await adminService.getSubscription(item.id); details.value = data; } catch (err) { console.error(err); } }
async function submitVerify() { try { await adminService.verifySubscriptionPayment(verifyTarget.value.id, verifyForm.value); verifyDialog.value = false; notify("Payment verified"); fetchSubscriptions(); } catch (err) { notifyError(err.response?.data?.error || "Error verifying payment"); } }
async function submitAdjust() { try { await adminService.adjustSubscription(adjustTarget.value.id, adjustForm.value); adjustDialog.value = false; notify("Subscription status updated"); fetchSubscriptions(); } catch (err) { notifyError(err.response?.data?.error || err.response?.data?.message || "Error adjusting subscription"); } }
function statusColor(s) { const colors = { pending_payment: "warning", trial: "info", active: "success", past_due: "error", grace_period: "warning", suspended: "grey", cancelled: "grey", expired: "grey" }; return colors[s] || "grey"; }
function formatMoney(cur, v) { return v === null || v === undefined ? "-" : `${cur || "KES"} ${Number(v).toLocaleString()}`; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : "-"; }
onMounted(fetchSubscriptions);
</script>