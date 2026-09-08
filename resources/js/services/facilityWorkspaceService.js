import api from './api';

export const facilityWorkspaceService = {
    async getDashboard() {
        const response = await api.get('/facility/dashboard');
        return response.data.data;
    },
    async getDoctors(params = {}) {
        const response = await api.get('/facility/doctors', { params });
        return response.data.data;
    },
    async getFacilities() {
        const response = await api.get('/facility/facilities');
        return response.data.data;
    },
    async getClinicSessions(params = {}) {
        const response = await api.get('/facility/sessions', { params });
        return response.data.data;
    },
    async createClinicSession(data) {
        const response = await api.post('/sessions', data);
        return response.data.data;
    },
    async confirmSession(id) {
        const response = await api.put(`/facility/sessions/${id}/confirm`);
        return response.data.data;
    },
    async rejectSession(id, reason) {
        const response = await api.put(`/facility/sessions/${id}/reject`, { reason });
        return response.data.data;
    },
    async getAppointments(params = {}) {
        const response = await api.get('/facility/appointments', { params });
        return response.data;
    },
    async getAppointment(id) {
        const response = await api.get(`/facility/appointments/${id}`);
        return response.data.data;
    },
    async getLocations() {
        const response = await api.get('/facility/locations');
        return response.data.data;
    },
    async createLocation(data) {
        const response = await api.post('/facility/locations', data);
        return response.data.data;
    },
    async getStaff() {
        const response = await api.get('/facility/staff');
        return response.data.data;
    },
    async createStaff(data) {
        const response = await api.post('/facility/staff', data);
        return response.data.data;
    },
    async updateStaff(id, data) {
        const response = await api.patch(`/facility/staff/${id}`, data);
        return response.data.data;
    },
    async removeStaff(id) {
        const response = await api.delete(`/facility/staff/${id}`);
        return response.data;
    },
    async getProfile() {
        const response = await api.get('/facility/profile');
        return response.data.data;
    },
    async updateProfile(data) {
        const response = await api.put('/facility/profile', data);
        return response.data.data;
    },
    async getClinicDay(date) {
        const response = await api.get('/facility/clinic-day', { params: { date } });
        return response.data.data;
    },
    // Onboarding (facility creation after sign-up)
    async getOnboardingStatus() {
        const response = await api.get('/facility/onboarding/status');
        return response.data.data;
    },
    async createOnboarding(data) {
        const response = await api.post('/facility/onboarding', data);
        return response.data.data;
    },

    // ── Phase 28 workspace surfaces ─────────────────────────────────────────
    async getOperatingHours() {
        const response = await api.get('/facility/operating-hours');
        return response.data.data;
    },
    async saveOperatingHours(hours) {
        const response = await api.put('/facility/operating-hours', { hours });
        return response.data.data;
    },
    async getSchedules() {
        const response = await api.get('/facility/schedules');
        return response.data.data;
    },
    async getServices() {
        const response = await api.get('/facility/services');
        return response.data.data;
    },
    async createService(data) {
        const response = await api.post('/facility/services', data);
        return response.data.data;
    },
    async updateService(id, data) {
        const response = await api.patch(`/facility/services/${id}`, data);
        return response.data.data;
    },
    async deleteService(id) {
        const response = await api.delete(`/facility/services/${id}`);
        return response.data;
    },
    async getPayments(params = {}) {
        const response = await api.get('/facility/payments', { params });
        return response.data.data;
    },
    async getTransactions() {
        const response = await api.get('/facility/transactions');
        return response.data;
    },
    async getReports(params = {}) {
        const response = await api.get('/facility/reports', { params });
        return response.data.data;
    },
    async exportReport() {
        const response = await api.get('/facility/reports/export', { responseType: 'blob' });
        return response.data;
    },
    async getBilling() {
        const response = await api.get('/facility/billing');
        return response.data.data;
    },
    async getSettings() {
        const response = await api.get('/facility/settings');
        return response.data.data;
    },
    async updateSettings(data) {
        const response = await api.put('/facility/settings', data);
        return response.data.data;
    },
};