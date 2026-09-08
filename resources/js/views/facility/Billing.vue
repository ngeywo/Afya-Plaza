<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>

        <div class="mb-4">
            <h1 class="text-h5 font-weight-bold mb-0">Billing</h1>
            <p class="text-body-2 text-medium-emphasis mb-0">Subscription charges and statement history.</p>
        </div>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <v-card v-if="!loading && data?.subscription" variant="outlined" class="mb-6 pa-5">
            <div class="d-flex align-center justify-space-between flex-wrap">
                <div>
                    <div class="text-caption text-medium-emphasis text-uppercase mb-1">Current Subscription</div>
                    <h2 class="text-h5 font-weight-bold mb-1">{{ data.subscription.plan_name }}</h2>
                    <v-chip :color="statusColor(data.subscription.status)" size="small" variant="tonal">{{ data.subscription.status }}</v-chip>
                </div>
                <div class="text-right">
                    <div class="text-caption text-medium-emphasis text-uppercase mb-1">{{ data.subscription.billing_cycle }} amount</div>
                    <div class="text-h5 font-weight-bold">KSh {{ Number(data.subscription.effective_amount).toLocaleString() }}</div>
                    <div v-if="data.subscription.last_amount_paid" class="text-caption text-medium-emphasis">
                        Last paid: KSh {{ Number(data.subscription.last_amount_paid).toLocaleString() }}
                    </div>
                </div>
            </div>
            <v-divider class="my-3"></v-divider>
            <div class="d-flex flex-wrap ga-6">
                <div>
                    <div class="text-caption text-medium-emphasis">Next billing</div>
                    <div class="font-weight-medium">{{ data.subscription.next_billing_date || '—' }}</div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">Last billing</div>
                    <div class="font-weight-medium">{{ data.subscription.last_billing_date || '—' }}</div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">Payment reference</div>
                    <div class="font-weight-medium">{{ data.subscription.payment_reference || '—' }}</div>
                </div>
                <div v-if="data.subscription.cancel_at_period_end">
                    <v-chip color="error" size="small" variant="tonal">Cancels at period end</v-chip>
                </div>
            </div>
        </v-card>

        <v-card v-if="!loading && !data?.subscription" variant="outlined" class="mb-6 pa-8 text-center">
            <v-icon icon="mdi-receipt" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No active subscription</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">This facility has not subscribed to a plan yet.</p>
            <v-btn color="primary" to="/facility/my-plan">Choose a Plan</v-btn>
        </v-card>

        <h2 class="text-h6 font-weight-bold mb-3">Statement</h2>
        <v-card v-if="!loading && events.length === 0" variant="outlined" class="pa-6 text-center">
            <p class="text-body-2 text-medium-emphasis mb-0">No billing events recorded.</p>
        </v-card>
        <v-card v-if="!loading && events.length > 0" variant="outlined">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Reason</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in events" :key="e.id">
                        <td class="font-weight-medium">{{ e.event }}</td>
                        <td class="text-caption">{{ e.from_status || '—' }}</td>
                        <td class="text-caption">{{ e.to_status || '—' }}</td>
                        <td class="text-body-2">{{ e.reason || '—' }}</td>
                        <td class="text-caption">{{ formatDate(e.created_at) }}</td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const data = ref(null);
const loading = ref(false);
const error = ref(null);

const events = computed(() => data.value?.events || []);

function statusColor(s) {
    if (s === 'active' || s === 'trial') return 'success';
    if (s === 'past_due' || s === 'grace_period') return 'warning';
    if (s === 'cancelled' || s === 'suspended' || s === 'expired') return 'error';
    return 'default';
}

function formatDate(d) {
    return d ? new Date(d).toLocaleString() : '—';
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        data.value = await facilityWorkspaceService.getBilling();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load billing.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>