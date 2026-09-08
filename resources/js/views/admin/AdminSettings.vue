<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = null">{{ message }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <h1 class="text-h5 font-weight-bold mb-1">Platform Settings</h1>
        <p class="text-body-2 text-medium-emphasis mb-4">Global configuration. Changes are audited with the reason you provide.</p>
        <v-form ref="settingsForm">
            <v-card variant="outlined" class="mb-4">
                <v-card-title class="text-subtitle-1 font-weight-bold">General</v-card-title>
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="settings.platform_name" label="Platform name" variant="outlined" density="compact"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="settings.support_email" label="Support email" variant="outlined" density="compact" :rules="[v => !v || /.+@.+\..+/.test(v) || 'Valid email required']"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="settings.default_commission_rate" label="Default commission rate (%)" type="number" variant="outlined" density="compact"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="settings.default_currency" label="Default currency" variant="outlined" density="compact"></v-text-field>
                        </v-col>
                    </v-row>
                    <v-row class="mt-1">
                        <v-col cols="12" md="4">
                            <v-switch v-model="settings.registration_open" label="Open registration" color="success" hide-details inset></v-switch>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-switch v-model="settings.booking_enabled" label="Booking enabled" color="success" hide-details inset></v-switch>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-switch v-model="settings.maintenance_mode" label="Maintenance mode" color="error" hide-details inset></v-switch>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
            <v-card variant="outlined" class="mb-4">
                <v-card-title class="text-subtitle-1 font-weight-bold">Save changes</v-card-title>
                <v-card-text>
                    <v-textarea v-model="reason" label="Reason for these changes *" variant="outlined" density="compact" rows="2" :rules="[v => !!v || 'A reason is required for an audit trail']" counter="255" maxlength="255"></v-textarea>
                    <v-btn color="primary" :loading="saving" @click="save">Save settings</v-btn>
                </v-card-text>
            </v-card>
        </v-form>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { adminService } from "../../services/adminService";

const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const message = ref(null);
const settingsForm = ref(null);
const reason = ref("");
const settings = ref({
    platform_name: "",
    support_email: "",
    default_currency: "KES",
    default_commission_rate: "",
    registration_open: false,
    booking_enabled: false,
    maintenance_mode: false,
});

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const map = await adminService.getSettings();
        settings.value = { ...settings.value, ...map };
    } catch (e) {
        error.value = "Failed to load settings.";
    } finally {
        loading.value = false;
    }
}

async function save() {
    const { valid } = await settingsForm.value.validate();
    if (!valid) return;
    if (!reason.value) {
        error.value = "Please provide a reason for the audit trail.";
        return;
    }
    saving.value = true;
    error.value = null;
    try {
        const res = await adminService.updateSettings(settings.value, reason.value);
        message.value = res.message || "Settings saved.";
        reason.value = "";
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Could not save settings.";
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>