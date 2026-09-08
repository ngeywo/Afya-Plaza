<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-alert v-if="saved" type="success" variant="tonal" class="mb-4" closable @click:close="saved = null">{{ saved }}</v-alert>

        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Settings</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Booking and workspace preferences for this facility.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-content-save" :loading="loading" @click="save">Save Settings</v-btn>
        </div>

        <v-card variant="outlined" class="pa-6" style="max-width: 640px;">
            <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>
            <template v-if="!loading">
                <h3 class="text-subtitle-1 font-weight-bold mb-4">Booking Preferences</h3>
                <v-row class="mb-4">
                    <v-col cols="12" md="6">
                        <v-text-field v-model="form.slot_duration_minutes" label="Default slot duration (minutes)" type="number" min="5" step="5"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="form.booking_notice_minutes" label="Minimum booking notice (minutes)" type="number" min="0" step="15" hint="How far in advance patients must book." persistent-hint></v-text-field>
                    </v-col>
                </v-row>
                <v-row class="mb-4">
                    <v-col cols="12" md="6">
                        <v-text-field v-model="form.cancel_horizon_hours" label="Free cancellation window (hours)" type="number" min="0" step="1"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-select v-model="form.currency" :items="['KES', 'UGX', 'TZS', 'NGN', 'GHS', 'USD']" label="Currency"></v-select>
                    </v-col>
                </v-row>
                <v-switch v-model="form.instant_booking" label="Allow instant online booking" color="primary" class="mb-2"></v-switch>
            </template>
        </v-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const form = ref({
    slot_duration_minutes: '30',
    booking_notice_minutes: '60',
    cancel_horizon_hours: '24',
    currency: 'KES',
    instant_booking: true,
});

const loading = ref(false);
const error = ref(null);
const saved = ref(null);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const data = await facilityWorkspaceService.getSettings();
        form.value = {
            slot_duration_minutes: data.slot_duration_minutes || '30',
            booking_notice_minutes: data.booking_notice_minutes || '60',
            cancel_horizon_hours: data.cancel_horizon_hours || '24',
            currency: data.currency || 'KES',
            instant_booking: (data.instant_booking ?? '1') !== '0',
        };
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load settings.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    loading.value = true;
    error.value = null;
    saved.value = null;
    try {
        await facilityWorkspaceService.updateSettings({
            slot_duration_minutes: String(form.value.slot_duration_minutes),
            booking_notice_minutes: String(form.value.booking_notice_minutes),
            cancel_horizon_hours: String(form.value.cancel_horizon_hours),
            currency: form.value.currency,
            instant_booking: form.value.instant_booking,
        });
        saved.value = 'Settings saved.';
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to save settings.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>