import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import subscriptionService from '../services/subscriptionService.js';

export const useSubscriptionStore = defineStore('subscription', () => {
  const subscription = ref(null);
  const plan = ref(null);
  const usage = ref(null);
  const features = ref(null);
  const plans = ref([]);
  const loading = ref(false);
  const error = ref(null);
  const restricted = ref(false);
  const restrictions = ref({});
  const billingWindow = ref({ from: null, to: null });

  const hasSubscription = computed(() => subscription.value !== null);
  const isActive = computed(() => {
    if (!subscription.value) return false;
    return ['active', 'trial'].includes(subscription.value.status);
  });
  const isTrial = computed(() => subscription.value?.status === 'trial');
  const isRestricted = computed(() => restricted.value);
  const statusColor = computed(() => {
    if (!subscription.value) return 'grey';
    const m = { pending_payment: 'warning', trial: 'info', active: 'success',
      past_due: 'error', grace_period: 'warning', suspended: 'error',
      cancelled: 'grey', expired: 'grey' };
    return m[subscription.value.status] || 'grey';
  });
  const statusLabel = computed(() => {
    if (!subscription.value) return 'No Subscription';
    const m = { pending_payment: 'Pending Payment', trial: 'Trial', active: 'Active',
      past_due: 'Past Due', grace_period: 'Grace Period', suspended: 'Suspended',
      cancelled: 'Cancelled', expired: 'Expired' };
    return m[subscription.value.status] || subscription.value.status;
  });
  const doctorUsage = computed(() => usage.value?.doctors || { used: 0, max: null, percent: 0, status: 'ok' });
  const staffUsage = computed(() => usage.value?.staff || { used: 0, max: null, percent: 0, status: 'ok' });
  const locationUsage = computed(() => usage.value?.locations || { used: 0, max: null, percent: 0, status: 'ok' });
  const availablePlans = computed(() => plans.value.filter(p => p.slug !== plan.value?.slug));
  const currentPlanName = computed(() => plan.value?.name || 'No Plan');

  async function fetchSubscription() {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.getSubscription();
      subscription.value = data.subscription;
      plan.value = data.plan;
      usage.value = data.usage;
      features.value = data.features;
      restricted.value = data.restricted;
      restrictions.value = data.restrictions || {};
      billingWindow.value = data.billing_window || { from: null, to: null };
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load subscription';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function fetchPlans() {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.getPlans();
      plans.value = data.plans || [];
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load plans';
      throw err;
    } finally {
      loading.value = false;
    }
  }
  async function checkout(planSlug, billingCycle = 'monthly') {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.checkout({ planSlug, billingCycle });
      await fetchSubscription();
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Checkout failed';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function confirmPayment(reference) {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.confirmPayment({ reference });
      await fetchSubscription();
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Payment confirmation failed';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function upgrade(planSlug, billingCycle) {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.upgrade({ planSlug, billingCycle });
      await fetchSubscription();
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Upgrade failed';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function downgrade(planSlug, billingCycle) {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.downgrade({ planSlug, billingCycle });
      await fetchSubscription();
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Downgrade failed';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function cancel(reason) {
    loading.value = true;
    error.value = null;
    try {
      const data = await subscriptionService.cancel({ reason });
      await fetchSubscription();
      return data;
    } catch (err) {
      error.value = err.response?.data?.error || 'Cancellation failed';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  function clearError() { error.value = null; }
  function $reset() {
    subscription.value = null; plan.value = null; usage.value = null;
    features.value = null; plans.value = []; loading.value = false;
    error.value = null; restricted.value = false; restrictions.value = {};
    billingWindow.value = { from: null, to: null };
  }

  return {
    subscription, plan, usage, features, plans, loading, error,
    restricted, restrictions, billingWindow,
    hasSubscription, isActive, isTrial, isRestricted,
    statusColor, statusLabel, doctorUsage, staffUsage, locationUsage,
    availablePlans, currentPlanName,
    fetchSubscription, fetchPlans, checkout, confirmPayment,
    upgrade, downgrade, cancel, clearError, $reset,
  };
});
