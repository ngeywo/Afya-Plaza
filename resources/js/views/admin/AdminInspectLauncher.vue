<template>
    <div>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAdminWorkspaceStore } from "../../stores/adminWorkspaceStore";

const store = useAdminWorkspaceStore();
const route = useRoute();
const router = useRouter();
const loading = ref(false);
const error = ref(null);

onMounted(async () => {
    const type = route.params.type;
    const id = route.params.id;

    // Already inspecting this workspace?
    if (store.isInspecting && store.inspectionType === type && String(store.inspectionId) === String(id)) {
        router.replace(buildRedirect());
        return;
    }

    loading.value = true;
    error.value = null;
    try {
        if (type === "doctor") {
            await store.inspectDoctor(id);
        } else {
            await store.inspectFacility(id);
        }
        router.replace(buildRedirect());
    } catch (e) {
        error.value = e.response?.data?.message || "Failed to inspect workspace";
        loading.value = false;
    }
});

function buildRedirect() {
    return `/admin/inspect/${route.params.type}/${route.params.id}/sessions`;
}
</script>
