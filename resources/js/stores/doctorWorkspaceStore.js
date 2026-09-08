import { defineStore } from 'pinia';
import { ref } from 'vue';
import { doctorWorkspaceService } from '../services/doctorWorkspaceService';
import { appointmentService } from '../services/appointmentService';

export const useDoctorWorkspaceStore = defineStore('doctorWorkspace', () => {
    const dashboard = ref(null);
    const clinics = ref([]);
    const schedule = ref(null);
    const appointments = ref([]);
    const profile = ref(null);
    const facilities = ref([]);
    const clinicDay = ref(null);
    const subscription = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const success = ref(null);

    function clearMessages() { error.value = null; success.value = null; }

    async function fetchDashboard() {
        loading.value = true; error.value = null;
        try { dashboard.value = await doctorWorkspaceService.getDashboard(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load dashboard'; }
        finally { loading.value = false; }
    }

    async function fetchClinics(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await doctorWorkspaceService.getClinics(params);
            clinics.value = res.data;
        } catch (e) { error.value = e.response?.data?.error || 'Failed to load clinics'; }
        finally { loading.value = false; }
    }

    async function fetchSchedule() {
        loading.value = true; error.value = null;
        try { schedule.value = await doctorWorkspaceService.getSchedule(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load schedule'; }
        finally { loading.value = false; }
    }

    async function fetchAppointments(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await doctorWorkspaceService.getAppointments(params);
            appointments.value = res.data;
        } catch (e) { error.value = e.response?.data?.error || 'Failed to load appointments'; }
        finally { loading.value = false; }
    }

    async function fetchProfile() {
        loading.value = true; error.value = null;
        try { profile.value = await doctorWorkspaceService.getProfile(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load profile'; }
        finally { loading.value = false; }
    }

    async function updateProfile(data) {
        const res = await doctorWorkspaceService.updateProfile(data);
        if (profile.value) profile.value = { ...profile.value, ...res };
        return res;
    }

    async function fetchFacilities() {
        loading.value = true; error.value = null;
        try { facilities.value = await doctorWorkspaceService.getFacilities(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load facilities'; }
        finally { loading.value = false; }
    }

    // ─── Phase 11: Clinic Day Board ─────────────────────────────────────────────

    async function fetchClinicDay(date) {
        loading.value = true; error.value = null;
        try { clinicDay.value = await doctorWorkspaceService.getClinicDay(date); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load clinic day'; }
        finally { loading.value = false; }
    }

    async function startConsultationAppointment(id) {
        clearMessages();
        try {
            const res = await appointmentService.startConsultation(id);
            if (clinicDay.value) {
                const apt = clinicDay.value.appointments?.find(a => a.id === id);
                if (apt) { apt.status = res.data.status; apt.consultation_started_at = res.data.consultation_started_at; }
            }
            success.value = 'Consultation started';
            return res.data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to start consultation';
            throw e;
        }
    }

    async function completeConsultationAppointment(id) {
        clearMessages();
        try {
            const res = await appointmentService.completeConsultation(id);
            if (clinicDay.value) {
                const apt = clinicDay.value.appointments?.find(a => a.id === id);
                if (apt) { apt.status = res.data.status; apt.completed_at = res.data.completed_at; }
            }
            success.value = 'Consultation completed';
            return res.data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to complete consultation';
            throw e;
        }
    }

    // ─── Onboarding & session management ────────────────────────────────────────

    async function loadOnboardingStatus() {
        return await doctorWorkspaceService.getOnboardingStatus();
    }

    async function completeOnboarding(data) {
        clearMessages();
        try {
            const res = await doctorWorkspaceService.createOnboarding(data);
            if (profile.value) profile.value = { ...profile.value, ...res };
            success.value = res?.message || 'Profile created';
            return res;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to create profile';
            throw e;
        }
    }

    async function createSession(data) {
        clearMessages();
        try {
            const res = await doctorWorkspaceService.createSession(data);
            success.value = 'Clinic session created';
            return res;
        } catch (e) {
            error.value = e.response?.data?.error || e.response?.data?.message || 'Failed to create session';
            throw e;
        }
    }

    async function confirmSession(id) {
        clearMessages();
        try {
            const res = await doctorWorkspaceService.confirmSession(id);
            success.value = 'Session confirmed';
            return res;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to confirm session';
            throw e;
        }
    }

    async function cancelSession(id, reason) {
        clearMessages();
        try {
            const res = await doctorWorkspaceService.cancelSession(id, reason);
            success.value = 'Session cancelled';
            return res;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to cancel session';
            throw e;
        }
    }

    // ─── Plan subscription ──────────────────────────────────────────────────────

    async function fetchSubscription() {
        loading.value = true; error.value = null;
        try { subscription.value = await doctorWorkspaceService.getSubscription(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load subscription'; }
        finally { loading.value = false; }
    }

    async function subscribe(planId) {
        clearMessages();
        try {
            const res = await doctorWorkspaceService.subscribe(planId);
            await fetchSubscription();
            success.value = res?.message || 'Subscription updated';
            return res;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to update subscription';
            throw e;
        }
    }

    return {
        dashboard, clinics, schedule, appointments, profile, facilities,
        clinicDay, subscription, loading, error, success,
        fetchDashboard, fetchClinics, fetchSchedule, fetchAppointments,
        fetchProfile, updateProfile, fetchFacilities,
        fetchClinicDay, startConsultationAppointment, completeConsultationAppointment,
        loadOnboardingStatus, completeOnboarding,
        createSession, confirmSession, cancelSession,
        fetchSubscription, subscribe,
        clearMessages,
    };
});