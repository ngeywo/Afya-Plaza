import api from './api';

export const doctorWorkspaceService = {
    async getDashboard() {
        const response = await api.get('/doctor/dashboard');
        return response.data.data;
    },
    async getClinics(params = {}) {
        const response = await api.get('/doctor/clinics', { params });
        return response.data;
    },
    async getSchedule() {
        const response = await api.get('/doctor/schedule');
        return response.data.data;
    },
    async getAppointments(params = {}) {
        const response = await api.get('/doctor/appointments', { params });
        return response.data;
    },
    async getAppointment(id) {
        const response = await api.get(`/doctor/appointments/${id}`);
        return response.data.data;
    },
    async getProfile() {
        const response = await api.get('/doctor/profile');
        return response.data.data;
    },
    async updateProfile(data) {
        const response = await api.put('/doctor/profile', data);
        return response.data.data;
    },
    async getFacilities() {
        const response = await api.get('/doctor/facilities');
        return response.data.data;
    },
    // Phase 11: Doctor Clinic Day Board
    async getClinicDay(date) {
        const response = await api.get('/doctor/clinic-day', { params: { date } });
        return response.data.data;
    },
    // Onboarding (profile creation after sign-up)
    async getOnboardingStatus() {
        const response = await api.get('/doctor/onboarding/status');
        return response.data.data;
    },
    async createOnboarding(data) {
        const response = await api.post('/doctor/onboarding', data);
        return response.data.data;
    },
    // Clinic session management
    async createSession(data) {
        const response = await api.post('/sessions', data);
        return response.data.data;
    },
    async confirmSession(id) {
        const response = await api.post(`/sessions/${id}/confirm`);
        return response.data.data;
    },
    async cancelSession(id, reason) {
        const response = await api.post(`/sessions/${id}/cancel`, { reason });
        return response.data.data;
    },
    // Plan subscription
    async getSubscription() {
        const response = await api.get('/doctor/subscription');
        return response.data.data;
    },
    async subscribe(planId) {
        const response = await api.post('/doctor/subscription', { plan_id: planId });
        return response.data;
    },
};