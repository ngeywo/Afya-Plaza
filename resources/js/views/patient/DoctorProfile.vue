<template>
    <div class="py-8">
        <v-container>
            <div v-if="loading"><v-skeleton-loader type="article"></v-skeleton-loader></div>
            <template v-else-if="doctor">
                <div class="d-flex flex-wrap align-start gap-6 mb-8">
                    <v-avatar size="100" color="primary"><span class="text-h4 font-weight-bold text-white">{{ initials }}</span></v-avatar>
                    <div class="flex-grow-1">
                        <div class="d-flex align-center gap-2 mb-2 flex-wrap">
                            <h1 class="text-h4 font-weight-bold">{{ doctor.name }}</h1>
                            <v-chip v-if="doctor.is_verified" color="success" variant="tonal" size="small">Verified</v-chip>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <v-chip v-for="s in doctor.specialties" :key="s.name" :prepend-icon="s.icon" variant="tonal" size="small">{{ s.name }}</v-chip>
                        </div>
                        <p class="text-body-2 text-medium-emphasis">{{ doctor.qualifications }} - {{ doctor.years_of_experience }}+ years</p>
                    </div>
                    <v-btn v-if="hasAvailableSessions" color="primary" size="x-large" prepend-icon="mdi-calendar-plus" @click="bookingOpen = true">Book Appointment</v-btn>
                </div>
                <v-alert v-if="!hasAvailableSessions" type="info" variant="tonal" class="mb-6">No open clinic sessions in the next 2 weeks. Check back later.</v-alert>
                <h2 class="text-h5 font-weight-bold mb-4"><v-icon icon="mdi-calendar-clock" class="mr-2" color="primary"></v-icon>Upcoming Clinics</h2>
                <v-row v-if="doctor.sessions && doctor.sessions.length" class="mb-6">
                    <v-col v-for="s in doctor.sessions" :key="s.id" cols="12" sm="6" md="4">
                        <v-card variant="outlined" class="pa-4 h-100">
                            <div class="d-flex align-center mb-2">
                                <v-avatar color="primary" variant="tonal" size="44"><div class="text-center"><div class="text-caption">{{ formatDay(s.session_date).slice(0,3) }}</div><div class="text-body-2 font-weight-bold">{{ new Date(s.session_date).getDate() }}</div></div></v-avatar>
                                <div class="ml-3">
                                    <div class="text-subtitle-2 font-weight-bold">{{ formatDate(s.session_date) }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ s.start_time }} - {{ s.end_time }}</div>
                                </div>
                            </div>
                            <v-divider class="my-2"></v-divider>
                            <div class="d-flex align-center mb-2"><v-icon icon="mdi-hospital-building" size="small" color="medium-emphasis" class="mr-2"></v-icon><span class="text-body-2">{{ s.facility.name }}</span></div>
                            <div class="d-flex align-center mb-3"><v-icon icon="mdi-map-marker" size="small" color="medium-emphasis" class="mr-2"></v-icon><span class="text-body-2 text-medium-emphasis">{{ s.facility.city }}</span></div>
                            <v-chip :color="slotsLeft(s) > 0 ? 'success' : 'error'" size="small" variant="tonal" :prepend-icon="slotsLeft(s) > 0 ? 'mdi-check-circle' : 'mdi-close-circle'">{{ slotsLeft(s) }} / {{ s.max_appointments }} slots</v-chip>
                        </v-card>
                    </v-col>
                </v-row>
                <v-card v-else class="mb-6 text-center pa-6" elevation="1">
                    <v-icon icon="mdi-calendar-remove" size="40" color="grey" class="mb-2"></v-icon>
                    <h3 class="text-h6 font-weight-bold">No upcoming clinics</h3>
                    <p class="text-body-2 text-medium-emphasis">Schedule not yet published.</p>
                </v-card>
                <v-card><v-card-title class="text-h6 font-weight-bold">About</v-card-title><v-divider></v-divider><v-card-text class="pt-4">{{ doctor.biography }}</v-card-text></v-card>
            </template>
            <BookingDialog v-if="doctor" v-model="bookingOpen" :doctor="doctor" @booked="handleBooked" />
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useDoctorStore } from "../../stores/doctorStore";
import { useAuthStore } from "../../stores/authStore";
import BookingDialog from "../../components/BookingDialog.vue";

const route = useRoute();
const router = useRouter();
const store = useDoctorStore();
const auth = useAuthStore();

const loading = ref(false);
const bookingOpen = ref(false);

const doctor = computed(() => store.currentDoctor);
const initials = computed(() => doctor.value ? doctor.value.name.split(" ").map(n => n[0]).join("").slice(0, 2) : "");
const hasAvailableSessions = computed(() => (doctor.value?.sessions || []).some(s => s.is_active && s.booked_appointments < s.max_appointments));

function slotsLeft(s) { return Math.max(0, s.max_appointments - s.booked_appointments); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString("en-KE", { weekday: "short", day: "numeric", month: "short" }) : ""; }
function formatDay(d) { return d ? new Date(d).toLocaleDateString("en-KE", { weekday: "long" }) : ""; }

function handleBooked() {
    store.find(route.params.slug);
    if (auth.isAuthenticated) router.push("/my-appointments");
}

async function load() {
    loading.value = true;
    try { await store.find(route.params.slug); }
    finally { loading.value = false; }
}
onMounted(load);
watch(() => route.params.slug, load);
</script>