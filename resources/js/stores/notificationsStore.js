/**
 * Phase 10: Notification store.
 * - Lazily fetches the latest notifications on first open
 * - Polls unread count every 60s so the header bell stays current
 * - Marks notifications read on open
 */
import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import notificationService from '../services/notificationService';
import { useAuthStore } from './authStore';

export const useNotificationsStore = defineStore('notifications', () => {
    const items = ref([]);
    const unreadCount = ref(0);
    const loading = ref(false);
    const error = ref(null);
    const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
    const dropdownOpen = ref(false);
    let pollHandle = null;

    const hasUnread = computed(() => unreadCount.value > 0);

    async function fetchList(params = {}) {
        loading.value = true;
        error.value = null;
        try {
            const resp = await notificationService.list(params);
            items.value = resp.data || [];
            meta.value = resp.meta || meta.value;
            if (resp.meta?.unread_count !== undefined) {
                unreadCount.value = resp.meta.unread_count;
            }
        } catch (e) {
            error.value = e.response?.data?.message || 'Failed to load notifications';
        } finally {
            loading.value = false;
        }
    }

    async function fetchUnreadCount() {
        const auth = useAuthStore();
        if (!auth.isAuthenticated) {
            unreadCount.value = 0;
            return;
        }
        try {
            const r = await notificationService.unreadCount();
            unreadCount.value = r.count || 0;
        } catch (e) {
            // Silent — bell just stops updating
        }
    }

    async function markRead(id) {
        try {
            const r = await notificationService.markRead(id);
            const idx = items.value.findIndex((n) => n.id === id);
            if (idx !== -1) items.value[idx] = r.data;
            if (unreadCount.value > 0) unreadCount.value -= 1;
        } catch (e) {
            error.value = e.response?.data?.message || 'Failed to mark read';
        }
    }

    async function markAllRead() {
        try {
            await notificationService.markAllRead();
            items.value = items.value.map((n) => ({ ...n, is_unread: false, read_at: new Date().toISOString() }));
            unreadCount.value = 0;
        } catch (e) {
            error.value = e.response?.data?.message || 'Failed to mark all read';
        }
    }

    function openDropdown() {
        dropdownOpen.value = true;
        if (items.value.length === 0) {
            fetchList({ per_page: 10 });
        }
    }

    function closeDropdown() {
        dropdownOpen.value = false;
    }

    function startPolling() {
        const auth = useAuthStore();
        if (pollHandle) return;
        fetchUnreadCount();
        pollHandle = setInterval(() => {
            if (auth.isAuthenticated) fetchUnreadCount();
        }, 60000);
    }

    function stopPolling() {
        if (pollHandle) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
    }

    // React to login/logout
    const auth = useAuthStore();
    watch(
        () => auth.isAuthenticated,
        (v) => {
            if (v) {
                fetchUnreadCount();
                startPolling();
            } else {
                items.value = [];
                unreadCount.value = 0;
                stopPolling();
            }
        },
        { immediate: true }
    );

    return {
        items, unreadCount, loading, error, meta, dropdownOpen,
        hasUnread,
        fetchList, fetchUnreadCount, markRead, markAllRead,
        openDropdown, closeDropdown, startPolling, stopPolling,
    };
});
