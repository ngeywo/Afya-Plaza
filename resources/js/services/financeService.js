import api from './api';

/**
 * Phase 12: Finance Service
 * API calls for doctor earnings, payouts, and super-admin marketplace finance.
 */
export const financeService = {
    // ─── Doctor Earnings ─────────────────────────────────────────────────────────

    /**
     * GET /api/finance/earnings
     * Returns paginated earnings list + summary for the authenticated doctor.
     */
    async getEarnings(params = {}) {
        const response = await api.get('/finance/earnings', { params });
        return response.data; // { summary, data, meta }
    },

    /**
     * GET /api/finance/earnings/{id}
     * Returns a single earning with full details.
     */
    async getEarningDetail(id) {
        const response = await api.get(`/finance/earnings/${id}`);
        return response.data.data;
    },

    /**
     * POST /api/finance/payout-request
     * Moves available earnings into a payout request.
     */
    async requestPayout() {
        const response = await api.post('/finance/payout-request');
        return response.data; // { message, data }
    },

    // ─── Super-Admin Marketplace Finance ────────────────────────────────────────

    /**
     * GET /api/admin/finance/marketplace
     * Platform-wide financial overview (gross volume, platform revenue, payout status).
     */
    async getMarketplace(params = {}) {
        const response = await api.get('/admin/finance/marketplace', { params });
        return response.data.data;
    },

    /**
     * GET /api/admin/finance/payments
     * Paginated payment list.
     */
    async getPayments(params = {}) {
        const response = await api.get('/admin/finance/payments', { params });
        return response.data; // { data, meta }
    },

    /**
     * GET /api/admin/finance/plans
     * List all active plans with subscription counts.
     */
    async getPlans() {
        const response = await api.get('/admin/finance/plans');
        return response.data.data;
    },

    // ─── M-Pesa Integration ──────────────────────────────────────────────────────

    /**
     * POST /api/payments/initiate
     * Triggers an M-Pesa STK Push to the patient's phone.
     *
     * @param {number} appointmentId
     * @param {object} opts  { method?, provider?, phone? }
     */
    async initiatePayment(appointmentId, opts = {}) {
        const response = await api.post('/payments/initiate', {
            appointment_id: appointmentId,
            ...opts,
        });
        return response.data;
    },

    /**
     * GET /api/v1/mpesa/stk/status/{paymentId}
     * Poll M-Pesa STK push status (fallback for unreachable callback URL).
     */
    async mpesaStkStatus(paymentId) {
        const response = await api.get(`/v1/mpesa/stk/status/${paymentId}`);
        return response.data;
    },

    /**
     * GET /api/payments/{id}
     */
    async getPayment(id) {
        const response = await api.get(`/payments/${id}`);
        return response.data.data;
    },
};
