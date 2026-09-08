<template>
    <div class="privileges-page">
        <AppPageHeader title="Privileges" subtitle="Per-user roles and effective permissions" icon="mdi-account-star" />
        <v-card>
            <v-card-text>
                <v-row class="mb-3">
                    <v-col cols="12" md="6"><v-text-field v-model="filters.search" density="compact" variant="outlined" prepend-inner-icon="mdi-magnify" placeholder="Search users..." hide-details @update:model-value="debouncedFetch"></v-text-field></v-col>
                </v-row>
                <v-data-table :headers="headers" :items="users" :loading="loading" :items-length="meta.total" class="elevation-0">
                    <template #item.name="{ item }"><div class="d-flex align-center"><v-avatar size="32" color="primary" class="mr-2"><span class="text-white text-caption">{{ item.name.charAt(0).toUpperCase() }}</span></v-avatar>{{ item.name }}</div></template>
                    <template #item.roles="{ item }"><v-chip v-for="role in item.roles" :key="role.id" size="small" color="primary" variant="tonal" class="mr-1">{{ role.name }}</v-chip></template>
                    <template #item.permissions="{ item }">
                        <v-chip size="small" color="secondary" variant="tonal">{{ item.effective_permissions.length }} permissions</v-chip>
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn icon="mdi-eye" size="small" variant="text" title="View permissions" @click="viewPermissions(item)"></v-btn>
                        <v-btn icon="mdi-shield-edit" size="small" variant="text" color="primary" title="Edit roles" @click="editRoles(item)"></v-btn>
                    </template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-account-search-outline" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No users match your search.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <v-dialog v-model="permDialog" max-width="600">
            <v-card v-if="selectedUser">
                <v-card-title>Effective Permissions</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-3"><strong>{{ selectedUser.name }}</strong> &mdash; {{ selectedUser.email }}</p>
                    <v-chip v-for="p in selectedUser.effective_permissions" :key="p.slug" size="small" variant="outlined" class="mr-1 mb-1"><code>{{ p.slug }}</code></v-chip>
                    <v-alert v-if="!selectedUser.effective_permissions.length" type="info" variant="tonal">No permissions assigned.</v-alert>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="permDialog = false">Close</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="rolesDialog" max-width="500" persistent>
            <v-card v-if="selectedUser">
                <v-card-title>Edit Roles &mdash; {{ selectedUser.name }}</v-card-title>
                <v-card-text>
                    <v-select v-model="selectedRoleIds" :items="roles" item-title="name" item-value="id" label="Roles" multiple chips closable-chips></v-select>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="rolesDialog = false">Cancel</v-btn><v-btn color="primary" @click="saveRoles">Save Roles</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import api from "../../services/api";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import { useNotifier } from "../../composables/useNotifier";
const { snackbar, snackText, snackColor, notifyError } = useNotifier();
const users = ref([]);
const roles = ref([]);
const loading = ref(false);
const filters = ref({ search: "" });
const meta = ref({ current_page: 1, total: 0 });
const permDialog = ref(false);
const rolesDialog = ref(false);
const selectedUser = ref(null);
const selectedRoleIds = ref([]);
const headers = [
    { title: "User", key: "name" },
    { title: "Email", key: "email" },
    { title: "Roles", key: "roles" },
    { title: "Permissions", key: "permissions" },
    { title: "Actions", key: "actions", sortable: false },
];
let debounceTimer;
const debouncedFetch = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetchUsers, 300); };
async function fetchUsers() {
    loading.value = true;
    try {
        const params = { page: meta.value.current_page, per_page: 20 };
        if (filters.value.search) params.search = filters.value.search;
        const { data } = await api.get("/admin/users", { params });
        users.value = data.data;
        meta.value = data.meta;
    } catch (err) { console.error(err); }
    finally { loading.value = false; }
}
async function fetchRoles() {
    try { const { data } = await api.get("/admin/roles"); roles.value = data.data; } catch (err) { console.error(err); }
}
function viewPermissions(user) { selectedUser.value = user; permDialog.value = true; }
function editRoles(user) { selectedUser.value = user; selectedRoleIds.value = user.roles?.map(r => r.id) || []; rolesDialog.value = true; }
async function saveRoles() {
    try {
        await api.post(`/admin/users/${selectedUser.value.id}/roles`, { role_ids: selectedRoleIds.value });
        rolesDialog.value = false;
        fetchUsers();
    } catch (err) { notifyError(err.response?.data?.error || "Error saving roles"); }
}
onMounted(() => { fetchUsers(); fetchRoles(); });
</script>