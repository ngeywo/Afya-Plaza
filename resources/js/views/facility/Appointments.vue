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
                <ClinicDayBoard ref="boardRef" :fetch-fn="fetchBoard" />
                <template #actions="{ appointment }">
                    <CheckInButton :appointment="appointment" :on-check-in="onCheckIn" />
                </template>
            </v-window-item>

            <v-window-item value="list">
                <v-card variant="outlined" class="mb-4 pa-4">
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="date" type="date" label="Date" density="comfortable" variant="outlined" @update:model-value="reload" hide-details></v-text-field>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-select v-model="doctorId" :items="doctorOptions" item-title="name" item-value="id" label="Doctor" density="comfortable" variant="outlined" clearable @update:model-value="reload" hide-details></v-select>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-select v-model="status" :items="statusOptions" label="Status" density="comfortable" variant="outlined" clearable @update:model-value="reload" hide-details></v-select>
                        </v-col>
                    </v-row>
                </v-card>

                <v-progress-linear v-if="store.loading && store.appointments.length === 0" indeterminate></v-progress-linear>

                <v-card v-if="!store.loading && store.appointments.length === 0" variant="outlined" class="pa-8 text-center">
                    <v-icon icon="mdi-calendar-blank-outline" size="64" color="medium-emphasis" class="mb-3"></v-icon>
                    <h3 class="text-h6 font-weight-bold mb-2">No appointments</h3>
                    <p class="text-body-2 text-medium-emphasis">No appointments match these filters.</p>
                </v-card>

                <v-card v-for="apt in store.appointments" :key="apt.id" variant="outlined" class="mb-2">
                    <div class="pa-4 d-flex align-center flex-wrap">
                        <div class="text-right mr-4" style="min-width:60px;">
                            <div class="text-caption text-medium-emphasis">{{ apt.date }}</div>
                            <div class="text-h6 font-weight-bold">{{ apt.start_time }}</div>
                        </div>
                        <v-divider vertical class="mr-4"></v-divider>
                        <div class="flex-grow-1">
                            <div class="text-h6 font-weight-bold">{{ apt.doctor?.name }}</div>
                            <div class="text-caption text-medium-emphasis">{{ apt.appointment_number }} · {{ apt.reason || 'General' }}</div>
                        </div>
                        <AppointmentStatusChip :status="apt.status" class="mr-2" />
                        <CheckInButton :appointment="apt" :on-check-in="onCheckInList" />
                    </div>
                </v-card>
            </v-window-item>
        </v-window>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
import ClinicDayBoard from "../../components/ClinicDayBoard.vue";
import CheckInButton from "../../components/CheckInButton.vue";
import AppointmentStatusChip from "../../components/AppointmentStatusChip.vue";

const store = useFacilityWorkspaceStore();
const activeTab = ref("board");
const boardRef = ref(null);
const date = ref(new Date().toISOString().slice(0, 10));
const doctorId = ref(null);
const status = ref(null);

const statusOptions = ["pending", "confirmed", "checked_in", "in_progress", "completed", "cancelled", "no_show"];
const doctorOptions = computed(() => store.appointmentDoctors);

async function fetchBoard(d) { return await store.fetchClinicDay(d); }

async function onCheckIn(apt) {
    await store.checkInAppointment(apt.id);
    boardRef.value?.load();
}

async function onCheckInList(apt) {
    await store.checkInAppointment(apt.id);
    reload();
}

onMounted(() => {
    if (activeTab.value === "board") {
        boardRef.value?.load();
    } else {
        reload();
    }
});

function reload() {
    store.fetchAppointments({
        date: date.value,
        doctor_id: doctorId.value || undefined,
        status: status.value || undefined,
    });
}
</script>