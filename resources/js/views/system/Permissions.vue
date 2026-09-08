<template>
    <div class="permissions-page">
        <AppPageHeader title="Permissions" subtitle="System permissions and capabilities" icon="mdi-key">
            <template #actions><v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Permission</v-btn></template>
        </AppPageHeader>
        <v-progress-linear v-if="loading" indeterminate class="mb-2"></v-progress-linear>
        <v-card v-if="!loading">
            <v-card-text>
                <v-expansion-panels v-if="permGroups.length">
                    <v-expansion-panel v-for="(perms, group) in permGroups" :key="group">
                        <v-expansion-panel-title class="font-weight-bold">
                            <v-icon icon="mdi-tag" size="small" class="mr-2"></v-icon>{{ group || 'Ungrouped' }}
                            <v-chip size="x-small" variant="tonal" class="ml-2">{{ perms.length }}</v-chip>
                        </v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <v-list>
                                <v-list-item v-for="p in perms" :key="p.id" border="b-thin" class="py-1">
                                    <template #prepend><v-icon icon="mdi-key" size="small" color="secondary"></v-icon></template>
                                    <v-list-item-title class="text-body-2 font-weight-medium">{{ p.name }}</v-list-item-title>
                                    <v-list-item-subtitle><code class="text-caption">{{ p.slug }}</code></v-list-item-subtitle>
                                    <template #append>
                                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="editPermission(p)"></v-btn>
                                        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(p)"></v-btn>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
                <div v-else class="pa-6 text-center">
                    <v-icon icon="mdi-key" size="48" color="medium-emphasis" class="mb-2"></v-icon>
                    <p class="text-body-2 text-medium-emphasis">No permissions defined.</p>
                </div>
            </v-card-text>
        </v-card>

        <v-dialog v-model="formDialog" max-width="500" persistent>
            <v-card><v-card-title>{{ isEditing ? 'Edit Permission' : 'Create Permission' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="form.name" label="Name *" class="mb-2"></v-text-field>
                    <v-text-field v-model="form.slug" label="Slug *" class="mb-2" hint="e.g. appointments.view"></v-text-field>
                    <v-combobox v-model="form.group" :items="groupNames" label="Group *" class="mb-2"></v-combobox>
                    <v-textarea v-model="form.description" label="Description" rows="2"></v-textarea>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="formDialog = false">Cancel</v-btn><v-btn color="primary" @click="savePermission">{{ isEditing ? 'Update' : 'Create' }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="400">
            <v-card><v-card-title>Delete Permission</v-card-title>
                <v-card-text>Are you sure you want to delete <strong>{{ selectedPermission?.name }}</strong>?</v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="deleteDialog = false">Cancel</v-btn><v-btn color="error" @click="deletePermission">Delete</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from "vue";
import api from "../../services/api";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import { useNotifier } from "../../composables/useNotifier";
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();
const permissions = ref([]);
const loading = ref(false);
const formDialog = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selectedPermission = ref(null);
const form = ref({ name: "", slug: "", group: "", description: "" });
const permGroups = computed(() => {
    const grouped = {};
    for (const p of permissions.value) { (grouped[p.group || "Ungrouped"] = grouped[p.group || "Ungrouped"] || []).push(p); }
    return grouped;
});
const groupNames = computed(() => Object.keys(permGroups.value));
async function fetchPermissions() {
    loading.value = true;
    try {
        const { data } = await api.get("/admin/permissions");
        permissions.value = data.data.all;
    } catch (err) { console.error(err); }
    finally { loading.value = false; }
}
function openCreate() { isEditing.value = false; form.value = { name: "", slug: "", group: "", description: "" }; formDialog.value = true; }
function editPermission(p) { isEditing.value = true; selectedPermission.value = p; form.value = { name: p.name, slug: p.slug, group: p.group, description: p.description || "" }; formDialog.value = true; }
function confirmDelete(p) { selectedPermission.value = p; deleteDialog.value = true; }
async function savePermission() {
    try {
        if (isEditing.value) { await api.patch(`/admin/permissions/${selectedPermission.value.id}`, form.value); }
        else { await api.post("/admin/permissions", form.value); }
        formDialog.value = false;
        notify(isEditing.value ? "Permission updated" : "Permission created");
        fetchPermissions();
    } catch (err) { notifyError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(", ") : (err.response?.data?.error || "Error saving permission")); }
}
async function deletePermission() {
    try { await api.delete(`/admin/permissions/${selectedPermission.value.id}`); deleteDialog.value = false; notify("Permission deleted"); fetchPermissions(); }
    catch (err) { notifyError(err.response?.data?.error || "Error deleting permission"); }
}
onMounted(fetchPermissions);
</script>