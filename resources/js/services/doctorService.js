import api from './api';

export const doctorService = {
    search(params = {}) {
        return api.get('/doctors', { params });
    },
    find(slug) {
        return api.get(`/doctors/${slug}`);
    },
    sessions(id, params = {}) {
        return api.get(`/doctors/${id}/sessions`, { params });
    },
};

export const specialtyService = {
    list() {
        return api.get('/specialties');
    },
};

export const facilityService = {
    list() {
        return api.get('/facilities');
    },
    find(slug) {
        return api.get(`/facilities/${slug}`);
    },
};
