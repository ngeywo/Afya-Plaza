<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading && store.staff.length === 0" indeterminate></v-progress-linear>

        <h1 class="text-h5 font-weight-bold mb-4">Staff Members</h1>
        <p class="text-body-2 text-medium-emphasis mb-4">Users authorized to access this facility workspace. Section 25: staff members are scoped to authorized facilities only.</p>

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
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>

<script setup>
import { onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
const store = useFacilityWorkspaceStore();
onMounted(() => { store.fetchStaff(); });
function initials(name) { return name ? name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase() : "?"; }
</script>
