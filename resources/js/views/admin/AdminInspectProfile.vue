<template>
    <div>
        <div v-if="store.isInspecting" class="mb-4">
            <div class="d-flex align-center mb-1">
                <v-icon icon="mdi-shield-account" color="red-darken-2" size="20" class="mr-2"></v-icon>
                <span class="text-caption text-medium-emphasis">Super Admin — Inspecting</span>
            </div>
            <h1 class="text-h5 font-weight-bold">{{ workspaceName }}</h1>
        </div>
        <v-alert v-if="!store.isInspecting" type="warning" variant="tonal" class="mb-4">No workspace selected. <v-btn to="/admin/dashboard" variant="text" size="small">Go to Dashboard</v-btn></v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

        <!-- Governance Actions -->
        <v-card v-if="profile" variant="outlined" class="mb-4">
            <v-card-title class="text-subtitle-2">Governance Actions</v-card-title>
            <v-divider></v-divider>
            <v-card-text>
                <div class="d-flex flex-wrap gap-2 align-center">
                    <v-chip :color="statusColor" variant="elevated" size="small">
                        <v-icon start size="12">mdi-circle</v-icon>
                        {{ profile.verification_status_label || profile.verification_status || "Unknown" }}
                    </v-chip>
                    <v-chip v-if="profile.is_trustworthy" color="success" variant="tonal" size="small"><v-icon start size="12">mdi-shield-check</v-icon>Trustworthy</v-chip>
                    <v-chip v-if="!profile.is_trustworthy" color="error" variant="tonal" size="small"><v-icon start size="12">mdi-shield-alert</v-icon>Not Trustworthy</v-chip>
                    <v-chip v-if="profile.is_bookable" color="primary" variant="tonal" size="small"><v-icon start size="12">mdi-calendar-check</v-icon>Bookable</v-chip>
                    <v-chip v-if="!profile.is_bookable && profile.is_trustworthy" color="grey" variant="tonal" size="small"><v-icon start size="12">mdi-calendar-remove</v-icon>Not Bookable</v-chip>
                    <v-chip v-if="profile.is_suspended" color="warning" variant="elevated" size="small"><v-icon start size="12">mdi-account-cancel</v-icon>Suspended</v-chip>
                </div>
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <v-btn v-if="canVerify" size="small" color="success" variant="tonal" :loading="actionLoading" @click="onVerify">Verify</v-btn>
                    <v-btn v-if="canReject" size="small" color="error" variant="outlined" :loading="actionLoading" @click="openRejectDialog = true">Reject</v-btn>
                    <v-btn v-if="canSuspend" size="small" color="warning" variant="outlined" :loading="actionLoading" @click="openSuspendDialog = true">Suspend</v-btn>
                    <v-btn v-if="canUnsuspend" size="small" color="info" variant="tonal" :loading="actionLoading" @click="onUnsuspend">Unsuspend</v-btn>
                    <v-btn v-if="canUnverify" size="small" color="grey" variant="outlined" :loading="actionLoading" @click="onUnverify">Reset Verification</v-btn>
                </div>
            </v-card-text>
        </v-card>

        <!-- Reject Dialog -->
        <v-dialog v-model="openRejectDialog" max-width="500" persistent>
            <v-card>
                <v-card-title class="text-h6">Reject {{ inspectionLabel }}</v-card-title>
                <v-card-text>
                    <v-form ref="rejectForm">
                        <v-text-field v-model="rejectReason" label="Reason *" variant="outlined" density="compact" :rules="[v => !!v || 'Required']" counter="255" maxlength="255"></v-text-field>
                        <v-textarea v-model="rejectNotes" label="Additional notes" variant="outlined" density="compact" rows="2"></v-textarea>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="openRejectDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="actionLoading" @click="onRejectConfirm">Reject</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Suspend Dialog -->
        <v-dialog v-model="openSuspendDialog" max-width="500" persistent>
            <v-card>
                <v-card-title class="text-h6">Suspend {{ inspectionLabel }}</v-card-title>
                <v-card-text>
                    <v-form ref="suspendForm">
                        <v-text-field v-model="suspendReason" label="Reason *" variant="outlined" density="compact" :rules="[v => !!v || 'Required']" counter="255" maxlength="255"></v-text-field>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="openSuspendDialog = false">Cancel</v-btn>
                    <v-btn color="warning" :loading="actionLoading" @click="onSuspendConfirm">Suspend</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-card v-if="profile" variant="outlined">
            <v-card-title class="text-subtitle-1 font-weight-bold">{{ store.inspectionType === "doctor" ? "Doctor Profile" : "Facility Profile" }}</v-card-title>
            <v-divider></v-divider>
            <v-card-text>
                <v-row>
                    <v-col cols="12" md="6" v-for="(value, key) in profileFields" :key="key">
                        <div class="text-caption text-medium-emphasis text-uppercase" style="letter-spacing:0.5px;">{{ key }}</div>
                        <div class="text-body-1 font-weight-medium">{{ value }}</div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>
    </div>
</template>
<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
import { adminService } from "../../services/adminService";

const store = useAdminWorkspaceStore();
const profile = ref(null);
const loading = ref(false);
const error = ref(null);
const actionLoading = ref(false);
const openRejectDialog = ref(false);
const openSuspendDialog = ref(false);
const rejectReason = ref("");
const rejectNotes = ref("");
const suspendReason = ref("");
const rejectForm = ref(null);
const suspendForm = ref(null);

const workspaceName = computed(() => {
    if (!store.currentInspection || !store.currentInspection.data) return "...";
    return store.inspectionType === "doctor"
        ? (store.currentInspection.data.doctor && store.currentInspection.data.doctor.name) || "..."
        : (store.currentInspection.data.facility && store.currentInspection.data.facility.name) || "...";
});

const inspectionLabel = computed(() => store.inspectionType === "doctor" ? "Doctor" : "Facility");

const statusColor = computed(() => {
    const s = profile.value?.verification_status;
    return s === "verified" ? "success"
        : s === "pending" ? "warning"
        : s === "rejected" ? "error"
        : s === "suspended" ? "warning"
        : "grey";
});

const canVerify = computed(() => ["pending", "rejected"].includes(profile.value?.verification_status));
const canReject = computed(() => ["pending", "verified"].includes(profile.value?.verification_status));
const canSuspend = computed(() => profile.value?.verification_status === "verified" && !profile.value?.is_suspended);
const canUnsuspend = computed(() => profile.value?.is_suspended);
const canUnverify = computed(() => ["verified", "suspended", "rejected"].includes(profile.value?.verification_status));

async function load() {
    if (!store.isInspecting || !store.inspectionId) return;
    loading.value = true; error.value = null;
    try {
        const res = store.inspectionType === "doctor"
            ? await store.inspectDoctorProfile(store.inspectionId)
            : await store.inspectFacilityProfile(store.inspectionId);
        profile.value = res.data || res;
    } catch (e) { error.value = e.response?.data?.message || "Failed to load profile"; }
    finally { loading.value = false; }
}

onMounted(load);
watch(() => store.inspectionId, load);

async function refresh() { await load(); }

async function onVerify() {
    actionLoading.value = true;
    try {
        if (store.inspectionType === "doctor") await adminService.verifyDoctor(store.inspectionId);
        else await adminService.verifyFacility(store.inspectionId);
        await refresh();
    } catch (e) { error.value = e.response?.data?.message || "Failed to verify"; }
    finally { actionLoading.value = false; }
}

async function onRejectConfirm() {
    const form = rejectForm.value;
    const { valid } = await form.validate();
    if (!valid) return;
    actionLoading.value = true;
    try {
        if (store.inspectionType === "doctor") await adminService.rejectDoctor(store.inspectionId, { reason: rejectReason.value, notes: rejectNotes.value });
        else await adminService.rejectFacility(store.inspectionId, { reason: rejectReason.value, notes: rejectNotes.value });
        openRejectDialog.value = false;
        rejectReason.value = ""; rejectNotes.value = "";
        await refresh();
    } catch (e) { error.value = e.response?.data?.message || "Failed to reject"; }
    finally { actionLoading.value = false; }
}

async function onSuspendConfirm() {
    const form = suspendForm.value;
    const { valid } = await form.validate();
    if (!valid) return;
    actionLoading.value = true;
    try {
        if (store.inspectionType === "doctor") await adminService.suspendDoctor(store.inspectionId, { reason: suspendReason.value });
        else await adminService.suspendFacility(store.inspectionId, { reason: suspendReason.value });
        openSuspendDialog.value = false;
        suspendReason.value = "";
        await refresh();
    } catch (e) { error.value = e.response?.data?.message || "Failed to suspend"; }
    finally { actionLoading.value = false; }
}

async function onUnsuspend() {
    actionLoading.value = true;
    try {
        if (store.inspectionType === "doctor") await adminService.unsuspendDoctor(store.inspectionId);
        else await adminService.unsuspendFacility(store.inspectionId);
        await refresh();
    } catch (e) { error.value = e.response?.data?.message || "Failed to unsuspend"; }
    finally { actionLoading.value = false; }
}

async function onUnverify() {
    actionLoading.value = true;
    try {
        if (store.inspectionType === "doctor") await adminService.unverifyDoctor(store.inspectionId);
        else await adminService.unverifyFacility(store.inspectionId);
        await refresh();
    } catch (e) { error.value = e.response?.data?.message || "Failed to reset verification"; }
    finally { actionLoading.value = false; }
}

const profileFields = computed(() => {
    if (!profile.value) return {};
    const p = profile.value;
    if (store.inspectionType === "doctor") {
        return {
            "Display Name": p.display_name || p.name || "",
            "Email": p.email || "",
            "Phone": p.phone || "",
            "Qualifications": p.qualifications || "",
            "Specialties": Array.isArray(p.specialties) ? p.specialties.map(s => s.name).join(", ") : "",
            "Experience": p.years_of_experience ? p.years_of_experience + " years" : "",
            "Consultation Fee": p.consultation_fee ? "KES " + p.consultation_fee : "",
            "Verified": p.is_verified ? "Yes" : "No",
            "Active": p.is_active ? "Yes" : "No",
            "Rejection Reason": p.rejection_reason || "—",
            "Suspension Reason": p.suspension_reason || "—",
        };
    }
    return {
        "Name": p.name || "",
        "Email": p.email || "",
        "Phone": p.phone || "",
        "City": p.city || "",
        "Address": p.address || "",
        "Verified": p.is_verified ? "Yes" : "No",
        "Active": p.is_active ? "Yes" : "No",
        "Rejection Reason": p.rejection_reason || "—",
        "Suspension Reason": p.suspension_reason || "—",
    };
});
</script>