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
        const response = await api.get('/audit/logs', { params });
        return response.data;
    },

    // Doctor lifecycle (Phase 13 — governance routes)
    async rejectDoctor(id, data) {
        const response = await api.post(`/doctors/${id}/reject`, data);
        return response.data;
    },
    async suspendDoctor(id, data) {
        const response = await api.post(`/doctors/${id}/suspend`, data);
        return response.data;
    },
    async unsuspendDoctor(id, data = {}) {
        const response = await api.post(`/doctors/${id}/unsuspend`, data);
        return response.data;
    },

    // Facility lifecycle (Phase 13 — governance routes)
    async rejectFacility(id, data) {
        const response = await api.post(`/facilities/${id}/reject`, data);
        return response.data;
    },
    async suspendFacility(id, data) {
        const response = await api.post(`/facilities/${id}/suspend`, data);
        return response.data;
    },
    async unsuspendFacility(id, data = {}) {
        const response = await api.post(`/facilities/${id}/unsuspend`, data);
        return response.data;
    },
};
