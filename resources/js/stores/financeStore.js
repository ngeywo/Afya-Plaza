import { defineStore } from 'pinia';
import { ref } from 'vue';
import { financeService } from '../services/financeService';

export const useFinanceStore = defineStore('finance', () => {
    // ─── Doctor Earnings State ──────────────────────────────────────────────────
    const summary = ref(null);
    const earnings = ref([]);
    const earningsMeta = ref({});
    const loading = ref(false);
    const error = ref(null);

    // ─── Actions ────────────────────────────────────────────────────────────────

    async function fetchEarnings(params = {}) {
        loading.value = true;
        error.value = null;
        try {
            const result = await financeService.getEarnings(params);
            summary.value = result.summary;
            earnings.value = result.data;
            earningsMeta.value = result.meta;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to load earnings';
        } finally {
            loading.value = false;
        }
    }

    async function requestPayout() {
        loading.value = true;
        error.value = null;
        try {
            const result = await financeService.requestPayout();
            // Refresh earnings after payout request
            await fetchEarnings();
            return result.data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to request payout';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    function clearError() {
        error.value = null;
    }

    return {
        summary, earnings, earningsMeta, loading, error,
        fetchEarnings, requestPayout, clearError,
    };
});
