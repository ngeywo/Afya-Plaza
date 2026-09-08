<template>
    <v-dialog v-model="dialog" max-width="640" persistent>
        <v-card>
            <v-card-title class="d-flex align-center pa-4 border-b">
                <v-icon icon="mdi-calendar-plus" color="primary" class="mr-2"></v-icon>
                <span class="text-h6 font-weight-bold">Book an Appointment</span>
                <v-spacer></v-spacer>
                <v-btn icon="mdi-close" variant="text" size="small" @click="close"></v-btn>
            </v-card-title>
            <v-card-text class="pa-5">
                <template v-if="step === 'auth'">
                    <div class="text-center py-4">
                        <v-icon icon="mdi-account-circle-outline" size="64" color="primary" class="mb-3"></v-icon>
                        <h2 class="text-h6 font-weight-bold mb-2">Sign in to book</h2>
                        <p class="text-body-2 text-medium-emphasis mb-4">You need an account to book. Sign in or create one.</p>
                        <v-btn color="primary" block size="large" to="/login">Sign in</v-btn>
                        <v-btn variant="outlined" block size="large" class="mt-2" to="/register">Create new account</v-btn>
                    </div>
                </template>
                <template v-else-if="step === 'session'">
                    <p class="text-body-2 text-medium-emphasis mb-3">Choose a date that works for you</p>
                    <v-row dense>
                        <v-col v-for="s in availableSessions" :key="s.id" cols="12" sm="6">
                            <v-card variant="outlined" class="pa-3 cursor-pointer h-100 session-card" :class="{'session-card-active': selectedSession?.id === s.id}" @click="selectSession(s)">
                                <div class="d-flex align-center">
                                    <v-icon icon="mdi-calendar" color="primary" class="mr-2"></v-icon>
                                    <div>
                                        <div class="text-subtitle-2 font-weight-medium">{{ formatDate(s.session_date) }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ s.start_time.slice(0,5) }} - {{ s.end_time.slice(0,5) }} at {{ s.facility?.name }}</div>
                                    </div>
                                </div>
                                <v-chip class="mt-2" :color="s.available_slots > 0 ? 'success' : 'error'" size="x-small" variant="tonal">{{ s.available_slots }} / {{ s.max_appointments }} slots</v-chip>
                            </v-card>
                        </v-col>
                    </v-row>
                </template>
                <template v-else-if="step === 'slot'">
                    <div class="d-flex align-center mb-3">
                        <v-btn icon="mdi-arrow-left" variant="text" size="small" @click="step = 'session'"></v-btn>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">{{ formatDate(selectedSession?.session_date) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ selectedSession?.start_time?.slice(0,5) }} - {{ selectedSession?.end_time?.slice(0,5) }} at {{ selectedSession?.facility?.name }}</div>
                        </div>
                    </div>
                    <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
                    <p class="text-body-2 text-medium-emphasis mb-3">Select a time slot</p>
                    <v-row dense>
                        <v-col v-for="slot in slots" :key="slot.start_time" cols="6" sm="4">
                            <v-btn block variant="outlined" :color="selectedSlot?.start_time === slot.start_time ? 'primary' : 'default'" :disabled="!slot.available" @click="selectSlot(slot)">{{ slot.start_time }} - {{ slot.end_time }}</v-btn>
                        </v-col>
                    </v-row>
                </template>                <template v-else-if="step === 'confirm'">
                    <div class="d-flex align-center mb-3">
                        <v-btn icon="mdi-arrow-left" variant="text" size="small" @click="step = 'slot'"></v-btn>
                        <span class="text-subtitle-1 font-weight-bold">Confirm your appointment</span>
                    </div>
                    <v-card variant="outlined" class="pa-4 mb-3">
                        <div class="d-flex align-start mb-2">
                            <v-icon icon="mdi-doctor" color="primary" class="mr-3 mt-1"></v-icon>
                            <div>
                                <div class="text-subtitle-1 font-weight-bold">{{ doctor?.name }}</div>
                                <div class="text-body-2 text-medium-emphasis">{{ (doctor?.specialties || []).map(s => s.name).join(" | ") }}</div>
                            </div>
                        </div>
                        <v-divider class="my-3"></v-divider>
                        <div class="d-flex align-start mb-2"><v-icon icon="mdi-calendar" size="small" color="medium-emphasis" class="mr-3"></v-icon><div><div class="text-body-2 font-weight-medium">{{ formatDate(selectedSession?.session_date) }}</div><div class="text-caption text-medium-emphasis">{{ dayName(selectedSession?.session_date) }}</div></div></div>
                        <div class="d-flex align-start mb-2"><v-icon icon="mdi-clock-outline" size="small" color="medium-emphasis" class="mr-3"></v-icon><div class="text-body-2 font-weight-medium">{{ selectedSlot?.start_time }} - {{ selectedSlot?.end_time }}</div></div>
                        <div class="d-flex align-start mb-2"><v-icon icon="mdi-map-marker" size="small" color="medium-emphasis" class="mr-3"></v-icon><div><div class="text-body-2 font-weight-medium">{{ selectedSession?.facility?.name }}</div><div class="text-caption text-medium-emphasis">{{ selectedSession?.facility?.city }}</div></div></div>
                        <v-divider class="my-3"></v-divider>
                        <div class="d-flex align-center"><v-icon icon="mdi-cash" color="success" class="mr-2"></v-icon><span class="text-body-2">Consultation fee</span><v-spacer></v-spacer><span class="text-h6 font-weight-bold text-primary">KSh {{ doctor?.consultation_fee }}</span></div>
                    </v-card>
                    <v-textarea v-model="reason" label="Reason for visit (optional)" variant="outlined" density="comfortable" rows="2" placeholder="e.g. Knee pain follow-up..."></v-textarea>
                    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
                </template>
                <template v-else-if="step === 'success'">
                    <div class="text-center py-6">
                        <v-icon icon="mdi-check-circle" color="success" size="80" class="mb-3"></v-icon>
                        <h2 class="text-h5 font-weight-bold mb-2">You are booked!</h2>
                        <p class="text-body-2 text-medium-emphasis mb-2">Appointment #{{ createdAppointment?.appointment_number }}</p>
                        <v-card variant="outlined" class="pa-4 text-left">
                            <div class="d-flex align-center mb-2"><v-icon icon="mdi-doctor" color="primary" class="mr-2"></v-icon><span class="text-body-2 font-weight-medium">{{ doctor?.name }}</span></div>
                            <div class="d-flex align-center mb-2"><v-icon icon="mdi-calendar" color="primary" class="mr-2"></v-icon><span class="text-body-2">{{ formatDate(selectedSession?.session_date) }} at {{ selectedSlot?.start_time }}</span></div>
                            <div class="d-flex align-center"><v-icon icon="mdi-map-marker" color="primary" class="mr-2"></v-icon><span class="text-body-2">{{ selectedSession?.facility?.name }}</span></div>
                        </v-card>
                        <p class="text-caption text-medium-emphasis mt-4">You will receive a confirmation SMS and email shortly.</p>
                    </div>
                </template>
            </v-card-text>
            <v-card-actions class="pa-4 border-t" v-if="step !== 'success' && step !== 'auth'">
                <v-spacer></v-spacer>
                <v-btn v-if="step === 'confirm'" color="primary" size="large" :loading="booking" @click="confirmBooking">Confirm Booking - KSh {{ doctor?.consultation_fee }}</v-btn>
                <v-btn v-else-if="step === 'slot'" color="primary" size="large" :disabled="!selectedSlot" @click="step = 'confirm'">Continue</v-btn>
            </v-card-actions>
            <v-card-actions class="pa-4 border-t" v-if="step === 'success'">
                <v-btn color="primary" block size="large" @click="viewAppointment">View My Appointments</v-btn>
                <v-btn variant="outlined" block size="large" class="mt-2" @click="close">Done</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/authStore";
import { useAppointmentStore } from "../stores/appointmentStore";

const props = defineProps({ modelValue: Boolean, doctor: Object });
const emit = defineEmits(["update:modelValue", "booked"]);

const auth = useAuthStore();
const aptStore = useAppointmentStore();
const router = useRouter();

const dialog = computed({ get: () => props.modelValue, set: v => emit("update:modelValue", v) });
const step = ref("session");
const selectedSession = ref(null);
const selectedSlot = ref(null);
const reason = ref("");
const error = ref("");
const loading = ref(false);
const booking = ref(false);
const slots = ref([]);
const createdAppointment = ref(null);

const availableSessions = computed(() => (props.doctor?.sessions || []).filter(s => s.is_active && s.available_slots > 0));

watch(() => props.modelValue, v => {
    if (v) {
        step.value = auth.isAuthenticated ? "session" : "auth";
        selectedSession.value = null;
        selectedSlot.value = null;
        reason.value = "";
        error.value = "";
        createdAppointment.value = null;
    }
});

async function selectSession(s) {
    selectedSession.value = s;
    loading.value = true;
    try {
        await aptStore.fetchSlots(s.id);
        slots.value = aptStore.slots;
        step.value = "slot";
    } catch(e) { error.value = "Failed to load slots."; }
    finally { loading.value = false; }
}

function selectSlot(slot) { if (slot.available) selectedSlot.value = slot; }

async function confirmBooking() {
    booking.value = true; error.value = "";
    try {
        const data = await aptStore.book(selectedSession.value.id, selectedSlot.value.start_time, reason.value);
        createdAppointment.value = data.data;
        step.value = "success";
        emit("booked", data.data);
    } catch(e) { error.value = e.response?.data?.error || "Booking failed. Please try again."; }
    finally { booking.value = false; }
}

function viewAppointment() { dialog.value = false; router.push("/my-appointments"); }
function close() { dialog.value = false; }
function formatDate(d) {
    if (!d) return "";
    return new Date(d).toLocaleDateString("en-KE", { weekday: "short", day: "numeric", month: "short", year: "numeric" });
}
function dayName(d) { return d ? new Date(d).toLocaleDateString("en-KE", { weekday: "long" }) : ""; }
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
.session-card:hover { border-color: rgb(var(--v-theme-primary)); }
.session-card-active { border-color: rgb(var(--v-theme-primary)) !important; background-color: rgb(var(--v-theme-primary) / 0.05); }
</style>

