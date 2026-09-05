<template>
    <v-container class="py-8" max-width="720">
        <v-btn variant="text" color="medium-emphasis" size="small" class="mb-4 pl-0" prepend-icon="mdi-arrow-left" @click="goBack">
            Back
        </v-btn>
        <v-stepper :model-value="step" alt-labels class="mb-6">
            <v-stepper-header>
                <v-stepper-item :value="1" title="Clinic" :complete="step > 1" />
                <v-divider />
                <v-stepper-item :value="2" title="Time" :complete="step > 2" />
                <v-divider />
                <v-stepper-item :value="3" title="Details" :complete="step > 3" />
                <v-divider />
                <v-stepper-item :value="4" title="Confirm" :complete="step > 4" />
            </v-stepper-header>
        </v-stepper>
        <!-- STEP 1: Clinic Summary -->
        <template v-if="step === 1">
            <div v-if="sessionLoading" class="text-center py-12">
                <v-progress-circular indeterminate color="primary" />
                <p class="mt-3 text-medium-emphasis">Loading clinic details...</p>
            </div>
            <div v-else-if="sessionError" class="text-center py-12">
                <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-3" />
                <h3 class="text-h6 font-weight-bold mb-2">Clinic not found</h3>
                <p class="text-body-2 text-medium-emphasis">{{ sessionError }}</p>
                <v-btn color="primary" class="mt-4" to="/">Find another doctor</v-btn>
            </div>
            <div v-else-if="session">
                <h2 class="text-h5 font-weight-bold mb-1">Confirm your appointment</h2>
                <p class="text-body-2 text-medium-emphasis mb-6">Review the clinic details before choosing a time.</p>
                <v-card variant="outlined" class="clinic-summary-card mb-4">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center gap-3 mb-4">
                            <v-avatar size="52" color="primary">
                                <v-img v-if="session.doctor?.avatar" :src="session.doctor.avatar" />
                                <span v-else class="text-h6 font-weight-bold text-white">{{ doctorInitials }}</span>
                            </v-avatar>
                            <div>
                                <div class="text-h6 font-weight-bold">{{ session.doctor?.name }}</div>
                                <div class="text-body-2 text-medium-emphasis">{{ doctorSpecialty }}</div>
                            </div>
                        </div>
                        <v-divider class="my-3" />
                        <div class="d-flex align-center gap-2 mb-1">
                            <v-icon icon="mdi-hospital-building" size="18" color="primary" />
                            <span class="font-weight-medium">{{ session.facility?.name }}</span>
                        </div>
                        <div class="d-flex align-center gap-2 mb-3">
                            <v-icon icon="mdi-map-marker" size="16" color="medium-emphasis" />
                            <span class="text-body-2 text-medium-emphasis">
                                {{ session.facility?.city }}<span v-if="session.facility?.county">, {{ session.facility.county }}</span>
                            </span>
                        </div>
                        <div class="d-flex align-center gap-2 mb-2">
                            <v-icon icon="mdi-calendar" size="18" color="primary" />
                            <span class="font-weight-medium">{{ formatDate(session.session_date) }}</span>
                        </div>
                        <div class="d-flex align-center gap-2 mb-4">
                            <v-icon icon="mdi-clock-outline" size="18" color="medium-emphasis" />
                            <span class="text-body-2 text-medium-emphasis">{{ session.start_time?.slice(0,5) }} - {{ session.end_time?.slice(0,5) }}</span>
                        </div>
                        <div v-if="session.consultation_fee" class="d-flex align-center justify-space-between pa-3 rounded" style="background: rgb(var(--v-theme-surface)); border: 1px solid rgba(0,0,0,0.06);">
                            <span class="text-body-2 text-medium-emphasis">Consultation fee</span>
                            <span class="text-body-1 font-weight-bold text-primary">KSh {{ session.consultation_fee }}</span>
                        </div>
                    </v-card-text>
                </v-card>
                <v-btn color="primary" size="large" block :loading="slotsLoading" @click="loadSlots">
                    Choose a time <v-icon icon="mdi-arrow-right" end />
                </v-btn>
            </div>
        </template>
        <!-- STEP 2: Time Slots -->
        <template v-if="step === 2">
            <h2 class="text-h5 font-weight-bold mb-1">Select a time</h2>
            <p class="text-body-2 text-medium-emphasis mb-4">{{ formatDate(session?.session_date) }} at {{ session?.facility?.name }}</p>
            <v-btn variant="text" color="medium-emphasis" size="small" class="mb-4 pl-0" prepend-icon="mdi-arrow-left" @click="step = 1">
                Change clinic
            </v-btn>
            <div v-if="slotsLoading" class="text-center py-12">
                <v-progress-circular indeterminate color="primary" />
                <p class="mt-3 text-medium-emphasis">Loading available times...</p>
            </div>
            <div v-else-if="!availableSlots.length" class="text-center py-12">
                <v-icon icon="mdi-calendar-remove" size="64" color="error" class="mb-3" />
                <h3 class="text-h6 font-weight-bold mb-2">No available times</h3>
                <p class="text-body-2 text-medium-emphasis">This clinic is fully booked. Please choose another clinic date.</p>
                <v-btn color="primary" class="mt-4" @click="step = 1">Choose another clinic</v-btn>
            </div>
            <div v-else>
                <v-row>
                    <v-col v-for="slot in availableSlots" :key="slot.start_time" cols="4" sm="3">
                        <v-btn
                            block variant="outlined"
                            :color="selectedSlot === slot.start_time ? 'primary' : 'default'"
                            :class="selectedSlot === slot.start_time ? 'bg-primary text-white' : ''"
                            :disabled="!slot.is_available"
                            class="time-slot-btn mb-2"
                            @click="selectSlot(slot)"
                        >
                            <div class="text-center">
                                <div class="text-body-2 font-weight-medium">{{ formatTime(slot.start_time) }}</div>
                                <div v-if="!slot.is_available" class="text-caption">Booked</div>
                            </div>
                        </v-btn>
                    </v-col>
                </v-row>
                <v-alert v-if="selectedSlot" type="success" variant="tonal" class="mt-4 mb-2">
                    <strong>{{ formatTime(selectedSlot) }}</strong> selected
                </v-alert>
                <v-btn color="primary" size="large" block :disabled="!selectedSlot" @click="step = 3">
                    Continue <v-icon icon="mdi-arrow-right" end />
                </v-btn>
            </div>
        </template>
        <!-- STEP 3: Patient Details -->
        <template v-if="step === 3">
            <h2 class="text-h5 font-weight-bold mb-1">Your details</h2>
            <p class="text-body-2 text-medium-emphasis mb-4">Complete your booking information.</p>
            <v-btn variant="text" color="medium-emphasis" size="small" class="mb-4 pl-0" prepend-icon="mdi-arrow-left" @click="step = 2">
                Change time
            </v-btn>
            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-4">
                    <div class="text-caption text-medium-emphasis mb-2">APPOINTMENT SUMMARY</div>
                    <div class="d-flex align-center gap-2 mb-1">
                        <v-icon icon="mdi-doctor" size="16" color="primary" />
                        <span class="text-body-2 font-weight-medium">{{ session?.doctor?.name }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-1">
                        <v-icon icon="mdi-calendar" size="16" color="medium-emphasis" />
                        <span class="text-body-2 text-medium-emphasis">{{ formatDate(session?.session_date) }}</span>
                    </div>
                    <div class="d-flex align-center gap-2">
                        <v-icon icon="mdi-clock-outline" size="16" color="medium-emphasis" />
                        <span class="text-body-2 text-medium-emphasis">{{ formatTime(selectedSlot) }}</span>
                    </div>
                </v-card-text>
            </v-card>
            <v-text-field v-model="patientName" label="Full name" prepend-inner-icon="mdi-account"
                variant="outlined" density="comfortable" :rules="[v => !!v || 'Required']" class="mb-2" />
            <v-text-field v-model="patientPhone" label="Phone number" prepend-inner-icon="mdi-phone"
                variant="outlined" density="comfortable" :rules="[v => !!v || 'Required']" class="mb-2" />
            <v-text-field v-model="patientEmail" label="Email (optional)" prepend-inner-icon="mdi-email"
                variant="outlined" density="comfortable" type="email" class="mb-2" />
            <v-textarea v-model="appointmentReason" label="Reason for visit (optional)"
                prepend-inner-icon="mdi-text" variant="outlined" density="comfortable"
                rows="2" counter="1000" maxlength="1000" class="mb-2" />
            <v-btn color="primary" size="large" block :disabled="!patientName || !patientPhone" @click="goToReview">
                Review booking <v-icon icon="mdi-arrow-right" end />
            </v-btn>
        </template>
        <!-- STEP 4: Review & Confirm -->
        <template v-if="step === 4">
            <h2 class="text-h5 font-weight-bold mb-1">Review your appointment</h2>
            <p class="text-body-2 text-medium-emphasis mb-4">Please confirm all details are correct.</p>
            <v-btn variant="text" color="medium-emphasis" size="small" class="mb-4 pl-0" prepend-icon="mdi-arrow-left" @click="step = 3">
                Edit details
            </v-btn>
            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-5">
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-doctor" size="18" color="primary" />
                        <div>
                            <div class="text-body-2 font-weight-medium">{{ session?.doctor?.name }}</div>
                            <div class="text-caption text-medium-emphasis">{{ doctorSpecialty }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-hospital-building" size="18" color="medium-emphasis" />
                        <div>
                            <div class="text-body-2 font-weight-medium">{{ session?.facility?.name }}</div>
                            <div class="text-caption text-medium-emphasis">{{ session?.facility?.city }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-calendar" size="18" color="medium-emphasis" />
                        <div>
                            <div class="text-body-2 font-weight-medium">{{ formatDate(session?.session_date) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ formatTime(selectedSlot) }}</div>
                        </div>
                    </div>
                    <v-divider class="my-3" />
                    <div class="text-caption text-medium-emphasis mb-2">PATIENT</div>
                    <div class="d-flex align-center gap-2 mb-2">
                        <v-icon icon="mdi-account" size="16" color="medium-emphasis" />
                        <span class="text-body-2">{{ patientName }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-2">
                        <v-icon icon="mdi-phone" size="16" color="medium-emphasis" />
                        <span class="text-body-2">{{ patientPhone }}</span>
                    </div>
                    <div v-if="patientEmail" class="d-flex align-center gap-2 mb-2">
                        <v-icon icon="mdi-email" size="16" color="medium-emphasis" />
                        <span class="text-body-2">{{ patientEmail }}</span>
                    </div>
                    <div v-if="appointmentReason" class="d-flex align-center gap-2 mb-2">
                        <v-icon icon="mdi-text" size="16" color="medium-emphasis" />
                        <span class="text-body-2">{{ appointmentReason }}</span>
                    </div>
                    <v-divider class="my-3" />
                    <div class="d-flex align-center justify-space-between pa-3 rounded" style="background: rgb(var(--v-theme-surface)); border: 1px solid rgba(0,0,0,0.06);">
                        <span class="text-body-2 text-medium-emphasis">Consultation fee</span>
                        <span class="text-h6 font-weight-bold text-primary">KSh {{ session?.consultation_fee }}</span>
                    </div>
                </v-card-text>
            </v-card>
            <v-btn color="primary" size="large" block :loading="booking" @click="confirmBooking">
                Confirm appointment
            </v-btn>
            <p class="text-center text-caption text-medium-emphasis mt-3">
                By confirming, you agree to arrive 10-15 minutes before your appointment time.
            </p>
        </template>
        <!-- STEP 5: Success -->
        <template v-if="step === 5">
            <div class="text-center py-8">
                <v-icon icon="mdi-check-circle" size="80" color="success" class="mb-4" />
                <h2 class="text-h4 font-weight-bold mb-2 text-success">Appointment confirmed!</h2>
                <p class="text-body-1 text-medium-emphasis mb-6">Your appointment has been booked successfully.</p>
                <v-card v-if="confirmedAppointment" variant="outlined" class="text-left mb-6">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-start justify-space-between mb-4">
                            <div>
                                <div class="text-caption text-medium-emphasis">Reference</div>
                                <div class="text-subtitle-1 font-weight-bold font-mono">{{ confirmedAppointment.appointment_number }}</div>
                            </div>
                            <v-chip color="success" variant="tonal" size="small">{{ confirmedAppointment.status }}</v-chip>
                        </div>
                        <v-divider class="my-3" />
                        <div class="d-flex align-center gap-2 mb-2">
                            <v-icon icon="mdi-doctor" size="18" color="primary" />
                            <div>
                                <div class="text-body-2 font-weight-medium">{{ confirmedAppointment.doctor?.name }}</div>
                                <div class="text-caption text-medium-emphasis">{{ confirmedAppointment.doctor?.specialty }}</div>
                            </div>
                        </div>
                        <div class="d-flex align-center gap-2 mb-2">
                            <v-icon icon="mdi-hospital-building" size="16" color="medium-emphasis" />
                            <span class="text-body-2">{{ confirmedAppointment.facility?.name }}</span>
                        </div>
                        <div class="d-flex align-center gap-2 mb-2">
                            <v-icon icon="mdi-calendar" size="16" color="medium-emphasis" />
                            <span class="text-body-2">{{ confirmedAppointment.day }}</span>
                        </div>
                        <div class="d-flex align-center gap-2">
                            <v-icon icon="mdi-clock-outline" size="16" color="medium-emphasis" />
                            <span class="text-body-2">{{ confirmedAppointment.start_time }} - {{ confirmedAppointment.end_time }}</span>
                        </div>
                    </v-card-text>
                </v-card>
                <v-btn color="primary" :to="'/appointments/' + confirmedAppointment?.id" size="large" block class="mb-3">
                    View appointment
                </v-btn>
                <v-btn variant="outlined" to="/my-appointments" size="large" block class="mb-3">
                    My appointments
                </v-btn>
                <v-btn variant="text" to="/" size="large" block>
                    Find another doctor
                </v-btn>
            </div>
        </template>

        <!-- Auth redirect dialog -->
        <v-dialog v-model="showAuthPrompt" max-width="400" persistent>
            <v-card>
                <v-card-title class="text-h6 pa-4">Sign in required</v-card-title>
                <v-card-text class="px-4 pb-4">
                    Please sign in or create an account to complete your booking.
                </v-card-text>
                <v-card-actions class="px-4 pb-4 gap-2">
                    <v-btn variant="outlined" @click="showAuthPrompt = false">Cancel</v-btn>
                    <v-btn color="primary" to="/login">Sign in</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Error snackbar -->
        <v-snackbar v-model="showError" color="error" :timeout="6000">
            {{ errorMessage }}
            <template #actions>
                <v-btn variant="text" @click="showError = false">Close</v-btn>
            </template>
        </v-snackbar>
    </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../../stores/authStore";
import { useAppointmentStore } from "../../stores/appointmentStore";
import { appointmentService } from "../../services/appointmentService";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const aptStore = useAppointmentStore();

const sessionId = parseInt(route.params.sessionId);

const step = ref(1);
const session = ref(null);
const sessionLoading = ref(true);
const sessionError = ref(null);
const slotsLoading = ref(false);
const slots = ref([]);
const selectedSlot = ref(null);
const patientName = ref("");
const patientPhone = ref("");
const patientEmail = ref("");
const appointmentReason = ref("");
const booking = ref(false);
const confirmedAppointment = ref(null);
const showAuthPrompt = ref(false);
const showError = ref(false);
const errorMessage = ref("");

const availableSlots = computed(() => slots.value.filter(s => s.is_available));

const doctorInitials = computed(() => {
    const name = session.value?.doctor?.name || "";
    return name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
});

const doctorSpecialty = computed(() => session.value?.doctor?.specialty || "");

async function loadSession() {
    sessionLoading.value = true;
    sessionError.value = null;
    try {
        const response = await appointmentService.sessionSlots(sessionId);
        session.value = {
            ...response.session,
            doctor: response.session.doctor || {},
            facility: response.session.facility || {},
        };
    } catch (e) {
        sessionError.value = e.response?.data?.error || "Failed to load clinic session.";
    } finally {
        sessionLoading.value = false;
    }
}

async function loadSlots() {
    slotsLoading.value = true;
    try {
        await aptStore.fetchSlots(sessionId);
        slots.value = aptStore.slots;
        step.value = 2;
    } catch (e) {
        errorMessage.value = "Failed to load time slots. Please try again.";
        showError.value = true;
    } finally {
        slotsLoading.value = false;
    }
}

function selectSlot(slot) {
    if (!slot.is_available) return;
    selectedSlot.value = slot.start_time;
}

function goToReview() {
    if (!authStore.isAuthenticated) {
        showAuthPrompt.value = true;
        return;
    }
    if (authStore.user) {
        patientName.value = patientName.value || authStore.user.name || "";
        patientEmail.value = patientEmail.value || authStore.user.email || "";
    }
    step.value = 4;
}

async function confirmBooking() {
    booking.value = true;
    try {
        const result = await aptStore.book(sessionId, selectedSlot.value, appointmentReason.value);
        confirmedAppointment.value = result.data;
        step.value = 5;
    } catch (e) {
        const code = e.response?.data?.code;
        const error = e.response?.data?.error;
        if (code === "SLOT_TAKEN") {
            errorMessage.value = "This time slot was just booked by another patient. Please choose another time.";
            step.value = 2;
            selectedSlot.value = null;
            await loadSlots();
        } else if (code === "SESSION_CANCELLED") {
            errorMessage.value = "This clinic session has been cancelled. Please choose another clinic.";
            step.value = 1;
        } else if (code === "SESSION_NOT_BOOKABLE") {
            errorMessage.value = "This session is no longer available for booking.";
            step.value = 1;
        } else {
            errorMessage.value = error || "We could not complete your booking. Please try again.";
        }
        showError.value = true;
    } finally {
        booking.value = false;
    }
}

function goBack() {
    if (step.value > 1 && step.value < 5) {
        step.value--;
    } else {
        router.back();
    }
}

function formatDate(dateStr) {
    if (!dateStr) return "";
    return new Date(dateStr).toLocaleDateString("en-KE", {
        weekday: "long", day: "numeric", month: "long", year: "numeric"
    });
}

function formatTime(timeStr) {
    if (!timeStr) return "";
    const [h, m] = timeStr.split(":");
    const hour = parseInt(h);
    const ampm = hour >= 12 ? "PM" : "AM";
    const hour12 = hour % 12 || 12;
    return `${hour12}:${m} ${ampm}`;
}

onMounted(async () => {
    await loadSession();
    if (authStore.isAuthenticated && authStore.user) {
        patientName.value = patientName.value || authStore.user.name || "";
        patientEmail.value = patientEmail.value || authStore.user.email || "";
    }
});
</script>

<style scoped>
.clinic-summary-card { border-left: 4px solid rgb(var(--v-theme-primary)); }
.time-slot-btn { height: 60px !important; }
.font-mono { font-family: monospace; letter-spacing: 0.05em; }
</style>
