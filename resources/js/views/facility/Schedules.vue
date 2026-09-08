<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>

        <div class="mb-4">
            <h1 class="text-h5 font-weight-bold mb-0">Schedules</h1>
            <p class="text-body-2 text-medium-emphasis mb-0">Recurring weekly practice patterns for doctors at this facility.</p>
        </div>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <v-card v-if="!loading && schedules.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-calendar-repeat" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No schedules yet</h3>
            <p class="text-body-2 text-medium-emphasis">Weekly schedules are set up per doctor. Once a doctor adds recurring session times they appear here.</p>
        </v-card>

        <v-card v-if="!loading && schedules.length > 0" variant="outlined">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Doctor</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Slot</th>
                        <th>Capacity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in schedules" :key="s.id">
                        <td class="font-weight-medium">{{ s.day_name }}</td>
                        <td>{{ s.doctor?.name || '—' }}</td>
                        <td>{{ s.start_time }}</td>
                        <td>{{ s.end_time }}</td>
                        <td>{{ s.slot_duration_minutes }} min</td>
                        <td>{{ s.max_appointments }}</td>
                        <td>
                            <v-chip :color="s.is_active ? 'success' : 'default'" size="x-small" variant="tonal">{{ s.is_active ? 'Active' : 'Inactive' }}</v-chip>
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

const schedules = ref([]);
const loading = ref(false);
const error = ref(null);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        schedules.value = await facilityWorkspaceService.getSchedules();
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load schedules.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>