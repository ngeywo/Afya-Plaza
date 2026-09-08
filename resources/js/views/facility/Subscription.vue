<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.clearError()">
            {{ store.error }}
        </v-alert>
        <v-progress-linear v-if="store.loading && !store.plan" indeterminate></v-progress-linear>
        <div v-if="store.plan">
            <v-card variant="outlined" class="mb-6 pa-5">
                <div class="d-flex align-center justify-space-between flex-wrap">
                    <div>
                        <div class="text-caption text-medium-emphasis text-uppercase mb-1">Current Plan</div>
                        <h1 class="text-h4 font-weight-bold">{{ store.currentPlanName }}</h1>
                        <v-chip :color="store.statusColor" size="small" variant="tonal" class="mt-2">{{ store.statusLabel }}</v-chip>
                    </div>
                    <div class="text-right">
                        <div class="text-caption text-medium-emphasis text-uppercase mb-1">Billing</div>
                        <div class="text-h6">{{ store.subscription?.billing_cycle || 'Monthly' }}</div>
                        <div v-if="store.subscription?.next_billing_date" class="text-caption text-medium-emphasis">
                            Next: {{ formatDate(store.subscription.next_billing_date) }}
                        </div>
                    </div>
                </div>
            </v-card>
            <v-alert v-if="store.isRestricted" type="warning" variant="tonal" class="mb-6" prominent>
                <v-alert-title>Subscription Restricted</v-alert-title>
                New capacity actions are blocked. Renew or update your subscription to restore full access.
            </v-alert>
            <h2 class="text-h6 font-weight-bold mb-3">Usage</h2>
            <v-row class="mb-6">
                <v-col cols="12" md="4">
                    <UsageCard title="Doctors" :usage="store.doctorUsage" icon="mdi-doctor" @manage="$router.push('/facility/doctors')" />
                </v-col>
                <v-col cols="12" md="4">
                    <UsageCard title="Staff" :usage="store.staffUsage" icon="mdi-account-tie" @manage="$router.push('/facility/staff')" />
                </v-col>
                <v-col cols="12" md="4">
                    <UsageCard title="Locations" :usage="store.locationUsage" icon="mdi-map-marker" @manage="$router.push('/facility/locations')" />
                </v-col>
            </v-row>
            <v-card variant="outlined" class="mb-6">
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Available Plans</span>
                </v-card-title>
                <v-card-text>
                    <v-row>
                        <v-col v-for="p in store.availablePlans" :key="p.slug" cols="12" md="4">
                            <v-card variant="outlined" class="pa-4 h-100">
                                <h3 class="text-h6 font-weight-bold mb-1">{{ p.name }}</h3>
                                <p class="text-body-2 text-medium-emphasis mb-2">{{ p.description }}</p>
                                <div class="mb-2">
                                    <span class="text-h5 font-weight-bold">{{ formatPrice(p.monthly_price) }}</span>
                                    <span class="text-caption text-medium-emphasis">/month</span>
                                </div>
                                <div class="text-caption text-medium-emphasis mb-3">
                                    {{ p.max_doctors || '∞' }} doctors · {{ p.max_staff || '∞' }} staff · {{ p.max_locations || '∞' }} loc
                                </div>
                                <v-btn block :color="isUpgrade(p) ? 'primary' : 'warning'" variant="tonal" @click="handleChange(p)">
                                    {{ isUpgrade(p) ? 'Upgrade' : 'Downgrade' }}
                                </v-btn>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </div>
        <v-dialog v-model="showCancelDialog" max-width="500">
            <v-card>
                <v-card-title>Cancel Subscription</v-card-title>
                <v-card-text>
                    <v-textarea v-model="cancelReason" label="Reason for cancellation" rows="3"></v-textarea>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="showCancelDialog = false">Keep Subscription</v-btn>
                    <v-btn color="error" :loading="store.loading" :disabled="!cancelReason.trim()" @click="handleCancel">Cancel</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useSubscriptionStore } from '../../stores/subscriptionStore';
import UsageCard from '../../components/UsageCard.vue';

const store = useSubscriptionStore();
const showCancelDialog = ref(false);
const cancelReason = ref('');
const snackbar = ref(false);
const snackText = ref('');
const snackColor = ref('success');

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString() : '—';
}

function formatPrice(p) {
    const n = parseFloat(p || 0);
    return n > 0 ? `KSh ${n.toLocaleString()}` : 'Free';
}

function isUpgrade(plan) {
    const current = store.subscription?.plan_snapshot?.max_doctors || 0;
    return (plan.max_doctors || 0) >= current;
}

async function handleChange(plan) {
    try {
        if (isUpgrade(plan)) {
            await store.upgrade(plan.slug);
            snackText.value = `Upgraded to ${plan.name}`;
        } else {
            await store.downgrade(plan.slug);
            snackText.value = `Downgraded to ${plan.name}`;
        }
        snackColor.value = 'success'; snackbar.value = true;
    } catch (e) {
        snackText.value = store.error || 'Operation failed'; snackColor.value = 'error'; snackbar.value = true;
    }
}

async function handleCancel() {
    try {
        await store.cancel(cancelReason.value);
        showCancelDialog.value = false; cancelReason.value = '';
        snackText.value = 'Subscription cancelled'; snackColor.value = 'success'; snackbar.value = true;
    } catch (e) {
        snackText.value = store.error || 'Cancellation failed'; snackColor.value = 'error'; snackbar.value = true;
    }
}
</script>
