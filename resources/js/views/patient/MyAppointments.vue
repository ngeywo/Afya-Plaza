<template>
    <v-container class="py-8" max-width="960">
        <div class="d-flex align-center mb-6">
            <v-icon icon="mdi-calendar-check" color="primary" size="32" class="mr-3"></v-icon>
            <h1 class="text-h4 font-weight-bold">My Appointments</h1>
        </div>

        <v-alert v-if="!auth.isAuthenticated" type="info" variant="tonal" class="mb-4">
            Please <router-link to="/login" class="font-weight-medium">sign in</router-link> to view your appointments.
        </v-alert>

        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>

        <template v-else-if="auth.isAuthenticated">
            <v-row v-if="appointments.length > 0">
                <v-col v-for="apt in appointments" :key="apt.id" cols="12" md="6">
                    <v-card variant="outlined" class="pa-4 h-100">
                        <div class="d-flex align-start justify-space-between mb-3">
                            <div>
                                <div class="text-caption text-medium-emphasis">Appointment #</div>
                                <div class="text-subtitle-2 font-weight-medium">{{ apt.appointment_number }}</div>
                            </div>
                            <v-chip :color="statusColor(apt.status)" size="small" variant="tonal">
                                {{ apt.status }}
                            </v-chip>
                        </div>
                        <v-divider class="my-2"></v-divider>
                        <div class="d-flex align-center mb-2">
                            <v-icon icon="mdi-doctor" size="small" color="primary" class="mr-2"></v-icon>
                            <span class="text-body-2 font-weight-medium">{{ apt.doctor?.name }}</span>
                        </div>
                        <div class="d-flex align-center mb-2">
                            <v-icon icon="mdi-calendar" size="small" color="medium-emphasis" class="mr-2"></v-icon>
                            <span class="text-body-2">{{ formatDate(apt.appointment_date) }} at {{ apt.start_time?.slice(0,5) }}</span>
                        </div>
                        <div class="d-flex align-center mb-2">
                            <v-icon icon="mdi-map-marker" size="small" color="medium-emphasis" class="mr-2"></v-icon>
                            <span class="text-body-2">{{ apt.facility?.name }}, {{ apt.facility?.city }}</span>
                        </div>
                        <div v-if="apt.reason" class="text-caption text-medium-emphasis mt-2">
                            <v-icon icon="mdi-text" size="small" class="mr-1"></v-icon>{{ apt.reason }}
                        </div>
                        <v-divider class="my-3"></v-divider>
                        <div class="d-flex align-center">
                            <span class="text-body-2 text-medium-emphasis">Fee paid</span>
                            <v-spacer></v-spacer>
                            <span class="text-body-1 font-weight-bold text-primary">KSh {{ apt.amount_paid }}</span>
                        </div>
                        <v-btn v-if="canCancel(apt)" block color="error" variant="outlined" size="small" class="mt-3" :loading="cancelling === apt.id" @click="cancelApt(apt)">
                            Cancel appointment
                        </v-btn>
                    </v-card>
                </v-col>
            </v-row>
            <v-card v-else variant="outlined" class="pa-12 text-center">
                <v-icon icon="mdi-calendar-blank-outline" size="80" color="medium-emphasis" class="mb-4"></v-icon>
                <h2 class="text-h6 font-weight-bold mb-2">No appointments yet</h2>
                <p class="text-body-2 text-medium-emphasis mb-4">Find a doctor and book your first visit.</p>
                <v-btn color="primary" :to="'/'" size="large">Find a doctor</v-btn>
            </v-card>
        </template>
    </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "../../stores/authStore";
import { useAppointmentStore } from "../../stores/appointmentStore";

const auth = useAuthStore();
const aptStore = useAppointmentStore();

const loading = computed(() => aptStore.loading);
const appointments = computed(() => aptStore.appointments);
const cancelling = ref(null);

async function load() {
    if (auth.isAuthenticated) await aptStore.fetchAppointments();
}

onMounted(load);
watch(() => auth.isAuthenticated, load);

function statusColor(status) {
    return { pending: "warning", confirmed: "success", cancelled: "error", completed: "info" }[status] || "default";
}

function canCancel(apt) {
    return ["pending", "confirmed"].includes(apt.status);
}

async function cancelApt(apt) {
    if (!confirm("Cancel this appointment?")) return;
    cancelling.value = apt.id;
    try {
        await aptStore.cancel(apt.id);
    } catch (e) {
        alert(e.response?.data?.message || "Failed to cancel");
    } finally {
        cancelling.value = null;
    }
}

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString("en-KE", { weekday: "short", day: "numeric", month: "short", year: "numeric" }) : "";
}
</script>
