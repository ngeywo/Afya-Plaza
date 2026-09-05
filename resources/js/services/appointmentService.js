import api from './api';

export const appointmentService = {
    async list() {
        const response = await api.get('/appointments');
        return response.data;
    },

    async get(id) {
        const response = await api.get(`/appointments/${id}`);
        return response.data;
    },

    async create(data) {
        const response = await api.post('/appointments', data);
        return response.data;
    },

    async cancel(id, cancellationReason) {
        const response = await api.delete(`/appointments/${id}`, {
            data: { cancellation_reason: cancellationReason },
        });
        return response.data;
    },

    async checkIn(appointmentId) {
        const response = await api.post(`/appointments/${appointmentId}/check-in`);
        return response.data;
    },

    async startConsultation(appointmentId) {
        const response = await api.post(`/appointments/${appointmentId}/start`);
        return response.data;
    },

    async completeConsultation(appointmentId) {
        const response = await api.post(`/appointments/${appointmentId}/complete`);
        return response.data;
    },

    async markNoShow(appointmentId) {
        const response = await api.post(`/appointments/${appointmentId}/no-show`);
        return response.data;
    },

    async facilityCancel(appointmentId, cancellationReason) {
        const response = await api.post(`/appointments/${appointmentId}/facility-cancel`, { cancellation_reason: cancellationReason });
        return response.data;
    },

    async reschedule(appointmentId, data) {
        const response = await api.patch(`/appointments/${appointmentId}`, data);
        return response.data;
    },

    async sessionSlots(sessionId) {
        const response = await api.get(`/sessions/${sessionId}/slots`);
        return response.data;
    },
};
