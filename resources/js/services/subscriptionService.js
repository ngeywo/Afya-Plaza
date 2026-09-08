import api from './api.js';

/**
 * Phase 21/24: Facility subscription service
 * Handles facility subscription management, checkout, and plan operations.
 * Note: API responses are wrapped in { data: ... }, so callers should access
 *       response.data.data (or response.data.subscription, etc).
 */
const subscriptionService = {
  async getSubscription() {
    const response = await api.get('/facility/subscription');
    return response.data.data;
  },

  async getPlans() {
    const response = await api.get('/facility/subscription/plans');
    return response.data.data;
  },

  async checkout({ planSlug, billingCycle = 'monthly' }) {
    const response = await api.post('/facility/subscription/checkout', {
      plan_slug: planSlug,
      billing_cycle: billingCycle,
    });
    return response.data;
  },

  async confirmPayment({ reference }) {
    const response = await api.post('/facility/subscription/payment/confirm', { reference });
    return response.data;
  },

  async paymentFailure({ reason }) {
    const response = await api.post('/facility/subscription/payment/failure', { reason });
    return response.data;
  },

  async upgrade({ planSlug, billingCycle }) {
    const response = await api.post('/facility/subscription/upgrade', {
      plan_slug: planSlug,
      billing_cycle: billingCycle,
    });
    return response.data;
  },

  async downgrade({ planSlug, billingCycle }) {
    const response = await api.post('/facility/subscription/downgrade', {
      plan_slug: planSlug,
      billing_cycle: billingCycle,
    });
    return response.data;
  },

  async cancel({ reason }) {
    const response = await api.post('/facility/subscription/cancel', { reason });
    return response.data;
  },
};

export default subscriptionService;
