<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-6">
            <div>
                <h1 class="text-h5 font-weight-bold mb-1">Pricing Plans</h1>
                <p class="text-body-2 text-medium-emphasis">Choose the commission plan that fits your practice.</p>
            </div>
            <v-btn variant="tonal" color="primary" :to="'/doctor/earnings'">Back to earnings</v-btn>
        </div>

        <v-alert v-if="store.error" type="error" variant="tonal" density="comfortable" class="mb-4" closable @click:close="store.error = null">
            {{ store.error }}
        </v-alert>
        <v-alert v-if="store.success" type="success" variant="tonal" density="comfortable" class="mb-4" closable @click:close="store.success = null">
            {{ store.success }}
        </v-alert>
        <v-progress-linear v-if="store.loading && !store.subscription" indeterminate></v-progress-linear>

        <template v-if="store.subscription">
            <!-- Current plan banner -->
            <v-card color="primary" variant="tonal" class="mb-6 pa-4">
                <div class="d-flex align-center">
                    <v-icon icon="mdi-crown" size="36" class="mr-3" color="primary"></v-icon>
                    <div class="flex-grow-1">
                        <div class="text-subtitle-1 font-weight-bold">Current plan — {{ currentPlanName }}</div>
                        <div class="text-body-2 text-medium-emphasis">
                            {{ commissionSummary(currentPlan) }}
                        </div>
                    </div>
                    <v-chip v-if="store.subscription.subscription?.status === 'active'" color="success" size="small" variant="tonal">
                        Active
                    </v-chip>
                </div>
            </v-card>

            <!-- Plan tiers -->
            <v-row>
                <v-col v-for="plan in store.subscription.plans" :key="plan.id" cols="12" md="4">
                    <v-card
                        class="plan-card fill-height"
                        rounded="xl"
                        :variant="isCurrent(plan) ? 'elevated' : 'outlined'"
                        :color="isCurrent(plan) ? 'primary' : ''"
                        :elevation="isCurrent(plan) ? 4 : 0"
                    >
                        <v-card-item>
                            <template #subtitle>
                                <v-chip v-if="isCurrent(plan)" color="primary" size="small" variant="tonal" class="mb-2">Current plan</v-chip>
                                <v-chip v-else-if="plan.is_default" size="small" variant="tonal" class="mb-2">Recommended</v-chip>
                            </template>
                            <v-card-title class="text-h6 font-weight-bold">{{ plan.name }}</v-card-title>
                            <div class="d-flex align-baseline mt-1">
                                <span class="text-h4 font-weight-black">KES {{ price(plan) }}</span>
                                <span class="text-body-2 text-medium-emphasis ml-1">/month</span>
                            </div>
                        </v-card-item>

                        <v-card-text>
                            <div class="commission-hero mb-4 text-center py-4">
                                <div class="text-body-2 text-medium-emphasis">Platform commission</div>
                                <div class="text-h5 font-weight-bold text-primary">{{ commissionSummary(plan) }}</div>
                            </div>
                            <p class="text-body-2 text-medium-emphasis">{{ plan.description }}</p>
                        </v-card-text>

                        <v-card-actions class="pa-4 pt-0">
                            <v-btn
                                block
                                color="primary"
                                variant="tonal"
                                :disabled="isCurrent(plan)"
                                :loading="subscribingId === plan.id"
                                @click="choose(plan)"
                            >
                                {{ isCurrent(plan) ? 'Your plan' : 'Choose this plan' }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useDoctorWorkspaceStore } from '../../stores/doctorWorkspaceStore';

const store = useDoctorWorkspaceStore();
const subscribingId = ref(null);

const currentPlan = ref(null);

onMounted(fetchData);

async function fetchData() {
    await store.fetchSubscription();
    currentPlan.value = store.subscription?.subscription?.plan || null;
}

function isCurrent(plan) {
    return currentPlan.value && currentPlan.value.id === plan.id;
}

function price(plan) {
    return Number(plan.monthly_price || 0).toFixed(2);
}

function commissionSummary(plan) {
    if (!plan) return '—';
    if (plan.commission_type === 2) {
        return `KES ${Number(plan.fixed_commission_amount || 0).toFixed(2)} per booking`;
    }
    return `${Number(plan.default_commission_rate || 0).toFixed(0)}% per booking`;
}

async function choose(plan) {
    subscribingId.value = plan.id;
    try {
        await store.subscribe(plan.id);
        currentPlan.value = store.subscription?.subscription?.plan || null;
    } catch (e) { /* message surfaced via store */ }
    finally { subscribingId.value = null; }
}
</script>

<style scoped>
.plan-card { border-width: 2px !important; transition: transform .15s ease, box-shadow .15s ease; }
.plan-card:hover { transform: translateY(-3px); }
.commission-hero { background: var(--v-theme-background); border-radius: 16px; }
</style>