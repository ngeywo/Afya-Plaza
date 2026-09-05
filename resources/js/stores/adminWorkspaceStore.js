import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { adminService } from "../services/adminService";

export const useAdminWorkspaceStore = defineStore("adminWorkspace", () => {
    const dashboard = ref(null);
    const administrators = ref([]);
    const pendingDoctors = ref([]);
    const pendingFacilities = ref([]);
    const auditLogs = ref([]);
    const auditMeta = ref({});
    const currentInspection = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const meta = ref({});

    const isInspecting = computed(() => currentInspection.value !== null);
    const inspectionType = computed(() => currentInspection.value?.type ?? null);
    const inspectionId = computed(() => currentInspection.value?.id ?? null);

    async function fetchDashboard() {
        loading.value = true; error.value = null;
        try { dashboard.value = await adminService.getDashboard(); }
        catch (e) { error.value = e.response?.data?.message || "Failed to load dashboard"; }
        finally { loading.value = false; }
    }

    async function fetchAdministrators(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await adminService.getAdministrators(params);
            administrators.value = res.data; meta.value = res.meta;
        } catch (e) { error.value = e.response?.data?.message || "Failed to load administrators"; }
        finally { loading.value = false; }
    }

    async function fetchPendingDoctors(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await adminService.getPendingDoctors(params);
            pendingDoctors.value = res.data; meta.value = res.meta;
        } catch (e) { error.value = e.response?.data?.message || "Failed to load pending doctors"; }
        finally { loading.value = false; }
    }

    async function fetchPendingFacilities(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await adminService.getPendingFacilities(params);
            pendingFacilities.value = res.data; meta.value = res.meta;
        } catch (e) { error.value = e.response?.data?.message || "Failed to load pending facilities"; }
        finally { loading.value = false; }
    }

    async function verifyDoctor(id) {
        await adminService.verifyDoctor(id);
        pendingDoctors.value = pendingDoctors.value.filter(d => d.id !== id);
        if (dashboard.value?.counts) dashboard.value.counts.pending_doctor_verification = Math.max(0, dashboard.value.counts.pending_doctor_verification - 1);
        return true;
    }

    async function rejectDoctor(id, data) {
        await adminService.rejectDoctor(id, data);
        pendingDoctors.value = pendingDoctors.value.filter(d => d.id !== id);
        if (dashboard.value?.counts) dashboard.value.counts.pending_doctor_verification = Math.max(0, dashboard.value.counts.pending_doctor_verification - 1);
        return true;
    }

    async function suspendDoctor(id, data) {
        await adminService.suspendDoctor(id, data);
        return true;
    }

    async function unsuspendDoctor(id, data) {
        await adminService.unsuspendDoctor(id, data);
        return true;
    }

    async function verifyFacility(id) {
        await adminService.verifyFacility(id);
        pendingFacilities.value = pendingFacilities.value.filter(f => f.id !== id);
        if (dashboard.value?.counts) dashboard.value.counts.pending_facility_verification = Math.max(0, dashboard.value.counts.pending_facility_verification - 1);
        return true;
    }

    async function rejectFacility(id, data) {
        await adminService.rejectFacility(id, data);
        pendingFacilities.value = pendingFacilities.value.filter(f => f.id !== id);
        if (dashboard.value?.counts) dashboard.value.counts.pending_facility_verification = Math.max(0, dashboard.value.counts.pending_facility_verification - 1);
        return true;
    }

    async function suspendFacility(id, data) {
        await adminService.suspendFacility(id, data);
        return true;
    }

    async function unsuspendFacility(id, data) {
        await adminService.unsuspendFacility(id, data);
        return true;
    }

    async function fetchAuditLogs(params = {}) {
        loading.value = true; error.value = null;
        try {
            const res = await adminService.getAuditLogs(params);
            auditLogs.value = res.data; auditMeta.value = res.meta;
        } catch (e) { error.value = e.response?.data?.message || "Failed to load audit logs"; }
        finally { loading.value = false; }
    }

    async function inspectDoctor(id) {
        loading.value = true; error.value = null;
        try {
            const data = await adminService.getDoctorDashboard(id);
            currentInspection.value = { type: "doctor", id, data };
            return data;
        } catch (e) { error.value = e.response?.data?.message || "Failed to inspect doctor workspace"; throw e; }
        finally { loading.value = false; }
    }

    async function inspectFacility(id) {
        loading.value = true; error.value = null;
        try {
            const data = await adminService.getFacilityDashboard(id);
            currentInspection.value = { type: "facility", id, data };
            return data;
        } catch (e) { error.value = e.response?.data?.message || "Failed to inspect facility workspace"; throw e; }
        finally { loading.value = false; }
    }

    async function inspectDoctorSessions(id, params) { return await adminService.getDoctorSessions(id, params || {}); }
    async function inspectDoctorAppointments(id, params) { return await adminService.getDoctorAppointments(id, params || {}); }
    async function inspectDoctorProfile(id) { return await adminService.getDoctorProfile(id); }
    async function inspectFacilitySessions(id, params) { return await adminService.getFacilitySessions(id, params || {}); }
    async function inspectFacilityAppointments(id, params) { return await adminService.getFacilityAppointments(id, params || {}); }
    async function inspectFacilityProfile(id) { return await adminService.getFacilityProfile(id); }

    function clearInspection() { currentInspection.value = null; }
    function reset() {
        dashboard.value = null; administrators.value = []; pendingDoctors.value = [];
        pendingFacilities.value = []; auditLogs.value = []; auditMeta.value = {};
        currentInspection.value = null; loading.value = false; error.value = null; meta.value = {};
    }

    return {
        dashboard, administrators, pendingDoctors, pendingFacilities,
        auditLogs, auditMeta,
        currentInspection, loading, error, meta,
        isInspecting, inspectionType, inspectionId,
        fetchDashboard, fetchAdministrators,
        fetchPendingDoctors, fetchPendingFacilities,
        verifyDoctor, rejectDoctor, suspendDoctor, unsuspendDoctor,
        verifyFacility, rejectFacility, suspendFacility, unsuspendFacility,
        fetchAuditLogs,
        inspectDoctor, inspectFacility,
        inspectDoctorSessions, inspectDoctorAppointments, inspectDoctorProfile,
        inspectFacilitySessions, inspectFacilityAppointments, inspectFacilityProfile,
        clearInspection, reset,
    };
});
