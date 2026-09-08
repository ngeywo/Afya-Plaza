<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-alert v-if="store.success" type="success" variant="tonal" class="mb-4" closable @click:close="store.success = null">{{ store.success }}</v-alert>
        <v-progress-linear v-if="store.loading && store.staff.length === 0" indeterminate></v-progress-linear>

        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Staff Members</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Users authorized to access this facility workspace.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="openAdd">Add Staff</v-btn>
        </div>

        <v-card v-if="!store.loading && store.staff.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-account-group-outline" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No staff members</h3>
            <p class="text-body-2 text-medium-emphasis">No users have been assigned to this facility yet.</p>
        </v-card>

        <v-card variant="outlined" v-if="store.staff.length > 0">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Facilities</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in store.staff" :key="s.id">
                        <td>
                            <div class="d-flex align-center">
                                <v-avatar size="32" color="primary" class="mr-2">
                                    <span class="text-white text-caption">{{ initials(s.name) }}</span>
                                </v-avatar>
                                <span class="font-weight-medium">{{ s.name }}</span>
                                <v-chip v-if="s.is_primary" size="x-small" color="primary" variant="tonal" class="ml-2">Primary</v-chip>
                            </div>
                        </td>
                        <td class="text-body-2">{{ s.email }}</td>
                        <td>
                            <v-chip v-for="r in s.roles" :key="r" size="x-small" variant="outlined" class="mr-1">{{ r }}</v-chip>
                        </td>
                        <td>
                            <v-chip :color="s.is_active ? 'success' : 'error'" size="x-small" variant="tonal">{{ s.is_active ? 'Active' : 'Inactive' }}</v-chip>
                        </td>
                        <td>
                            <v-chip size="x-small" variant="outlined">{{ s.authorized_facility_count }} {{ s.authorized_facility_count === 1 ? 'facility' : 'facilities' }}</v-chip>
                        </td>
                        <td class="text-right">
                            <v-btn v-if="!s.is_primary" icon="mdi-pencil" size="small" variant="text" title="Edit" @click="openEdit(s)"></v-btn>
                            <v-btn v-if="!s.is_primary" icon="mdi-delete" size="small" variant="text" color="error" title="Remove" @click="openRemove(s)"></v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>

        <v-dialog v-model="formDialog" max-width="500" persistent>
            <v-card>
                <v-card-title>{{ isEditing ? 'Edit Staff Member' : 'Add Staff Member' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-if="!isEditing" v-model="form.email" label="Email (existing user) *" type="email" class="mb-2" hint="The user must already have a platform account."></v-text-field>
                    <v-select v-model="form.role" :items="roleOptions" item-title="name" item-value="slug" label="Facility Role *"></v-select>
                    <v-switch v-if="isEditing" v-model="form.is_active" label="Active"></v-switch>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="formDialog = false">Cancel</v-btn><v-btn color="primary" :loading="saving" @click="save">{{ isEditing ? 'Update' : 'Add' }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="removeDialog" max-width="400">
            <v-card><v-card-title>Remove Staff Member</v-card-title>
                <v-card-text>Remove <strong>{{ selected?.name }}</strong> from this facility? They will lose access to this workspace.</v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="removeDialog = false">Cancel</v-btn><v-btn color="error" :loading="saving" @click="remove">Remove</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
const store = useFacilityWorkspaceStore();
const formDialog = ref(false);
const removeDialog = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const selected = ref(null);
const form = ref({ email: "", role: "facility-staff", is_active: true });
const roleOptions = [
    { name: "Facility Staff", slug: "facility-staff" },
    { name: "Facility Admin", slug: "facility-admin" },
];
onMounted(() => { store.fetchStaff(); });
function initials(name) { return name ? name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase() : "?"; }
function openAdd() { isEditing.value = false; form.value = { email: "", role: "facility-staff", is_active: true }; formDialog.value = true; }
function openEdit(s) {
    isEditing.value = true; selected.value = s;
    form.value = { email: s.email, role: (s.roles && s.roles[0]) || "facility-staff", is_active: !!s.is_active };
    formDialog.value = true;
}
function openRemove(s) { selected.value = s; removeDialog.value = true; }
async function save() {
    saving.value = true;
    try {
        if (isEditing.value) {
            await store.updateStaff(selected.value.id, { role: form.value.role, is_active: form.value.is_active });
        } else {
            await store.createStaff({ email: form.value.email, role: form.value.role });
        }
        formDialog.value = false;
    } catch (e) { /* store surfaces error */ }
    finally { saving.value = false; }
}
async function remove() {
    saving.value = true;
    try { await store.removeStaff(selected.value.id); removeDialog.value = false; }
    catch (e) { /* store surfaces error */ }
    finally { saving.value = false; }
}
</script>