<template>
    <div>
        <v-row class="mb-3" align="center">
            <v-col cols="auto"><v-btn icon="mdi-chevron-left" variant="text" @click="prevDay" /></v-col>
            <v-col>
                <v-chip color="primary" prepend-icon="mdi-calendar-today" size="large" variant="elevated">{{ formattedDate }}</v-chip>
            </v-col>
            <v-col cols="auto"><v-btn icon="mdi-chevron-right" variant="text" @click="nextDay" /></v-col>
            <v-col cols="auto"><v-btn color="primary" variant="tonal" prepend-icon="mdi-reload" @click="load" :loading="loading">Refresh</v-btn></v-col>
        </v-row>

        <v-row v-if="data">
            <v-col cols="6" sm="4" md="2"><v-card color="blue" variant="tonal" class="text-center py-3"><div class="text-h5 font-weight-bold">{{ data.metrics.total }}</div><div class="text-caption">Total</div></v-card></v-col>
            <v-col cols="6" sm="4" md="2"><v-card color="teal" variant="tonal" class="text-center py-3"><div class="text-h5 font-weight-bold">{{ data.metrics.checked_in }}</div><div class="text-caption">Checked in</div></v-card></v-col>
            <v-col cols="6" sm="4" md="2"><v-card color="amber" variant="tonal" class="text-center py-3"><div class="text-h5 font-weight-bold">{{ data.metrics.in_progress }}</div><div class="text-caption">In progress</div></v-card></v-col>
            <v-col cols="6" sm="4" md="2"><v-card color="green" variant="tonal" class="text-center py-3"><div class="text-h5 font-weight-bold">{{ data.metrics.completed }}</div><div class="text-caption">Completed</div></v-card></v-col>
            <v-col cols="6" sm="4" md="2"><v-card color="deep-orange" variant="tonal" class="text-center py-3"><div class="text-h5 font-weight-bold">{{ data.metrics.no_show }}</div><div class="text-caption">No-show</div></v-card></v-col>
            <v-col cols="6" sm="4" md="2"><v-card color="grey" variant="tonal" class="text-center py-3"><div class="text-h5 font-weight-bold">{{ data.metrics.pending }}</div><div class="text-caption">Pending</div></v-card></v-col>
        </v-row>

        <v-row class="mt-3" v-if="data?.sessions?.length">
            <v-col cols="12">
                <h4 class="text-subtitle-1 font-weight-bold mb-2">Clinics</h4>
                <v-chip v-for="s in data.sessions" :key="s.id" class="mr-2 mb-1" :color="s.is_confirmed ? 'green' : 'grey'" variant="tonal" size="small">
                    <v-avatar start size="20"><v-icon size="small">mdi-doctor</v-icon></v-avatar>
                    {{ s.doctor?.name || s.facility?.name }} &bull; {{ s.start_time }}
                </v-chip>
            </v-col>
        </v-row>

        <v-row class="mt-3" v-if="data?.appointments?.length">
            <v-col cols="12">
                <h4 class="text-subtitle-1 font-weight-bold mb-2">Patients</h4>
                <v-table density="comfortable">
                    <thead><tr><th>Time</th><th>Patient</th><th>Doctor/Facility</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="apt in data.appointments" :key="apt.id">
                            <td class="text-caption">{{ apt.start_time }}</td>
                            <td><div class="font-weight-medium text-body-2">{{ apt.patient?.name }}</div><div class="text-caption text-grey">{{ apt.patient?.phone }}</div></td>
                            <td class="text-body-2">{{ apt.doctor?.name || apt.facility?.name }}</td>
                            <td class="text-caption">{{ apt.reason }}</td>
                            <td><AppointmentStatusChip :status="apt.status" /></td>
                            <td><slot name="actions" :appointment="apt" /></td>
                        </tr>
                    </tbody>
                </v-table>
            </v-col>
        </v-row>

        <v-alert v-if="data && !data.appointments?.length" type="info" variant="tonal" class="mt-3">No appointments for this day.</v-alert>
        <v-alert v-if="errorMsg" type="error" variant="tonal" class="mt-3" closable @click:close="errorMsg = null">{{ errorMsg }}</v-alert>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppointmentStatusChip from './AppointmentStatusChip.vue';

const props = defineProps({
    fetchFn: { type: Function, required: true },
});

const loading = ref(false);
const errorMsg = ref(null);
const currentDate = ref(new Date().toISOString().slice(0, 10));
const data = ref(null);

const formattedDate = computed(() => {
    if (!data.value?.date) return currentDate.value;
    return new Date(data.value.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});

function prevDay() {
    const d = new Date(currentDate.value + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    currentDate.value = d.toISOString().slice(0, 10);
    load();
}

function nextDay() {
    const d = new Date(currentDate.value + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    currentDate.value = d.toISOString().slice(0, 10);
    load();
}

async function load() {
    loading.value = true; errorMsg.value = null;
    try { data.value = await props.fetchFn(currentDate.value); }
    catch (e) { errorMsg.value = e.response?.data?.error || e.message || 'Load failed'; }
    finally { loading.value = false; }
}

defineExpose({ load, currentDate, data });
</script>