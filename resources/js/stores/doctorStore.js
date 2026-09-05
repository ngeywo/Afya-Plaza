import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { doctorService, sessionSearchService } from '../services/doctorService';

export const useDoctorStore = defineStore('doctor', () => {
    const doctors = ref([]);
    const currentDoctor = ref(null);
    const loading = ref(false);
    const meta = ref({ total: 0, date: null });

    // Phase 3: Session-driven search results (clinic sessions with doctor+facility info)
    const sessions = ref([]);
    const sessionMeta = ref({ total: 0, date: null, day: null });

    /**
     * Session-driven search.
     * Uses /sessions/search — only doctors with actual confirmed sessions on date appear.
     * Section 3: Doctor-Facility ≠ Doctor's actual presence on a date.
     */
    async function sessionSearch(params = {}) {
        loading.value = true;
        try {
            const response = await sessionSearchService.search(params);
            sessions.value = response.data.data;
            sessionMeta.value = response.data.meta;
        } finally {
            loading.value = false;
        }
    }

    async function search(params = {}) {
        loading.value = true;
        try {
            const response = await doctorService.search(params);
            doctors.value = response.data.data;
            meta.value = response.data.meta;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Phase 8: Load doctor profile with session context.
     * Uses GET /api/doctors/{slug}/profile — enriched with
     * upcoming_sessions, today_clinics, next_clinic.
     */
    async function find(slug) {
        loading.value = true;
        try {
            const response = await doctorService.profile(slug);
            currentDoctor.value = response.data.data;
        } finally {
            loading.value = false;
        }
    }

    function clearCurrent() {
        currentDoctor.value = null;
    }

    function clearSessions() {
        sessions.value = [];
        sessionMeta.value = { total: 0, date: null, day: null };
    }

    return {
        doctors, currentDoctor, loading, meta,
        sessions, sessionMeta,
        search, sessionSearch, find, clearCurrent, clearSessions,
    };
});
