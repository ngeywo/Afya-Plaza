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
};