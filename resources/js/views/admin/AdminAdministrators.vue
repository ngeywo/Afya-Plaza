<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading" indeterminate></v-progress-linear>
        <h1 class="text-h5 font-weight-bold mb-4">Administrators</h1>
        <v-card variant="outlined" class="mb-4">
            <v-card-text class="d-flex gap-3 flex-wrap">
                <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" density="compact" hide-details clearable style="max-width:300px;"></v-text-field>
                <v-select v-model="filterRole" :items="roleOptions" label="Role" density="compact" hide-details style="max-width:160px;"></v-select>
                <v-select v-model="filterStatus" :items="statusOptions" label="Status" density="compact" hide-details style="max-width:160px;"></v-select>
            </v-card-text>
        </v-card>
        <v-card variant="outlined">
            <v-data-table :headers="headers" :items="filtered" :items-per-page="20" density="comfortable">
                <template #item.name="{ item }">
                    <div>
                        <div class="font-weight-medium">{{ item.name }}</div>
                        <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                    </div>
                </template>
                <template #item.role="{ item }">
                    <v-chip size="small" :color="item.role === 'doctor' ? 'primary' : 'secondary'" variant="tonal">{{ item.role === 'facility-admin' ? 'facility admin' : item.role }}</v-chip>
                </template>
                <template #item.workspace="{ item }">
                    <v-btn size="x-small" variant="tonal" color="info" :to="item.detail_url">Inspect</v-btn>
                </template>
            </v-data-table>
        </v-card>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from "vue";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";
const store = useAdminWorkspaceStore();
const search = ref("");
const filterRole = ref("");
const filterStatus = ref("");
onMounted(() => { if (!store.administrators.length) store.fetchAdministrators(); });
const roleOptions = ["", "doctor", "facility-admin"];
const statusOptions = ["", "active", "inactive"];
const headers = [
    { title: "Name / Email", key: "name", sortable: true },
    { title: "Role", key: "role", sortable: true },
    { title: "Status", key: "is_active", sortable: true },
    { title: "Workspace", key: "workspace", sortable: false, align: "end" },
];
const filtered = computed(() => {
    let list = store.administrators;
    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter(a => (a.name && a.name.toLowerCase().includes(q)) || (a.email && a.email.toLowerCase().includes(q)));
    }
    if (filterRole.value) list = list.filter(a => a.role === filterRole.value);
    if (filterStatus.value) list = list.filter(a => (filterStatus.value === "active" && a.is_active) || (filterStatus.value === "inactive" && !a.is_active));
    return list;
});
</script>
