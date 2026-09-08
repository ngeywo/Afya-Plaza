<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-alert v-if="saved" type="success" variant="tonal" class="mb-4" closable @click:close="saved = null">{{ saved }}</v-alert>

        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Operating Hours</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Weekly opening times for this facility.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-content-save" :loading="loading" @click="save">Save Hours</v-btn>
        </div>

        <v-card variant="outlined">
            <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
            <v-table v-if="!loading">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Opens</th>
                        <th>Closes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in hours" :key="row.day_of_week">
                        <td class="font-weight-medium">{{ row.day_name }}</td>
                        <td>
                            <v-select v-model="row.status" :items="[{ title: 'Open', value: 'open' }, { title: 'Closed', value: 'closed' }]" item-title="title" item-value="value" hide-details density="compact" style="max-width: 140px;"></v-select>
                        </td>
                        <td>
                            <v-text-field v-model="row.open_time" type="time" hide-details disabled :disabled="row.status !== 'open'" density="compact" style="max-width: 160px;"></v-text-field>
                        </td>
                        <td>
                            <v-text-field v-model="row.close_time" type="time" hide-details disabled :disabled="row.status !== 'open'" density="compact" style="max-width: 160px;"></v-text-field>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { facilityWorkspaceService } from "../../services/facilityWorkspaceService";

const hours = ref([]);
const loading = ref(false);
const error = ref(null);
const saved = ref(null);

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function defaultTemplate() {
    return DAY_NAMES.map((name, i) => ({
        day_of_week: i,
        day_name: name,
        status: (i === 0 || i === 6) ? 'closed' : 'open',
        open_time: '08:00',
        close_time: '17:00',
    }));
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const data = await facilityWorkspaceService.getOperatingHours();
        if (!data || data.length === 0) {
            hours.value = defaultTemplate();
        } else {
            hours.value = DAY_NAMES.map((name, i) => {
                const existing = data.find((d) => d.day_of_week === i);
                return existing || defaultTemplate()[i];
            });
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load operating hours.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    loading.value = true;
    error.value = null;
    saved.value = null;
    try {
        await facilityWorkspaceService.saveOperatingHours(hours.value);
        saved.value = 'Operating hours saved.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to save operating hours.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>