/**
 * Phase 10: Follow / unfollow doctors.
 * Caches follow state by doctor id to avoid extra network calls.
 */
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';
import { useAuthStore } from './authStore';

export const useFollowStore = defineStore('follow', () => {
    const isFollowingMap = ref({});      // doctorId => boolean
    const followingDoctors = ref([]);     // for MyDoctorsPage
    const loadingMap = ref({});           // doctorId => boolean (request in flight)
    const loadingList = ref(false);
    const error = ref(null);

    const followingCount = computed(() =>
        Object.values(isFollowingMap.value).filter(Boolean).length
    );

    async function fetchStatus(doctorId) {
        const auth = useAuthStore();
        if (!auth.isAuthenticated) {
            isFollowingMap.value[doctorId] = false;
            return false;
        }
        try {
            const r = await api.get(`/doctors/${doctorId}/follow-status`);
            isFollowingMap.value[doctorId] = !!r.data.following;
            return isFollowingMap.value[doctorId];
        } catch (e) {
            isFollowingMap.value[doctorId] = false;
            return false;
        }
    }

    async function follow(doctorId) {
        const auth = useAuthStore();
        if (!auth.isAuthenticated) {
            throw new Error('You must be logged in to follow a doctor.');
        }
        if (isFollowingMap.value[doctorId]) return true;
        loadingMap.value[doctorId] = true;
        try {
            await api.post(`/doctors/${doctorId}/follow`);
            isFollowingMap.value[doctorId] = true;
            return true;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to follow';
            throw e;
        } finally {
            loadingMap.value[doctorId] = false;
        }
    }

    async function unfollow(doctorId) {
        const auth = useAuthStore();
        if (!auth.isAuthenticated) return false;
        loadingMap.value[doctorId] = true;
        try {
            await api.delete(`/doctors/${doctorId}/follow`);
            isFollowingMap.value[doctorId] = false;
            return true;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to unfollow';
            throw e;
        } finally {
            loadingMap.value[doctorId] = false;
        }
    }

    async function toggle(doctorId) {
        if (isFollowingMap.value[doctorId]) {
            await unfollow(doctorId);
        } else {
            await follow(doctorId);
        }
    }

    async function fetchMyDoctors() {
        loadingList.value = true;
        try {
            const r = await api.get('/my/doctors');
            followingDoctors.value = r.data?.data || [];
            // sync follow flags
            for (const d of followingDoctors.value) {
                isFollowingMap.value[d.id] = true;
            }
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to load following list';
        } finally {
            loadingList.value = false;
        }
    }

    function isFollowing(doctorId) {
        return !!isFollowingMap.value[doctorId];
    }

    function isLoading(doctorId) {
        return !!loadingMap.value[doctorId];
    }

    return {
        isFollowingMap, followingDoctors, loadingMap, loadingList, error,
        followingCount,
        fetchStatus, follow, unfollow, toggle, fetchMyDoctors,
        isFollowing, isLoading,
    };
});
