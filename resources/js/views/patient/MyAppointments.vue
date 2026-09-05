<template>
    <v-container class="py-8" max-width="960">
        <div class="d-flex align-center mb-6">
            <v-icon icon="mdi-calendar-check" color="primary" size="32" class="mr-3"></v-icon>
            <h1 class="text-h4 font-weight-bold">My Appointments</h1>
        </div>

        <v-alert v-if="!auth.isAuthenticated" type="info" variant="tonal" class="mb-4">
            Please <router-link to="/login" class="font-weight-medium">sign in</router-link> to view your appointments.
        </v-alert>

        <template v-else-if="auth.isAuthenticated">
            <v-tabs v-model="activeTab" color="primary" class="mb-4">
                <v-tab value="upcoming">
                    Upcoming
                    <v-chip v-if="upcomingAppointments.length" size="x-small" color="primary" variant="tonal" class="ml-2">
                        {{ upcomingAppointments.length }}
                    </v-chip>
                </v-tab>
                <v-tab value="past">
                    Past
                    <v-chip v-if="pastAppointments.length" size="x-small" color="medium-emphasis" variant="tonal" class="ml-2">
                        {{ pastAppointments.length }}
                    </v-chip>
                </v-tab>
                <v-tab value="cancelled">
                    Cancelled
                    <v-chip v-if="cancelledAppointments.length" size="x-small" color="error" variant="tonal" class="ml-2">
                        {{ cancelledAppointments.length }}
                    </v-chip>
                </v-tab>
            </v-tabs>

            <v-progress-linear v-if="loading" indeterminate></v-progress-linear>

            <v-window v-else v-model="activeTab">
                <v-window-item value="upcoming">
                    <v-row v-if="upcomingAppointments.length > 0">
                        <v-col v-for="apt in upcomingAppointments" :key="apt.id" cols="12" md="6">
                            <AppointmentCard :apt="apt" @cancel="handleCancel" @reschedule="handleReschedule" :cancelling="cancelling" />
                        </v-col>
                    </v-row>
                    <EmptyState v-else icon="mdi-calendar-check-outline" title="No upcoming appointments" body="Find a doctor and book your next visit." action-label="Find a doctor" :action-to="'/'" />
                </v-window-item>

                <v-window-item value="past">
                    <v-row v-if="pastAppointments.length > 0">
                        <v-col v-for="apt in pastAppointments" :key="apt.id" cols="12" md="6">
                            <AppointmentCard :apt="apt" :cancelling="cancelling" />
                        </v-col>
                    </v-row>
                    <EmptyState v-else icon="mdi-history" title="No past appointments" body="Your completed visits will appear here." />
                </v-window-item>

                <v-window-item value="cancelled">
                    <v-row v-if="cancelledAppointments.length > 0">
                        <v-col v-for="apt in cancelledAppointments" :key="apt.id" cols="12" md="6">
                            <AppointmentCard :apt="apt" :cancelling="cancelling" />
                        </v-col>
                    </v-row>
                    <EmptyState v-else icon="mdi-calendar-remove-outline" title="No cancelled appointments" body="Appointments you cancel will appear here." />
                </v-window-item>
            </v-window>
        </template>
    </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../stores/authStore";
import { useAppointmentStore } from "../../stores/appointmentStore";
import AppointmentCard from "../../components/patient/AppointmentCard.vue";
import EmptyState from "../../components/EmptyState.vue";

const auth = useAuthStore();
const aptStore = useAppointmentStore();
const router = useRouter();

const activeTab = ref("upcoming");
const cancelling = ref(null);

const loading = computed(() => aptStore.loading);
const appointments = computed(() => aptStore.appointments);
const today = new Date().toISOString().slice(0, 10);

const upcomingAppointments = computed(() =>
    appointments.value.filter(a =>
        (a.status === "pending" || a.status === "confirmed") &&
        a.appointment_date >= today
    )
);
const pastAppointments = computed(() =>
    appointments.value.filter(a =>
        a.appointment_date < today &&
        a.status !== "cancelled"
    )
);
const cancelledAppointments = computed(() =>
    appointments.value.filter(a => a.status === "cancelled")
);

async function load() {
    if (auth.isAuthenticated) await aptStore.fetchAppointments();
}

onMounted(load);
watch(() => auth.isAuthenticated, load);

async function handleCancel(apt) {
    if (!confirm("Cancel this appointment?")) return;
    cancelling.value = apt.id;
    try {
        await aptStore.cancel(apt.id);
    } catch (e) {
        alert(e.response?.data?.error || "Failed to cancel appointment.");
    } finally {
        cancelling.value = null;
    }
}

// Phase 17: reschedule flows through the appointment detail page,
// where the RescheduleDialog offers real availability alternatives.
function handleReschedule(apt) {
    router.push(`/appointments/${apt.id}`);
}
</script>