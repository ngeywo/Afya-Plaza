<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <div class="d-flex align-center justify-space-between mb-4 gap-3">
            <div>
                <h1 class="text-h5 font-weight-bold">Specialties</h1>
                <p class="text-body-2 text-medium-emphasis">Curated taxonomy for the marketplace.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add specialty</v-btn>
        </div>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="items" :loading="loading" :items-per-page="20" density="comfortable">
                <template #item.name="{ item }">
                    <div class="d-flex align-center">
                        <v-icon :icon="item.icon || 'mdi-stethoscope'" class="mr-2 text-primary"></v-icon>
                        <span class="font-weight-medium">{{ item.name }}</span>
                    </div>
                </template>
                <template #item.description="{ item }"><span class="text-body-2">{{ item.description || "—" }}</span></template>
                <template #item.doctor_count="{ item }"><v-chip size="x-small" variant="tonal">{{ item.doctor_count }}</v-chip></template>
                <template #item.is_active="{ item }">
                    <v-chip size="x-small" :color="item.is_active ? 'success' : 'grey'" variant="flat">{{ item.is_active ? "Active" : "Inactive" }}</v-chip>
                </template>
                <template #item.actions="{ item }">
                    <v-btn size="small" variant="text" icon="mdi-pencil-outline" title="Edit" @click="openEdit(item)"></v-btn>
                    <v-btn size="small" variant="text" icon="mdi-delete-outline" color="error" title="Delete" :loading="deletingId === item.id" @click="onDelete(item)"></v-btn>
                </template>
            </v-data-table>
        </v-card>
        <v-dialog v-model="dialog.show" max-width="520" persistent>
            <v-card>
                <v-card-title class="text-h6">{{ dialog.id ? "Edit specialty" : "Add specialty" }}</v-card-title>
                <v-card-text>
                    <v-form ref="form">
                        <v-text-field v-model="dialog.form.name" label="Name *" variant="outlined" density="compact" :rules="[v => !!v || 'Name is required']"></v-text-field>
                        <v-text-field v-model="dialog.form.icon" label="Icon (Material icon name)" variant="outlined" density="compact"></v-text-field>
                        <v-textarea v-model="dialog.form.description" label="Description" variant="outlined" density="compact" rows="2"></v-textarea>
                        <v-switch v-model="dialog.form.is_active" label="Active" color="primary" inset></v-switch>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="dialog.show = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="dialog.saving" @click="onSave">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <AppConfirmDialog
            v-model="deleteDialog"
            title="Delete specialty"
            :message="'Delete specialty ' + (deleteTarget?.name || '') + ' permanently? This cannot be undone.'"
            confirm-label="Delete Specialty"
            confirm-color="error"
            confirm-icon="mdi-delete-outline"
            :loading="deletingId === deleteTarget?.id"
            @confirm="confirmDelete"
        />
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { adminService } from "../../services/adminService";
import AppConfirmDialog from "../../components/ui/AppConfirmDialog.vue";

const loading = ref(false);
const error = ref(null);
const items = ref([]);
const deletingId = ref(null);
const deleteDialog = ref(false);
const deleteTarget = ref(null);
const form = ref(null);
const dialog = ref({ show: false, id: null, saving: false, form: { name: "", icon: "", description: "", is_active: true } });

async function load() {
    loading.value = true;
    error.value = null;
    try {
        items.value = await adminService.getSpecialties();
    } catch (e) {
        error.value = "Failed to load specialties.";
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    dialog.value = { show: true, id: null, saving: false, form: { name: "", icon: "", description: "", is_active: true } };
}

function openEdit(item) {
    dialog.value = { show: true, id: item.id, saving: false, form: { name: item.name, icon: item.icon || "", description: item.description || "", is_active: !!item.is_active } };
}

async function onSave() {
    const { valid } = await form.value.validate();
    if (!valid) return;
    dialog.value.saving = true;
    try {
        if (dialog.value.id) {
            await adminService.updateSpecialty(dialog.value.id, dialog.value.form);
        } else {
            await adminService.createSpecialty(dialog.value.form);
        }
        dialog.value.show = false;
        await load();
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Could not save the specialty.";
    } finally {
        dialog.value.saving = false;
    }
}

async function onDelete(item) {
    deleteTarget.value = item;
    deleteDialog.value = true;
}

async function confirmDelete() {
    const item = deleteTarget.value;
    if (!item) return;
    deletingId.value = item.id;
    try {
        await adminService.deleteSpecialty(item.id);
        deleteDialog.value = false;
        await load();
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Could not delete the specialty.";
    } finally {
        deletingId.value = null;
    }
}

const headers = [
    { title: "Name", key: "name", sortable: true },
    { title: "Description", key: "description", sortable: false },
    { title: "Doctors", key: "doctor_count", sortable: true },
    { title: "Status", key: "is_active", sortable: true },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>