<template>
    <div class="audit-log-page">
        <AppPageHeader title="Audit Log" subtitle="System activity and changes" icon="mdi-history" />
        <v-card>
            <v-card-text>
                <v-data-table :headers="headers" :items="logs" :loading="loading" :items-length="meta.total" class="elevation-0">
                    <template #item.action="{ item }"><v-chip size="small" color="primary" variant="tonal">{{ item.action }}</v-chip></template>
                    <template #item.actor="{ item }"><span>{{ item.actor?.name || "System" }}</span></template>
                    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-history" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No audit events recorded yet.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import api from "../../services/api";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
const logs = ref([]);
const loading = ref(false);
const meta = ref({ total: 0 });
const headers = [
    { title: "Action", key: "action" },
    { title: "Resource", key: "resource_label" },
    { title: "Actor", key: "actor" },
    { title: "Date", key: "created_at" },
];
async function fetchLogs() {
    loading.value = true;
    try { const { data } = await api.get("/admin/audit/logs"); logs.value = data.data; meta.value = data.meta; }
    catch (err) { console.error(err); }
    finally { loading.value = false; }
}
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : "-"; }
onMounted(() => { fetchLogs(); });
</script>
