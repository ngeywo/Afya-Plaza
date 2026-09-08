<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" variant="tonal" class="mb-4" closable @click:close="success = null">{{ success }}</v-alert>

        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Services</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Priced services each doctor offers at this facility.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Service</v-btn>
        </div>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <v-card v-if="!loading && services.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-stethoscope" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No services</h3>
            <p class="text-body-2 text-medium-emphasis">Add priced services that doctors offer at this facility.</p>
        </v-card>

        <v-card v-if="!loading && services.length > 0" variant="outlined">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Doctor</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in services" :key="s.id">
                        <td class="font-weight-medium">{{ s.service_name }}</td>
                        <td>{{ s.doctor?.name || '—' }}</td>
                        <td>KSh {{ Number(s.price).toLocaleString() }}</td>
                        <td>{{ s.duration_minutes }} min</td>
                        <td>
                            <v-chip :color="s.is_active ? 'success' : 'default'" size="x-small" variant="tonal">{{ s.is_active ? 'Active' : 'Inactive' }}</v-chip>
                        </td>
                        <td class="text-right">
                            <v-btn icon="mdi-pencil" size="small" variant="text" title="Edit" @click="openEdit(s)"></v-btn>
                            <v-btn icon="mdi-content-save" size="small" variant="text" :color="s.is_active ? 'default' : 'success'" :title="s.is_active ? 'Deactivate' : 'Activate'" @click="toggle(s)"></v-btn>
                            <v-btn icon="mdi-delete" size="small" variant="text" color="error" title="Delete" @click="openDelete(s)"></v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>

        <v-dialog v-model="formDialog" max-width="500" persistent>
            <v-card>
                <v-card-title>{{ isEditing ? 'Edit Service' : 'Add Service' }}</v-card-title>
                <v-card-text>
                    <v-select v-if="!isEditing" v-model="form.doctor_id" :items="doctorOptions" item-title="name" item-value="id" label="Doctor *" class="mb-2"></v-select>
                    <v-text-field v-model="form.service_name" label="Service name *" class="mb-2"></v-text-field>
                    <v-text-field v-model="form.price" label="Price (KSh) *" type="number" min="0" class="mb-2"></v-text-field>
                    <v-text-field v-model="form.duration_minutes" label="Duration (minutes)" type="number" min="5"></v-text-field>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="formDialog = false">Cancel</v-btn><v-btn color="primary" :loading="saving" @click="save">{{ isEditing ? 'Update' : 'Add' }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="400">
            <v-card>
                <v-card-title>Delete Service</v-card-title>
                <v-card-text>Delete <strong>{{ selected?.service_name }}</strong>? Services with bookings cannot be deleted — deactivate them instead.</v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="deleteDialog = false">Cancel</v-btn><v-btn color="error" :loading="saving" @click="remove">Delete</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const services = ref([]);
const doctorOptions = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const success = ref(null);
const formDialog = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selected = ref(null);
const form = ref({ doctor_id: null, service_name: '', price: 0, duration_minutes: 30 });

async function load() {
    loading.value = true;
    error.value = null;
    try {
        services.value = await facilityWorkspaceService.getServices();
        const doctors = await facilityWorkspaceService.getDoctors();
        doctorOptions.value = (doctors || []).map((d) => ({ id: d.id, name: d.name }));
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load services.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    isEditing.value = false;
    form.value = { doctor_id: doctorOptions.value[0]?.id ?? null, service_name: '', price: 0, duration_minutes: 30 };
    formDialog.value = true;
}

function openEdit(s) {
    isEditing.value = true;
    selected.value = s;
    form.value = { doctor_id: s.doctor?.id, service_name: s.service_name, price: s.price, duration_minutes: s.duration_minutes };
    formDialog.value = true;
}

async function save() {
    saving.value = true;
    error.value = null;
    success.value = null;
    try {
        if (isEditing.value) {
            await facilityWorkspaceService.updateService(selected.value.id, form.value);
            success.value = 'Service updated.';
        } else {
            await facilityWorkspaceService.createService(form.value);
            success.value = 'Service added.';
        }
        formDialog.value = false;
        await load();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to save service.';
    } finally {
        saving.value = false;
    }
}

async function toggle(s) {
    saving.value = true;
    error.value = null;
    try {
        await facilityWorkspaceService.updateService(s.id, { is_active: !s.is_active });
        await load();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to update service.';
    } finally {
        saving.value = false;
    }
}

function openDelete(s) {
    selected.value = s;
    deleteDialog.value = true;
}

async function remove() {
    saving.value = true;
    error.value = null;
    success.value = null;
    try {
        await facilityWorkspaceService.deleteService(selected.value.id);
        deleteDialog.value = false;
        success.value = 'Service deleted.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to delete service.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>