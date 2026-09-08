import api from './api';

export const adminService = {
    async getDashboard() {
        const response = await api.get('/admin/dashboard');
        return response.data.data;
    },

    async getAdministrators(params = {}) {
        const response = await api.get('/admin/administrators', { params });
        return response.data;
    },

    async getPendingDoctors(params = {}) {
        const response = await api.get('/admin/verifications/doctors', { params });
        return response.data;
    },

    async getPendingFacilities(params = {}) {
        const response = await api.get('/admin/verifications/facilities', { params });
        return response.data;
    },

    async verifyDoctor(id) {
        const response = await api.post(`/admin/doctors/${id}/verify`);
        return response.data;
    },

    async unverifyDoctor(id) {
        const response = await api.post(`/admin/doctors/${id}/unverify`);
        return response.data;
    },

    async verifyFacility(id) {
        const response = await api.post(`/admin/facilities/${id}/verify`);
        return response.data;
    },

    async unverifyFacility(id) {
        const response = await api.post(`/admin/facilities/${id}/unverify`);
        return response.data;
    },

    // Doctor workspace inspection
    async getDoctorDashboard(id) {
        const response = await api.get(`/admin/doctors/${id}/dashboard`);
        return response.data.data;
    },

    async getDoctorSessions(id, params = {}) {
        const response = await api.get(`/admin/doctors/${id}/sessions`, { params });
        return response.data;
    },

    async getDoctorAppointments(id, params = {}) {
        const response = await api.get(`/admin/doctors/${id}/appointments`, { params });
        return response.data;
    },

    async getDoctorProfile(id) {
        const response = await api.get(`/admin/doctors/${id}/profile`);
        return response.data.data;
    },

    // Facility workspace inspection
    async getFacilityDashboard(id) {
        const response = await api.get(`/admin/facilities/${id}/dashboard`);
        return response.data.data;
    },

    async getFacilitySessions(id, params = {}) {
        const response = await api.get(`/admin/facilities/${id}/sessions`, { params });
        return response.data;
    },

    async getFacilityAppointments(id, params = {}) {
        const response = await api.get(`/admin/facilities/${id}/appointments`, { params });
        return response.data;
    },

    async getFacilityProfile(id) {
        const response = await api.get(`/admin/facilities/${id}/profile`);
        return response.data.data;
    },

    // Audit Logs (Phase 13)
    async getAuditLogs(params = {}) {
        const response = await api.get('/admin/audit/logs', { params });
        return response.data;
    },

    // Doctor lifecycle (Phase 13 — governance routes)
    async rejectDoctor(id, data) {
        const response = await api.post(`/admin/doctors/${id}/reject`, data);
        return response.data;
    },
    async suspendDoctor(id, data) {
        const response = await api.post(`/admin/doctors/${id}/suspend`, data);
        return response.data;
    },
    async unsuspendDoctor(id, data = {}) {
        const response = await api.post(`/admin/doctors/${id}/unsuspend`, data);
        return response.data;
    },

    // Facility lifecycle (Phase 13 — governance routes)
    async rejectFacility(id, data) {
        const response = await api.post(`/admin/facilities/${id}/reject`, data);
        return response.data;
    },
    async suspendFacility(id, data) {
        const response = await api.post(`/admin/facilities/${id}/suspend`, data);
        return response.data;
    },
    async unsuspendFacility(id, data = {}) {
        const response = await api.post(`/admin/facilities/${id}/unsuspend`, data);
        return response.data;
    },

    // Platform plans (Phase 24)
    async getPlans(params = {}) {
        const response = await api.get('/admin/plans', { params });
        return response.data;
    },

    async createPlan(data) {
        const response = await api.post('/admin/plans', data);
        return response.data;
    },

    async updatePlan(id, data) {
        const response = await api.put(`/admin/plans/${id}`, data);
        return response.data;
    },

    async setPlanActive(id, active, reason = '') {
        const response = await api.post(`/admin/plans/${id}/status`, { active, reason });
        return response.data;
    },

    async getPlanVersions(id) {
        const response = await api.get(`/admin/plans/${id}/versions`);
        return response.data;
    },

    // Platform subscriptions (Phase 24)
    async getSubscriptions(params = {}) {
        const response = await api.get('/admin/subscriptions', { params });
        return response.data;
    },

    async getSubscription(id) {
        const response = await api.get(`/admin/subscriptions/${id}`);
        return response.data;
    },

    async verifySubscriptionPayment(id, data) {
        const response = await api.post(`/admin/subscriptions/${id}/verify-payment`, data);
        return response.data;
    },

    async adjustSubscription(id, data) {
        const response = await api.post(`/admin/subscriptions/${id}/adjust`, data);
        return response.data;
    },

    // Platform account + workspace context
    async getMe() {
        const response = await api.get('/admin/me');
        return response.data.data;
    },

    async getWorkspace() {
        const response = await api.get('/admin/workspace');
        return response.data.data;
    },

    async switchWorkspace(payload) {
        const response = await api.post('/admin/workspace/switch', payload);
        return response.data.data;
    },

    async exitWorkspace() {
        const response = await api.post('/admin/workspace/exit');
        return response.data.data;
    },

    // Marketplace overview
    async getDoctors(params = {}) {
        const response = await api.get('/admin/marketplace/doctors', { params });
        return response.data;
    },

    async getFacilities(params = {}) {
        const response = await api.get('/admin/marketplace/facilities', { params });
        return response.data;
    },

    // Specialties
    async getSpecialties() {
        const response = await api.get('/admin/specialties');
        return response.data.data;
    },

    async createSpecialty(data) {
        const response = await api.post('/admin/specialties', data);
        return response.data;
    },

    async updateSpecialty(id, data) {
        const response = await api.put(`/admin/specialties/${id}`, data);
        return response.data;
    },

    async deleteSpecialty(id) {
        const response = await api.delete(`/admin/specialties/${id}`);
        return response.data;
    },

    // Marketplace services
    async getServices(params = {}) {
        const response = await api.get('/admin/services', { params });
        return response.data;
    },

    // Doctor-facility relationships + governance
    async getRelationships(params = {}) {
        const response = await api.get('/admin/relationships', { params });
        return response.data;
    },

    async relationshipAction(id, action, data = {}) {
        const response = await api.post(`/admin/relationships/${id}/${action}`, data);
        return response.data;
    },

    // Clinic sessions (platform-wide)
    async getAdminSessions(params = {}) {
        const response = await api.get('/admin/sessions', { params });
        return response.data;
    },

    async cancelAdminSession(id, reason) {
        const response = await api.post(`/admin/sessions/${id}/cancel`, { reason });
        return response.data;
    },

    // Appointments (platform-wide)
    async getAdminAppointments(params = {}) {
        const response = await api.get('/admin/appointments', { params });
        return response.data;
    },

    async cancelAdminAppointment(id, reason) {
        const response = await api.post(`/admin/appointments/${id}/cancel`, { reason });
        return response.data;
    },

    // Reports / analytics / finance surfaces
    async getReports(params = {}) {
        const response = await api.get('/admin/reports', { params });
        return response.data.data;
    },

    async getAnalytics(params = {}) {
        const response = await api.get('/admin/analytics', { params });
        return response.data.data;
    },

    async getTransactions(params = {}) {
        const response = await api.get('/admin/transactions', { params });
        return response.data;
    },

    async getCommissions(params = {}) {
        const response = await api.get('/admin/commissions', { params });
        return response.data.data;
    },

    // Platform settings
    async getSettings() {
        const response = await api.get('/admin/settings');
        return response.data.data;
    },

    async updateSettings(settings, reason = '') {
        const response = await api.put('/admin/settings', { settings, reason });
        return response.data;
    },

    // Phase 23 verification queue
    async getVerificationRequests(params = {}) {
        const response = await api.get('/admin/verification-requests', { params });
        return response.data;
    },

    async reviewVerificationRequest(id, data) {
        const response = await api.post(`/admin/verification-requests/${id}/review`, data);
        return response.data;
    },

    // Account governance
    async getAdminAccounts(params = {}) {
        const response = await api.get('/admin/accounts', { params });
        return response.data;
    },

    async getAccountActivity(id, params = {}) {
        const response = await api.get(`/admin/accounts/${id}/activity`, { params });
        return response.data;
    },

    async accountAction(id, action, data = {}) {
        const response = await api.post(`/admin/accounts/${id}/${action}`, data);
        return response.data;
    },

    // Platform fees
    async getPlatformFees() {
        const response = await api.get('/admin/platform-fees');
        return response.data;
    },

    async getActivePlatformFee() {
        const response = await api.get('/admin/platform-fees/active');
        return response.data;
    },
};
