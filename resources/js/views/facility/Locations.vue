<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-alert v-if="store.success" type="success" variant="tonal" class="mb-4" closable @click:close="store.success = null">{{ store.success }}</v-alert>

        <div class="d-flex align-center mb-4">
            <h1 class="text-h5 font-weight-bold flex-grow-1">Facility Locations</h1>
            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openAdd">Add Location</v-btn>
        </div>

        <v-progress-linear v-if="store.loading && store.locations.length === 0" indeterminate></v-progress-linear>

        <v-card v-if="!store.loading && store.locations.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-map-marker-off" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No locations</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">Add a branch or alternate location for this facility.</p>
            <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" @click="openAdd">Add First Location</v-btn>
        </v-card>

        <v-row>
            <v-col v-for="l in store.locations" :key="l.id" cols="12" md="6">
                <v-card variant="outlined" class="pa-4 h-100">
                    <div class="d-flex align-center mb-2">
                        <v-icon icon="mdi-map-marker" color="primary" class="mr-2"></v-icon>
                        <div class="text-h6 font-weight-bold flex-grow-1">{{ l.name }}</div>
                        <v-chip v-if="l.is_primary" color="primary" size="x-small" variant="tonal">Primary</v-chip>
                    </div>
                    <div class="text-body-2 text-medium-emphasis">
                        <div v-if="l.address">{{ l.address }}</div>
                        <div>{{ [l.city, l.county].filter(Boolean).join(', ') }}</div>
                        <div v-if="l.phone" class="mt-1"><v-icon icon="mdi-phone" size="14"></v-icon> {{ l.phone }}</div>
                        <div v-if="l.email"><v-icon icon="mdi-email" size="14"></v-icon> {{ l.email }}</div>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="addDialog" max-width="500">
            <v-card>
                <v-card-title class="text-h6">Add Location</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newLocation.name" label="Location name" variant="outlined" density="comfortable" class="mb-2"></v-text-field>
                    <v-text-field v-model="newLocation.address" label="Address" variant="outlined" density="comfortable" class="mb-2"></v-text-field>
                    <v-text-field v-model="newLocation.city" label="City" variant="outlined" density="comfortable" class="mb-2"></v-text-field>
                    <v-text-field v-model="newLocation.phone" label="Phone" variant="outlined" density="comfortable" class="mb-2"></v-text-field>
                    <v-text-field v-model="newLocation.email" label="Email" type="email" variant="outlined" density="comfortable" class="mb-2"></v-text-field>
                    <v-checkbox v-model="newLocation.is_primary" label="Set as primary location" density="comfortable" hide-details></v-checkbox>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="addDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";

const store = useFacilityWorkspaceStore();
const addDialog = ref(false);
const saving = ref(false);
const newLocation = reactive({ name: "", address: "", city: "", phone: "", email: "", is_primary: false });

onMounted(() => { store.fetchLocations(); });

function openAdd() {
    Object.assign(newLocation, { name: "", address: "", city: "", phone: "", email: "", is_primary: false });
    addDialog.value = true;
}

async function save() {
    if (!newLocation.name.trim()) return;
    saving.value = true;
    try {
        await store.createLocation({ ...newLocation });
        addDialog.value = false;
    } catch (e) {}
    saving.value = false;
}
</script>
