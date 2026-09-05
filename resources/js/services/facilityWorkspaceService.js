import api from './api';

export const facilityWorkspaceService = {
    async getDashboard() {
        const response = await api.get('/facility/dashboard');
        return response.data.data;
    },
    async getDoctors() {
        const response = await api.get('/facility/doctors');
        return response.data.data;
    },
    async getClinicSessions(params = {}) {
        const response = await api.get('/facility/clinic-sessions', { params });
        return response.data.data;
    },
    async confirmSession(id) {
        const response = await api.put(`/facility/clinic-sessions/${id}/confirm`);
        return response.data.data;
    },
    async rejectSession(id, reason) {
        const response = await api.put(`/facility/clinic-sessions/${id}/reject`, { reason });
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
};