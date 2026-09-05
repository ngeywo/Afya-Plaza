<template>
    <div class="py-8">
        <v-container>
            <div v-if="loading"><v-skeleton-loader type="article"></v-skeleton-loader></div>
            <template v-else-if="doctor">
                <!-- Doctor Header -->
                <div class="d-flex flex-wrap align-start gap-6 mb-6">
                    <v-avatar size="100" color="primary">
                        <v-img v-if="doctor.avatar" :src="doctor.avatar" :alt="doctor.name"></v-img>
                        <span v-else class="text-h4 font-weight-bold text-white">{{ initials }}</span>
                    </v-avatar>
                    <div class="flex-grow-1">
                        <div class="d-flex align-center gap-2 mb-2 flex-wrap">
                            <h1 class="text-h4 font-weight-bold" style="color:#0F172A">{{ doctor.name }}</h1>
                            <v-chip v-if="doctor.is_verified" color="success" variant="tonal" size="small" prepend-icon="mdi-check-decagram">Verified Doctor</v-chip>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <v-chip v-for="s in doctor.specialties" :key="s.name" :prepend-icon="s.icon" variant="tonal" size="small">{{ s.name }}</v-chip>
                        </div>
                        <p class="text-body-2 text-medium-emphasis">{{ doctor.qualifications }} - {{ doctor.years_of_experience }}+ years</p>
                    </div>
                    <div class="ml-auto d-flex align-start pt-1">
                        <!-- Phase 10: Follow button (patients only) -->
                        <FollowDoctorButton
                            v-if="auth.isPatient"
                            :doctor-id="doctor.id"
                            :initial-following="doctor.is_following || false"
                        />
                    </div>
                </div>
                <!-- Phase 8: WHERE TO FIND THIS DOCTOR (DoctorClinicFinder component) -->
                <DoctorClinicFinder :doctor="doctor" class="mb-6" />

                <h2 class="text-h5 font-weight-bold mb-4" style="color:#0F172A"><v-icon icon="mdi-calendar-clock" class="mr-2" color="primary"></v-icon>Upcoming Clinics</h2>
                <v-row v-if="upcomingClinics.length" class="mb-6">
                    <v-col v-for="s in upcomingClinics" :key="s.id" cols="12" sm="6" md="4">
                        <v-card variant="outlined" class="pa-4 h-100">
                            <div class="d-flex align-center justify-space-between mb-2">
                                <div class="d-flex align-center">
                                    <v-avatar color="primary" variant="tonal" size="44">
                                        <div class="text-center">
                                            <div class="text-caption">{{ s.day.slice(0,3) }}</div>
                                            <div class="text-body-2 font-weight-bold">{{ s.day_short?.split(',')[1]?.trim() || '' }}</div>
                                        </div>
                                    </v-avatar>
                                    <div class="ml-3">
                                        <div class="text-subtitle-2 font-weight-bold">{{ s.day }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ s.start_time }} - {{ s.end_time }}</div>
                                    </div>
                                </div>
                                <v-chip size="x-small" variant="tonal" :color="s.is_confirmed ? 'success' : 'warning'">
                                    {{ s.is_confirmed ? 'Confirmed' : 'Pending' }}
                                </v-chip>
                            </div>
                            <v-divider class="my-2"></v-divider>
                            <div class="d-flex align-center mb-2"><v-icon icon="mdi-hospital-building" size="small" color="medium-emphasis" class="mr-2"></v-icon><span class="text-body-2">{{ s.facility.name }}</span></div>
                            <div class="d-flex align-center mb-3"><v-icon icon="mdi-map-marker" size="small" color="medium-emphasis" class="mr-2"></v-icon><span class="text-body-2 text-medium-emphasis">{{ s.facility.city }}</span></div>
                            <v-chip :color="s.available_slots > 0 ? 'success' : 'error'" size="small" variant="tonal" :prepend-icon="s.available_slots > 0 ? 'mdi-check-circle' : 'mdi-close-circle'">{{ s.available_slots }} / {{ s.max_appointments }} slots</v-chip>
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
import DoctorClinicFinder from "../../components/patient/DoctorClinicFinder.vue";
import FollowDoctorButton from "../../components/FollowDoctorButton.vue";

const route = useRoute();
const router = useRouter();
const store = useDoctorStore();
const auth = useAuthStore();

const loading = ref(false);
const bookingOpen = ref(false);

const doctor = computed(() => store.currentDoctor);
const initials = computed(() => doctor.value ? doctor.value.name.split(" ").map(n => n[0]).join("").slice(0, 2) : "");
const upcomingClinics = computed(() => {
    const today = new Date().toISOString().slice(0, 10);
    return (doctor.value?.upcoming_sessions || []).filter(s => s.session_date >= today).slice(0, 6);
});

function handleBooked() {
    store.find(route.params.slug);
    if (auth.isAuthenticated) router.push("/my-appointments");
}

async function load() {
    loading.value = true;
    try {
        await store.find(route.params.slug);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.params.slug, load);
</script>
