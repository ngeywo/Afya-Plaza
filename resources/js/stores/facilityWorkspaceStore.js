import { defineStore } from 'pinia';
import { ref } from 'vue';
import { facilityWorkspaceService } from '../services/facilityWorkspaceService';
import { appointmentService } from '../services/appointmentService';

export const useFacilityWorkspaceStore = defineStore('facilityWorkspace', () => {
    const dashboard = ref(null);
    const doctors = ref([]);
    const sessions = ref([]);
    const appointments = ref([]);
    const appointmentDoctors = ref([]);
    const locations = ref([]);
    const staff = ref([]);
    const profile = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const success = ref(null);
    // Phase 11: Clinic Day Board
    const clinicDay = ref(null);
    const clinicDayLoading = ref(false);

    function clearMessages() { error.value = null; success.value = null; }

    async function fetchDashboard() {
        loading.value = true; error.value = null;
        try { dashboard.value = await facilityWorkspaceService.getDashboard(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load dashboard'; }
        finally { loading.value = false; }
    }

    async function fetchDoctors() {
        loading.value = true; error.value = null;
        try { doctors.value = await facilityWorkspaceService.getDoctors(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load doctors'; }
        finally { loading.value = false; }
    }

    async function fetchSessions(params = {}) {
        loading.value = true; error.value = null;
        try { sessions.value = await facilityWorkspaceService.getClinicSessions(params); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load sessions'; }
        finally { loading.value = false; }
    }

    async function confirmSession(id) {
        clearMessages();
        try {
            const updated = await facilityWorkspaceService.confirmSession(id);
            success.value = 'Clinic session confirmed';
            return updated;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to confirm session';
            throw e;
        }
    }

    async function rejectSession(id, reason) {
        clearMessages();
        try {
            const updated = await facilityWorkspaceService.rejectSession(id, reason);
            success.value = 'Clinic session rejected';
            return updated;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to reject session';
            throw e;
        }
    }

    async function fetchAppointments(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await facilityWorkspaceService.getAppointments(params);
            appointments.value = res.data;
            appointmentDoctors.value = res.doctors || [];
        } catch (e) { error.value = e.response?.data?.error || 'Failed to load appointments'; }
        finally { loading.value = false; }
    }

    async function fetchLocations() {
        loading.value = true; error.value = null;
        try { locations.value = await facilityWorkspaceService.getLocations(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load locations'; }
        finally { loading.value = false; }
    }

    async function createLocation(data) {
        clearMessages();
        try {
            const created = await facilityWorkspaceService.createLocation(data);
            success.value = 'Location created';
            return created;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to create location';
            throw e;
        }
    }

    async function fetchStaff() {
        loading.value = true; error.value = null;
        try { staff.value = await facilityWorkspaceService.getStaff(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load staff'; }
        finally { loading.value = false; }
    }

    async function fetchProfile() {
        loading.value = true; error.value = null;
        try { profile.value = await facilityWorkspaceService.getProfile(); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load profile'; }
        finally { loading.value = false; }
    }

    async function updateProfile(data) {
        clearMessages();
        try {
            const res = await facilityWorkspaceService.updateProfile(data);
            profile.value = res;
            success.value = 'Profile updated';
            return res;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to update profile';
            throw e;
        }
    }

    // ─── Phase 11: Clinic Day Board ─────────────────────────────────────────────

    async function fetchClinicDay(date) {
        clinicDayLoading.value = true; error.value = null;
        try { clinicDay.value = await facilityWorkspaceService.getClinicDay(date); }
        catch (e) { error.value = e.response?.data?.error || 'Failed to load clinic day'; }
        finally { clinicDayLoading.value = false; }
    }

    async function checkInAppointment(id) {
        clearMessages();
        try {
            const res = await appointmentService.checkIn(id);
            if (clinicDay.value) {
                const apt = clinicDay.value.appointments?.find(a => a.id === id);
                if (apt) { apt.status = res.data.status; apt.checked_in_at = res.data.checked_in_at; }
            }
            success.value = 'Patient checked in successfully';
            return res.data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Check-in failed';
            throw e;
        }
    }

    async function markAppointmentNoShow(id) {
        clearMessages();
        try {
            const res = await appointmentService.markNoShow(id);
            if (clinicDay.value) {
                const apt = clinicDay.value.appointments?.find(a => a.id === id);
                if (apt) apt.status = res.data.status;
            }
            success.value = 'Marked as no-show';
            return res.data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to mark no-show';
            throw e;
        }
    }

    async function cancelAppointment(id, reason) {
        clearMessages();
        try {
            const res = await appointmentService.facilityCancel(id, reason);
            if (clinicDay.value) {
                const apt = clinicDay.value.appointments?.find(a => a.id === id);
                if (apt) apt.status = res.data.status;
            }
            success.value = 'Appointment cancelled';
            return res.data;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to cancel appointment';
            throw e;
        }
    }

    return {
        dashboard, doctors, sessions, appointments, appointmentDoctors,
        locations, staff, profile, loading, error, success,
        clinicDay, clinicDayLoading,
        fetchDashboard, fetchDoctors, fetchSessions, confirmSession, rejectSession,
        fetchAppointments, fetchLocations, createLocation, fetchStaff,
        fetchProfile, updateProfile, clearMessages,
        fetchClinicDay, checkInAppointment, markAppointmentNoShow, cancelAppointment,
    };
});
