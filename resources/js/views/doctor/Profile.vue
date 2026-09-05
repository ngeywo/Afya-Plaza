<template>
    <div>
        <div class="d-flex align-center mb-6">
            <v-icon icon="mdi-account-circle" color="primary" size="32" class="mr-3"></v-icon>
            <h1 class="text-h5 font-weight-bold">My Profile</h1>
        </div>

        <v-progress-linear v-if="store.loading && !store.profile" indeterminate></v-progress-linear>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>

        <template v-if="store.profile">
            <v-card variant="outlined" class="mb-4">
                <div class="pa-6">
                    <div class="d-flex align-center mb-4">
                        <v-avatar size="80" color="primary" class="mr-4">
                            <span class="text-white text-h5 font-weight-bold">{{ initials }}</span>
                        </v-avatar>
                        <div>
                            <h2 class="text-h6 font-weight-bold">{{ store.profile.display_name }}</h2>
                            <div class="d-flex align-center mt-1 flex-wrap">
                                <v-chip v-if="store.profile.is_verified" color="success" size="small" variant="tonal" prepend-icon="mdi-check-decagram" class="mr-2 mb-1">Verified</v-chip>
                                <v-chip v-if="store.profile.is_featured" color="primary" size="small" variant="tonal" prepend-icon="mdi-star" class="mb-1">Featured</v-chip>
                            </div>
                        </div>
                    </div>
                    <v-btn variant="outlined" size="small" :href="`/doctors/${store.profile.slug}`" target="_blank" prepend-icon="mdi-open-in-new">View Public Profile</v-btn>
                </div>
            </v-card>

            <v-card variant="outlined" class="mb-4">
                <v-card-title class="text-subtitle-1 font-weight-bold">Edit Profile</v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="save">
                        <v-text-field v-model="form.display_name" label="Display Name" variant="outlined" class="mb-3"></v-text-field>
                        <v-textarea v-model="form.biography" label="Biography" variant="outlined" rows="3" class="mb-3"></v-textarea>
                        <v-textarea v-model="form.qualifications" label="Qualifications" variant="outlined" rows="2" class="mb-3"></v-textarea>
                        <v-row>
                            <v-col cols="6">
                                <v-text-field v-model.number="form.consultation_fee" label="Consultation Fee (KSh)" type="number" variant="outlined"></v-text-field>
                            </v-col>
                            <v-col cols="6">
                                <v-text-field v-model.number="form.years_of_experience" label="Years of Experience" type="number" variant="outlined"></v-text-field>
                            </v-col>
                        </v-row>
                        <v-select v-model="form.gender" :items="['male','female','other']" label="Gender" variant="outlined" class="mb-3"></v-select>
                        <v-alert v-if="saveMsg" type="success" variant="tonal" class="mb-3">{{ saveMsg }}</v-alert>
                        <v-btn type="submit" color="primary" :loading="saving">Save Changes</v-btn>
                    </v-form>
                </v-card-text>
            </v-card>

            <v-card variant="outlined">
                <v-card-title class="text-subtitle-1 font-weight-bold">My Clinics</v-card-title>
                <v-card-text>
                    <div v-for="f in store.profile.facilities" :key="f.id" class="d-flex align-center py-2">
                        <v-icon icon="mdi-hospital-building" color="primary" class="mr-3"></v-icon>
                        <div class="flex-grow-1">
                            <div class="font-weight-medium">{{ f.name }}</div>
                            <div class="text-caption text-medium-emphasis">{{ f.city }}, {{ f.county }}</div>
                        </div>
                        <v-chip :color="f.is_active ? 'success' : 'error'" size="small" variant="tonal">{{ f.is_active ? 'Active' : 'Inactive' }}</v-chip>
                    </div>
                    <div v-if="!store.profile.facilities?.length" class="text-medium-emphasis text-body-2">No facilities linked.</div>
                </v-card-text>
            </v-card>
        </template>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from "vue";
import { useDoctorWorkspaceStore } from "../../stores/doctorWorkspaceStore";

const store = useDoctorWorkspaceStore();
const saving = ref(false);
const saveMsg = ref('');

const form = ref({
    display_name: '',
    biography: '',
    qualifications: '',
    consultation_fee: '',
    gender: '',
    years_of_experience: '',
});

const initials = computed(() => {
    const name = store.profile?.display_name || '';
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
});

onMounted(async () => {
    await store.fetchProfile();
    if (store.profile) {
        form.value = {
            display_name: store.profile.display_name || '',
            biography: store.profile.biography || '',
            qualifications: store.profile.qualifications || '',
            consultation_fee: store.profile.consultation_fee || '',
            gender: store.profile.gender || '',
            years_of_experience: store.profile.years_of_experience || '',
        };
    }
});

async function save() {
    saving.value = true;
    saveMsg.value = '';
    try {
        await store.updateProfile(form.value);
        saveMsg.value = 'Profile updated successfully.';
        setTimeout(() => { saveMsg.value = ''; }, 3000);
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to update profile');
    } finally {
        saving.value = false;
    }
}
</script>