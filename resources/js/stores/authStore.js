import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authService } from '../services/authService';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('auth_token') || null);

    const isAuthenticated = computed(() => !!token.value);
    const isPatient = computed(() => user.value?.roles?.includes('patient') || false);
    const isDoctor = computed(() => user.value?.roles?.includes('doctor') || false);
    const isFacility = computed(() =>
        user.value?.roles?.some((r) => ['facility-admin', 'facility-staff'].includes(r)) || false
    );
    const isSuperAdmin = computed(() => user.value?.roles?.includes('super-admin') || false);
    const roles = computed(() => user.value?.roles || []);

    async function login(credentials) {
        const data = await authService.login(credentials);
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);
        return data;
    }

    async function register(formData) {
        const data = await authService.register(formData);
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);
        return data;
    }

    async function fetchMe() {
        if (!token.value) return null;
        try {
            user.value = await authService.me();
            return user.value;
        } catch (e) {
            await logout();
            return null;
        }
    }

    async function logout() {
        try { await authService.logout(); } catch (e) { /* ignore */ }
        token.value = null;
        user.value = null;
        localStorage.removeItem('auth_token');
    }

    return { user, token, isAuthenticated, isPatient, isDoctor, isFacility, isSuperAdmin, roles, login, register, fetchMe, logout };
});
