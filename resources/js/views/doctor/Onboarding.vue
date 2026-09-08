<template>
    <div class="onboarding-page" style="min-height: 100vh; background: linear-gradient(135deg, #0c1b3f 0%, #0e5d92 60%, #147ab4 100%);">
        <div class="d-flex align-center justify-center pa-6" style="min-height: 100vh;">
            <v-card width="720" max-width="100%" elevation="12" rounded="xl" class="overflow-hidden">
                <div class="pa-8">
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="d-flex align-center">
                            <v-avatar size="44" color="primary" class="mr-3">
                                <v-icon icon="mdi-stethoscope" color="white"></v-icon>
                            </v-avatar>
                            <div>
                                <h1 class="text-h5 font-weight-bold">Welcome, Doctor</h1>
                                <p class="text-body-2 text-medium-emphasis">Complete your public profile to start practising on Afya Plaza.</p>
                            </div>
                        </div>
                    </div>

                    <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="mb-4" closable @click:close="error = ''">
                        {{ error }}
                    </v-alert>

                    <div v-if="preview" class="mb-4">
                        <v-alert type="info" variant="tonal" density="comfortable" class="mb-2">
                            Your profile is <strong>pending verification</strong>. It will appear to patients once approved by our team.
                        </v-alert>
                        <div class="d-flex align-center">
                            <v-btn color="primary" :to="'/doctor/dashboard'" class="mr-2">Go to dashboard</v-btn>
                            <v-btn variant="tonal" @click="preview = null">Edit again</v-btn>
                        </div>
                    </div>

                    <v-form v-else @submit.prevent="submit">
                        <v-text-field v-model="form.display_name" label="Public display name *" hint="Patients will see this name on your profile"
                            prepend-inner-icon="mdi-account-outline" variant="outlined" density="comfortable"
                            :error="!!errors.display_name" :error-messages="errors.display_name" class="mb-2" />

                        <v-autocomplete v-model="form.specialty_id" label="Primary specialty *" :items="specialties" item-title="name"
                            item-value="id" prepend-inner-icon="mdi-doctor" variant="outlined" density="comfortable"
                            :loading="loadingSpecialties" :error="!!errors.specialty_id" :error-messages="errors.specialty_id" class="mb-2" />

                        <v-text-field v-model.number="form.consultation_fee" label="Consultation fee (KES) *" type="number" min="0"
                            prepend-inner-icon="mdi-cash" variant="outlined" density="comfortable"
                            :error="!!errors.consultation_fee" :error-messages="errors.consultation_fee" class="mb-2" />

                        <v-row dense>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.license_number" label="License / Registration No." prepend-inner-icon="mdi-card-account-details-outline"
                                    variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-select v-model="form.gender" label="Gender" :items="['male', 'female', 'other']"
                                    prepend-inner-icon="mdi-human" variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                        </v-row>

                        <v-row dense>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model.number="form.years_of_experience" label="Years of experience" type="number" min="0" max="80"
                                    prepend-inner-icon="mdi-clock-outline" variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="form.qualifications" label="Qualifications" hint="e.g. MBChB, MMed (Paediatrics)"
                                    prepend-inner-icon="mdi-school-outline" variant="outlined" density="comfortable" class="mb-2" />
                            </v-col>
                        </v-row>

                        <v-textarea v-model="form.biography" label="Short biography" rows="4"
                            prepend-inner-icon="mdi-format-align-left" variant="outlined" density="comfortable"
                            hint="Tell patients about your practice, background and approach." class="mb-3" />

                        <v-btn type="submit" color="primary" block size="large" :loading="loading">Create my doctor profile</v-btn>
                    </v-form>
                </div>
            </v-card>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useDoctorWorkspaceStore } from '../../stores/doctorWorkspaceStore';
import api from '../../services/api';

const auth = useAuthStore();
const store = useDoctorWorkspaceStore();
const router = useRouter();

const specialties = ref([]);
const loadingSpecialties = ref(false);
const error = ref('');
const loading = ref(false);
const preview = ref(null);

const form = reactive({
    display_name: '',
    specialty_id: null,
    consultation_fee: null,
    license_number: '',
    gender: null,
    years_of_experience: null,
    qualifications: '',
    biography: '',
});
const errors = reactive({ display_name: '', specialty_id: '', consultation_fee: '' });

const user = computed(() => auth.user);

onMounted(async () => {
    await Promise.all([fetchSpecialties(), checkStatus()]);
});

async function fetchSpecialties() {
    loadingSpecialties.value = true;
    try {
        const res = await api.get('/specialties');
        specialties.value = res.data.data;
    } finally {
        loadingSpecialties.value = false;
    }
}

async function checkStatus() {
    try {
        const status = await store.loadOnboardingStatus();
        if (status.has_profile) {
            preview.value = status.doctor;
        } else if (user.value?.name) {
            form.display_name = user.value.name;
        }
    } catch (e) { /* ignore */ }
}

function validate() {
    errors.display_name = '';
    errors.specialty_id = '';
    errors.consultation_fee = '';
    let ok = true;
    if (form.display_name.trim().length < 2) { errors.display_name = 'Enter your display name'; ok = false; }
    if (!form.specialty_id) { errors.specialty_id = 'Select your primary specialty'; ok = false; }
    if (form.consultation_fee === null || form.consultation_fee === undefined || form.consultation_fee < 0) {
        errors.consultation_fee = 'Enter a valid consultation fee'; ok = false;
    }
    return ok;
}

async function submit() {
    error.value = '';
    if (!validate()) return;

    loading.value = true;
    try {
        await store.completeOnboarding({ ...form });
        // Refresh the user payload so the guard sees a completed profile.
        await auth.fetchMe();
        router.push('/doctor/dashboard');
    } catch (e) {
        error.value = e.response?.data?.error || e.response?.data?.message || 'Failed to create profile';
    } finally {
        loading.value = false;
    }
}
</script>