<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-alert v-if="store.success" type="success" variant="tonal" class="mb-4" closable @click:close="store.success = null">{{ store.success }}</v-alert>
        <v-progress-linear v-if="store.loading && !store.profile" indeterminate></v-progress-linear>

        <template v-if="store.profile">
            <div class="d-flex align-center mb-6">
                <v-avatar size="64" color="secondary" class="mr-4"><v-icon icon="mdi-hospital-building" color="white" size="32"></v-icon></v-avatar>
                <div>
                    <h1 class="text-h5 font-weight-bold">{{ store.profile.name }}</h1>
                    <div class="d-flex align-center mt-1">
                        <v-chip v-if="store.profile.is_verified" color="success" size="small" variant="tonal" prepend-icon="mdi-check-decagram" class="mr-2">Verified</v-chip>
                        <v-chip v-if="store.profile.is_active" color="primary" size="small" variant="tonal" prepend-icon="mdi-check-circle" class="mr-2">Active</v-chip>
                        <span class="text-caption text-medium-emphasis">{{ store.profile.type || 'Healthcare Facility' }}</span>
                    </div>
                </div>
            </div>

            <v-form @submit.prevent="save">
                <v-card variant="outlined" class="mb-4">
                    <v-card-title class="text-subtitle-1 font-weight-bold">Facility Information</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field v-model="form.name" label="Facility Name" variant="outlined" density="comfortable" :rules="[v => !!v || 'Required']"></v-text-field>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field v-model="form.type" label="Facility Type" variant="outlined" density="comfortable"></v-text-field>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field v-model="form.email" label="Email" type="email" variant="outlined" density="comfortable"></v-text-field>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field v-model="form.phone" label="Phone" variant="outlined" density="comfortable"></v-text-field>
                            </v-col>
                            <v-col cols="12">
                                <v-textarea v-model="form.description" label="Description" variant="outlined" density="comfortable" rows="3"></v-textarea>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mb-4">
                    <v-card-title class="text-subtitle-1 font-weight-bold">Location</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="8">
                                <v-text-field v-model="form.address" label="Address" variant="outlined" density="comfortable"></v-text-field>
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field v-model="form.city" label="City" variant="outlined" density="comfortable"></v-text-field>
                            </v-col>
                        </v-row>
                        <div class="text-caption text-medium-emphasis">Verification status (is_verified) is controlled by the platform and cannot be changed here.</div>
                    </v-card-text>
                </v-card>

                <div class="d-flex">
                    <v-spacer></v-spacer>
                    <v-btn type="submit" color="primary" :loading="saving" variant="flat">Save Changes</v-btn>
                </div>
            </v-form>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";

const store = useFacilityWorkspaceStore();
const saving = ref(false);
const form = reactive({
    name: "", description: "", address: "", city: "", phone: "", email: "", type: "",
});

onMounted(async () => {
    await store.fetchProfile();
    if (store.profile) {
        Object.assign(form, {
            name: store.profile.name || "",
            description: store.profile.description || "",
            address: store.profile.address || "",
            city: store.profile.city || "",
            phone: store.profile.phone || "",
            email: store.profile.email || "",
            type: store.profile.type || "",
        });
    }
});

async function save() {
    saving.value = true;
    try {
        await store.updateProfile({ ...form });
    } catch (e) {}
    saving.value = false;
}
</script>
