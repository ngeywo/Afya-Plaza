<template>
    <div class="users-page">
<AppPageHeader title="Users" subtitle="Manage platform user accounts" icon="mdi-account-multiple">
            <template #actions><v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add User</v-btn></template>
        </AppPageHeader>
        <v-card>
            <v-card-text>
                <v-row class="mb-3">
                    <v-col cols="12" md="4"><v-text-field v-model="filters.search" density="compact" variant="outlined" prepend-inner-icon="mdi-magnify" placeholder="Search users..." hide-details @update:model-value="debouncedFetch"></v-text-field></v-col>
                    <v-col cols="12" md="3"><v-select v-model="filters.role" :items="roleOptions" item-title="name" item-value="slug" density="compact" variant="outlined" hide-details clearable @update:model-value="fetchUsers"></v-select></v-col>
                    <v-col cols="12" md="3"><v-select v-model="filters.status" :items="statusOptions" density="compact" variant="outlined" hide-details clearable @update:model-value="fetchUsers"></v-select></v-col>
                </v-row>
                <v-data-table :headers="headers" :items="users" :loading="loading" :items-length="meta.total" class="elevation-0">
                    <template #item.name="{ item }"><div class="d-flex align-center"><v-avatar size="32" color="primary" class="mr-2"><span class="text-white text-caption">{{ item.name.charAt(0).toUpperCase() }}</span></v-avatar>{{ item.name }}</div></template>
                    <template #item.roles="{ item }"><v-chip v-for="role in item.roles" :key="role.id" size="small" color="primary" variant="tonal" class="mr-1">{{ role.name }}</v-chip></template>
                    <template #item.account_state="{ item }"><v-chip :color="statusColor(item.account_state)" size="small">{{ item.account_state }}</v-chip></template>
                    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
<template #item.actions="{ item }">
                        <v-btn icon="mdi-eye" size="small" variant="text" @click="viewUser(item)"></v-btn>
                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="editUser(item)"></v-btn>
                        <v-btn icon="mdi-account-off" size="small" variant="text" color="warning" @click="openStatus(item)"></v-btn>
                        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)"></v-btn>
                    </template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-account-search-outline" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No users match your filters.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>
        <v-dialog v-model="formDialog" max-width="500" persistent>
            <v-card><v-card-title>{{ isEditing ? 'Edit User' : 'Create User' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="form.name" label="Name *" class="mb-2"></v-text-field>
                    <v-text-field v-model="form.email" label="Email *" type="email" class="mb-2"></v-text-field>
                    <v-text-field v-if="!isEditing" v-model="form.password" label="Password *" type="password" class="mb-2"></v-text-field>
                    <v-select v-model="form.role_ids" :items="roles" item-title="name" item-value="id" label="Roles *" multiple chips closable-chips></v-select>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="formDialog = false">Cancel</v-btn><v-btn color="primary" @click="saveUser">{{ isEditing ? 'Update' : 'Create' }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="viewDialog" max-width="450">
            <v-card v-if="selectedUser"><v-card-title>User Details</v-card-title>
                <v-card-text>
                    <div class="mb-2"><b>Name:</b> {{ selectedUser.name }}</div>
                    <div class="mb-2"><b>Email:</b> {{ selectedUser.email }}</div>
                    <div class="mb-2"><b>Status:</b> <v-chip :color="statusColor(selectedUser.account_state)" size="small">{{ selectedUser.account_state }}</v-chip></div>
                    <div class="mb-2"><b>Created:</b> {{ formatDate(selectedUser.created_at) }}</div>
                    <div><b>Roles:</b> <v-chip v-for="role in selectedUser.roles" :key="role.id" size="small" class="ml-1">{{ role.name }}</v-chip></div>
                </v-card-text>
            </v-card>
        </v-dialog>
        <v-dialog v-model="statusDialog" max-width="400">
            <v-card><v-card-title>Change User Status</v-card-title>
                <v-card-text><p class="mb-3">Change status for <strong>{{ selectedUser?.name }}</strong></p><v-select v-model="newStatus" :items="statusOptions" label="New Status"></v-select></v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="statusDialog = false">Cancel</v-btn><v-btn color="primary" @click="changeStatus">Update Status</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
<v-dialog v-model="deleteDialog" max-width="400">
            <v-card><v-card-title>Delete User</v-card-title>
                <v-card-text>Are you sure you want to delete <strong>{{ selectedUser?.name }}</strong>?</v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="deleteDialog = false">Cancel</v-btn><v-btn color="error" @click="deleteUser">Delete</v-btn></v-card-actions>
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
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();
const users = ref([]);
const roles = ref([]);
const loading = ref(false);
const filters = ref({ search: "", role: "", status: "" });
const meta = ref({ current_page: 1, total: 0 });
const formDialog = ref(false);
const viewDialog = ref(false);
const statusDialog = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selectedUser = ref(null);
const form = ref({ name: "", email: "", password: "", role_ids: [] });
const newStatus = ref("active");
const headers = [
    { title: "User", key: "name" },
    { title: "Email", key: "email" },
    { title: "Roles", key: "roles" },
    { title: "Status", key: "account_state" },
    { title: "Created", key: "created_at" },
    { title: "Actions", key: "actions", sortable: false },
];
const roleOptions = ref([]);
const statusOptions = ["active", "suspended", "disabled", "deactivated"];
let debounceTimer;
const debouncedFetch = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetchUsers, 300); };
async function fetchUsers() {
    loading.value = true;
    try {
        const params = { page: meta.value.current_page, per_page: 20 };
        if (filters.value.search) params.search = filters.value.search;
        if (filters.value.role) params.role = filters.value.role;
        if (filters.value.status) params.status = filters.value.status;
        const { data } = await api.get("/admin/users", { params });
        users.value = data.data;
        meta.value = data.meta;
    } catch (err) { console.error(err); }
    finally { loading.value = false; }
}
async function fetchRoles() {
    try { const { data } = await api.get("/admin/roles"); roles.value = data.data; roleOptions.value = data.data; } catch (err) { console.error(err); }
}
function openCreate() { isEditing.value = false; form.value = { name: "", email: "", password: "", role_ids: [] }; formDialog.value = true; }
function editUser(user) { isEditing.value = true; selectedUser.value = user; form.value = { name: user.name, email: user.email, role_ids: user.roles?.map(r => r.id) || [] }; formDialog.value = true; }
function viewUser(user) { selectedUser.value = user; viewDialog.value = true; }
function openStatus(user) { selectedUser.value = user; newStatus.value = user.account_state; statusDialog.value = true; }
function confirmDelete(user) { selectedUser.value = user; deleteDialog.value = true; }
async function saveUser() {
    try {
        if (isEditing.value) { await api.patch(`/admin/users/${selectedUser.value.id}`, form.value); }
        else { await api.post("/admin/users", form.value); }
formDialog.value = false;
        notify(isEditing.value ? "User updated" : "User created");
        fetchUsers();
    } catch (err) { notifyError(err.response?.data?.error || "Error saving user"); }
}
async function changeStatus() {
    try { await api.post(`/admin/users/${selectedUser.value.id}/status`, { status: newStatus.value }); statusDialog.value = false; notify("User status updated"); fetchUsers(); }
    catch (err) { notifyError(err.response?.data?.error || "Error changing status"); }
}
async function deleteUser() {
    try { await api.delete(`/admin/users/${selectedUser.value.id}`); deleteDialog.value = false; notify("User deleted"); fetchUsers(); }
    catch (err) { notifyError(err.response?.data?.error || "Error deleting user"); }
}
function statusColor(status) { return { active: "success", suspended: "warning", disabled: "error", deactivated: "grey" }[status] || "grey"; }
function formatDate(date) { return date ? new Date(date).toLocaleDateString() : "-"; }
onMounted(() => { fetchUsers(); fetchRoles(); });
</script>
