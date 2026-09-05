<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-alert v-if="store.success" type="success" variant="tonal" class="mb-4" closable @click:close="store.success = null">{{ store.success }}</v-alert>
        <h1 class="text-h5 font-weight-bold mb-4">Appointments</h1>
        <v-tabs v-model="activeTab" color="primary" class="mb-4">
            <v-tab value="board"><v-icon start>mdi-clipboard-list</v-icon>Clinic Day Board</v-tab>
            <v-tab value="list"><v-icon start>mdi-format-list-bulleted</v-icon>Appointment List</v-tab>
        </v-tabs>
        <v-window v-model="activeTab">
            <v-window-item value="board">
                <ClinicDayBoard ref="boardRef" :fetch-fn="fetchBoard">
                    <template #actions="{ appointment }">
                        <v-btn v-if="appointment.status === 'checked_in'" color="amber" size="small" variant="tonal" prepend-icon="mdi-play" :loading="startingId === appointment.id" @click="startConsultation(appointment)">Start</v-btn>
                        <v-btn v-else-if="appointment.status === 'in_progress'" color="green" size="small" variant="tonal" prepend-icon="mdi-check" :loading="completingId === appointment.id" @click="completeConsultation(appointment)">Complete</v-btn>
                        <AppointmentStatusChip v-else :status="appointment.status" />
                    </template>
                </ClinicDayBoard>
            </v-window-item>
            <v-window-item value="list">
                <v-card variant="outlined" class="pa-3 mb-4">
                    <v-row dense>
                        <v-col cols="12" sm="4"><v-text-field v-model="filters.date" type="date" label="Date" variant="outlined" density="compact" hide-details clearable @update:model-value="load"></v-text-field></v-col>
                        <v-col cols="12" sm="4"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="load"></v-select></v-col>
                        <v-col cols="12" sm="4" class="d-flex align-center"><v-btn variant="outlined" block @click="reset">Reset Filters</v-btn></v-col>
                    </v-row>
                </v-card>
                <v-progress-linear v-if="store.loading && store.appointments.length === 0" indeterminate></v-progress-linear>
                <v-card v-if="!store.loading && store.appointments.length === 0" variant="outlined" class="pa-12 text-center">
                    <v-icon icon="mdi-calendar-blank" size="64" color="medium-emphasis" class="mb-3"></v-icon>
                    <h2 class="text-h6 font-weight-bold mb-2">No appointments found</h2>
                    <p class="text-body-2 text-medium-emphasis">No appointments match your current filters.</p>
                </v-card>
                <v-card v-else variant="outlined">
                    <v-list>
                        <v-list-item v-for="apt in store.appointments" :key="apt.id">
                            <template #prepend>
                                <v-avatar size="40" color="primary" class="mr-3"><span class="text-white">{{ apt.patient?.name?.[0] ?? '?' }}</span></v-avatar>
                            </template>
                            <v-list-item-title class="font-weight-medium">
                                {{ apt.patient?.name }}
                                <AppointmentStatusChip :status="apt.status" class="ml-2" />
                            </v-list-item-title>
                            <v-list-item-subtitle>{{ apt.day }} · {{ apt.start_time }} – {{ apt.end_time }} · {{ apt.facility?.name }}</v-list-item-subtitle>
                            <v-list-item-subtitle v-if="apt.reason" class="text-caption"><v-icon icon="mdi-text" size="12"></v-icon> {{ apt.reason }}</v-list-item-subtitle>
                            <template #append>
                                <v-btn v-if="apt.status === 'checked_in'" color="amber" size="small" variant="tonal" prepend-icon="mdi-play" :loading="startingId === apt.id" @click="startConsultation(apt)" class="mr-2">Start</v-btn>
                                <v-btn v-else-if="apt.status === 'in_progress'" color="green" size="small" variant="tonal" prepend-icon="mdi-check" :loading="completingId === apt.id" @click="completeConsultation(apt)" class="mr-2">Complete</v-btn>
                                <span class="text-caption text-medium-emphasis">#{{ apt.appointment_number }}</span>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </v-window-item>
        </v-window>
    </div>
import { ref, onMounted } from "vue";
import { useDoctorWorkspaceStore } from "../../stores/doctorWorkspaceStore";
import ClinicDayBoard from "../../components/ClinicDayBoard.vue";
import AppointmentStatusChip from "../../components/AppointmentStatusChip.vue";

const store = useDoctorWorkspaceStore();
const activeTab = ref("board");
const boardRef = ref(null);
const startingId = ref(null);
const completingId = ref(null);
const filters = ref({ date: '', status: null });
const statusOptions = ["pending", "confirmed", "checked_in", "in_progress", "completed", "cancelled", "no_show"];

async function fetchBoard(d) { return await store.fetchClinicDay(d); }

async function startConsultation(apt) {
    startingId.value = apt.id;
    try { await store.startConsultationAppointment(apt.id); boardRef.value?.load(); reload(); }
    catch (e) {} finally { startingId.value = null; }
}

async function completeConsultation(apt) {
    completingId.value = apt.id;
    try { await store.completeConsultationAppointment(apt.id); boardRef.value?.load(); reload(); }
    catch (e) {} finally { completingId.value = null; }
}

function load() {
    const params = {};
    if (filters.value.date) params.date = filters.value.date;
    if (filters.value.status) params.status = filters.value.status;
    store.fetchAppointments(params);
}

function reload() { load(); }
function reset() { filters.value = { date: '', status: null }; load(); }

onMounted(() => {
    if (activeTab.value === "board") boardRef.value?.load();
    else load();
});

</template>