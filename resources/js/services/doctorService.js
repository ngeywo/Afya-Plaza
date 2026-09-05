import api from './api';

export const doctorService = {
    /**
     * Phase 8: Patient discovery search — session-driven, paginated.
     * Supports: q, specialty_id, city, county_id, facility_id, date, page, per_page
     */
    search(params = {}) {
        return api.get('/doctors/search', { params });
    },

    /**
     * Phase 8: Public doctor profile with session context.
     * Returns doctor + today_clinics + next_clinic + upcoming_sessions.
     */
    profile(slug) {
        return api.get(`/doctors/${slug}/profile`);
    },

    /**
     * Doctor sessions for date range.
     */
    sessions(slug, params = {}) {
        return api.get(`/doctors/${slug}/sessions`, { params });
    },

    /**
     * Legacy: session-driven doctor listing.
     */
    list(params = {}) {
        return api.get('/doctors', { params });
    },

    find(slug) {
        return api.get(`/doctors/${slug}`);
    },
    availability(id, date) {
        return api.get(`/doctors/${id}/availability`, { params: { date } });
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

export const countyService = {
    list() {
        return api.get('/counties');
    },
};

/**
 * Phase 3: Session-driven session search (by facility/location).
 */
export const sessionSearchService = {
    search(params = {}) {
        return api.get('/sessions/search', { params });
    },
    show(id) {
        return api.get(`/sessions/${id}`);
    },
    slots(id) {
        return api.get(`/sessions/${id}/slots`);
    },
};
