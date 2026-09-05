import api from './api';

export const appointmentService = {
    async list() {
        const response = await api.get('/appointments');
        return response.data;
    },

    async create(data) {
        const response = await api.post('/appointments', data);
        return response.data;
    },

    async cancel(id) {
        const response = await api.delete(`/appointments/${id}`);
        return response.data;
    },

    async sessionSlots(sessionId) {
        const response = await api.get(`/sessions/${sessionId}/slots`);
        return response.data;
    },
};
