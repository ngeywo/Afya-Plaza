<template>
    <div class="roles-page">
        <AppPageHeader title="Roles" subtitle="Manage roles and their permissions" icon="mdi-shield-account">
            <template #actions><v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Role</v-btn></template>
        </AppPageHeader>
        <v-card>
            <v-card-text>
                <v-data-table :headers="headers" :items="roles" :loading="loading" class="elevation-0" :items-per-page="20">
                    <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
                    <template #item.slug="{ item }"><code class="text-caption">{{ item.slug }}</code></template>
                    <template #item.users_count="{ item }"><v-chip size="small" color="secondary" variant="tonal">{{ item.users_count }}</v-chip></template>
                    <template #item.permissions_count="{ item }"><v-chip size="small" color="primary" variant="tonal">{{ item.permissions?.length || 0 }}</v-chip></template>
                    <template #item.actions="{ item }">
                        <v-btn icon="mdi-pencil" size="small" variant="text" :disabled="isProtected(item)" @click="editRole(item)"></v-btn>
                        <v-btn icon="mdi-shield-account" size="small" variant="text" color="primary" :disabled="isProtected(item)" @click="managePermissions(item)"></v-btn>
                        <v-btn icon="mdi-delete" size="small" variant="text" color="error" :disabled="isProtected(item)" @click="confirmDelete(item)"></v-btn>
                    </template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-shield-off-outline" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No roles defined yet.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>
        <!-- Create/Edit Dialog -->
        <v-dialog v-model="formDialog" max-width="500" persistent>
            <v-card><v-card-title>{{ isEditing ? 'Edit Role' : 'Create Role' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="form.name" label="Name *" class="mb-2"></v-text-field>
                    <v-text-field v-model="form.slug" label="Slug *" class="mb-2" :disabled="isEditing"></v-text-field>
                    <v-textarea v-model="form.description" label="Description" rows="2"></v-textarea>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="formDialog = false">Cancel</v-btn><v-btn color="primary" @click="saveRole">{{ isEditing ? 'Update' : 'Create' }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <!-- Permissions Dialog -->
        <v-dialog v-model="permDialog" max-width="700" persistent>
            <v-card v-if="selectedRole"><v-card-title>Manage Permissions &mdash; {{ selectedRole.name }}</v-card-title>
                <v-card-text>
                    <v-select v-if="!permGroups.length" v-model="selectedPermissions" :items="permissions" item-title="name" item-value="id" label="Permissions" multiple chips closable-chips></v-select>
                    <v-expansion-panels v-else>
                        <v-expansion-panel v-for="(perms, group) in permGroups" :key="group">
                            <v-expansion-panel-title class="font-weight-bold text-caption">{{ group || 'Ungrouped' }}</v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <v-checkbox v-for="p in perms" :key="p.id" :label="p.name" :hint="p.slug" density="compact" :model-value="isSelected(p.id)" @update:model-value="togglePermission(p)"></v-checkbox>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="permDialog = false">Cancel</v-btn><v-btn color="primary" @click="savePermissions">Save Permissions</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="deleteDialog" max-width="400">
            <v-card><v-card-title>Delete Role</v-card-title>
                <v-card-text>Are you sure you want to delete <strong>{{ selectedRole?.name }}</strong>?</v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="deleteDialog = false">Cancel</v-btn><v-btn color="error" @click="deleteRole">Delete</v-btn></v-card-actions>
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
const roles = ref([]);
const permissions = ref([]);
const loading = ref(false);
const formDialog = ref(false);
const permDialog = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selectedRole = ref(null);
const selectedPermissions = ref([]);
const form = ref({ name: "", slug: "", description: "" });
const headers = [
    { title: "Name", key: "name" },
    { title: "Slug", key: "slug" },
    { title: "Description", key: "description" },
    { title: "Users", key: "users_count" },
    { title: "Permissions", key: "permissions_count" },
    { title: "Actions", key: "actions", sortable: false },
];
const permGroups = computed(() => {
    const grouped = {};
    for (const p of permissions.value) { (grouped[p.group || "Ungrouped"] = grouped[p.group || "Ungrouped"] || []).push(p); }
    return grouped;
});
async function fetchRoles() { loading.value = true; try { const { data } = await api.get("/admin/roles"); roles.value = data.data; } catch (err) { console.error(err); } finally { loading.value = false; } }
async function fetchPermissions() { try { const { data } = await api.get("/admin/permissions"); permissions.value = data.data.all; } catch (err) { console.error(err); } }
function isProtected(role) { return ["super-admin", "system-owner"].includes(role.slug); }
function openCreate() { isEditing.value = false; form.value = { name: "", slug: "", description: "" }; formDialog.value = true; }
function editRole(role) { isEditing.value = true; selectedRole.value = role; form.value = { name: role.name, slug: role.slug, description: role.description }; formDialog.value = true; }
function managePermissions(role) { selectedRole.value = role; selectedPermissions.value = role.permissions?.map(p => p.id) || []; permDialog.value = true; }
function isSelected(id) { return selectedPermissions.value.includes(id); }
function togglePermission(p) {
    const idx = selectedPermissions.value.indexOf(p.id);
    if (idx >= 0) { selectedPermissions.value = selectedPermissions.value.filter(i => i !== p.id); }
    else { selectedPermissions.value = [...selectedPermissions.value, p.id]; }
}
function confirmDelete(role) { selectedRole.value = role; deleteDialog.value = true; }
async function saveRole() {
    try {
        if (isEditing.value) { await api.patch(`/admin/roles/${selectedRole.value.id}`, form.value); }
        else { await api.post("/admin/roles", form.value); }
        formDialog.value = false; notify(isEditing.value ? "Role updated" : "Role created"); fetchRoles();
    } catch (err) { notifyError(err.response?.data?.error || "Error saving role"); }
}
async function savePermissions() {
    try { await api.post(`/admin/roles/${selectedRole.value.id}/permissions`, { permission_ids: selectedPermissions.value }); permDialog.value = false; notify("Role permissions updated"); fetchRoles(); }
    catch (err) { notifyError(err.response?.data?.error || "Error saving permissions"); }
}
async function deleteRole() {
    try { await api.delete(`/admin/roles/${selectedRole.value.id}`); deleteDialog.value = false; notify("Role deleted"); fetchRoles(); }
    catch (err) { notifyError(err.response?.data?.error || "Error deleting role"); }
}
onMounted(() => { fetchRoles(); fetchPermissions(); });
</script>