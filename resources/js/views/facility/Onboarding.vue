<template>
    <div class="onboarding-page" style="min-height: 100vh; background: linear-gradient(135deg, #0c1b3f 0%, #0e5d92 60%, #147ab4 100%);">
        <div class="d-flex align-center justify-center pa-6" style="min-height: 100vh;">
            <v-card width="720" max-width="100%" elevation="12" rounded="xl" class="overflow-hidden">
                <div class="pa-8">
                    <div class="d-flex align-center mb-2">
                        <v-avatar size="44" color="primary" class="mr-3">
                            <v-icon icon="mdi-hospital-building" color="white"></v-icon>
                        </v-avatar>
                        <div>
                            <h1 class="text-h5 font-weight-bold">Welcome, Facility</h1>
                            <p class="text-body-2 text-medium-emphasis">Register your facility to manage clinic sessions and staff on Afya Plaza.</p>
                        </div>
                    </div>

                    <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="mb-4" closable @click:close="error = ''">
                        {{ error }}
                    </v-alert>

                    <div v-if="preview" class="mb-4">
                        <v-alert type="info" variant="tonal" density="comfortable" class="mb-2">
                            Your facility is <strong>pending verification</strong>. You can manage it once approved.
                        </v-alert>
                        <div class="d-flex align-center">
                            <v-btn color="primary" :to="'/facility/dashboard'" class="mr-2">Go to dashboard</v-btn>
                            <v-btn variant="tonal" @click="preview = null">Edit again</v-btn>
                        </div>
                    </div>

                    <v-form v-else @submit.prevent="submit">
                        <v-text-field v-model="form.name" label="Facility name *" prepend-inner-icon="mdi-domain"
                            variant="outlined" density="comfortable" :error="!!errors.name" :error-messages="errors.name" class="mb-2" />

                        <v-row dense>
                            <v-col cols="12" sm="6">
                                <v-autocomplete v-model="form.county_id" label="County *" :items="counties" item-title="name" item-value="id"
                                    prepend-inner-icon="mdi-map-marker-outline" variant="outlined" density="comfortable"
                                    :loading="loadingCounties" :error="!!errors.county_id" :error-messages="errors.county_id" class="mb-2" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-select v-model="form.type" label="Facility type" :items="types"
                                    prepend-inner-icon="mdi-shape-outline" variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                        </v-row>

                        <v-text-field v-model="form.city" label="City / Town *" prepend-inner-icon="mdi-city"
                            variant="outlined" density="comfortable" :error="!!errors.city" :error-messages="errors.city" class="mb-2" />
                        <v-text-field v-model="form.address" label="Street address *" prepend-inner-icon="mdi-map-marker-path"
                            variant="outlined" density="comfortable" :error="!!errors.address" :error-messages="errors.address" class="mb-2" />

                        <v-row dense>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.phone" label="Phone" prepend-inner-icon="mdi-phone-outline"
                                    variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.email" label="Public email" type="email" prepend-inner-icon="mdi-email-outline"
                                    variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                        </v-row>

                        <v-textarea v-model="form.description" label="Short description" rows="3"
                            prepend-inner-icon="mdi-format-align-left" variant="outlined" density="comfortable" class="mb-3" />

                        <v-btn type="submit" color="primary" block size="large" :loading="loading">Register my facility</v-btn>
                    </v-form>
                </div>
            </v-card>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useFacilityWorkspaceStore } from '../../stores/facilityWorkspaceStore';
import api from '../../services/api';

const auth = useAuthStore();
const store = useFacilityWorkspaceStore();
const router = useRouter();

const counties = ref([]);
const loadingCounties = ref(false);
const error = ref('');
const loading = ref(false);
const preview = ref(null);

const types = [
    { title: 'Clinic', value: 'clinic' },
    { title: 'Hospital', value: 'hospital' },
    { title: 'Diagnostic Centre', value: 'diagnostic' },
    { title: 'Pharmacy', value: 'pharmacy' },
    { title: 'Other', value: 'other' },
];

const form = reactive({
    name: '',
    county_id: null,
    type: 'clinic',
    city: '',
    address: '',
    phone: '',
    email: '',
    description: '',
});
const errors = reactive({ name: '', county_id: '', city: '', address: '' });

onMounted(async () => {
    await Promise.all([fetchCounties(), checkStatus()]);
});

async function fetchCounties() {
    loadingCounties.value = true;
    try {
        const res = await api.get('/counties');
        counties.value = res.data.data;
    } finally {
        loadingCounties.value = false;
    }
}

async function checkStatus() {
    try {
        const status = await store.loadOnboardingStatus();
        if (status.has_facility) {
            preview.value = status.facility;
        }
    } catch (e) { /* ignore */ }
}

function validate() {
    errors.name = '';
    errors.county_id = '';
    errors.city = '';
    errors.address = '';
    let ok = true;
    if (form.name.trim().length < 2) { errors.name = 'Enter a facility name'; ok = false; }
    if (!form.county_id) { errors.county_id = 'Select a county'; ok = false; }
    if (!form.city.trim()) { errors.city = 'Enter the city or town'; ok = false; }
    if (!form.address.trim()) { errors.address = 'Enter the street address'; ok = false; }
    return ok;
}

async function submit() {
    error.value = '';
    if (!validate()) return;

    loading.value = true;
    try {
        await store.completeOnboarding({ ...form });
        await auth.fetchMe();
        router.push('/facility/dashboard');
    } catch (e) {
        error.value = e.response?.data?.error || e.response?.data?.message || 'Failed to register facility';
    } finally {
        loading.value = false;
    }
}
</script>