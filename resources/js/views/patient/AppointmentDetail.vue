<template>
    <v-container class="py-8" max-width="720">
        <v-btn variant="text" color="medium-emphasis" size="small" class="mb-4 pl-0" prepend-icon="mdi-arrow-left" to="/my-appointments">
            My appointments
        </v-btn>

        <div v-if="loading" class="text-center py-12">
            <v-progress-circular indeterminate color="primary" size="48" />
            <p class="mt-4 text-medium-emphasis">Loading appointment details...</p>
        </div>

        <div v-else-if="!apt" class="text-center py-12">
            <v-icon icon="mdi-calendar-search" size="64" color="medium-emphasis" class="mb-3" />
            <h3 class="text-h6 font-weight-bold mb-2">Appointment not found</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">This appointment may have been cancelled or you do not have access.</p>
            <v-btn color="primary" to="/my-appointments">My appointments</v-btn>
        </div>

        <div v-else>
            <div class="d-flex align-start justify-space-between mb-6">
                <div>
                    <div class="text-caption text-medium-emphasis text-uppercase mb-1">Reference</div>
                    <div class="text-h5 font-weight-bold font-mono">{{ apt.appointment_number }}</div>
                </div>
                <v-chip :color="statusColor(apt.status)" variant="tonal" size="small">
                    {{ apt.status }}
                </v-chip>
            </div>

            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-5">
                    <div class="d-flex align-center gap-3 mb-4">
                        <v-avatar size="52" color="primary">
                            <v-img v-if="apt.doctor?.avatar" :src="apt.doctor.avatar" />
                            <span v-else class="text-h6 font-weight-bold text-white">{{ doctorInitials }}</span>
                        </v-avatar>
                        <div>
                            <div class="text-h6 font-weight-bold">{{ apt.doctor?.name }}</div>
                            <div v-if="apt.doctor?.specialty" class="text-body-2 text-primary">{{ apt.doctor.specialty }}</div>
                        </div>
                    </div>
                    <v-divider class="my-3" />
                    <div class="d-flex align-center gap-2 mb-2">
                        <v-icon icon="mdi-hospital-building" size="18" color="medium-emphasis" />
                        <div>
                            <div class="text-body-2 font-weight-medium">{{ apt.facility?.name }}</div>
                            <div class="text-caption text-medium-emphasis">{{ apt.facility?.city }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-center gap-2">
                        <v-icon icon="mdi-map-marker" size="18" color="medium-emphasis" />
                        <span class="text-body-2 text-medium-emphasis">{{ apt.facility?.address }}</span>
                    </div>
                </v-card-text>
            </v-card>

            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-5">
                    <div class="text-caption text-medium-emphasis text-uppercase mb-3">Appointment</div>
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-calendar" size="20" color="primary" />
                        <span class="text-body-1 font-weight-medium">{{ apt.day }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-clock-outline" size="20" color="primary" />
                        <span class="text-body-1 font-weight-medium">{{ apt.start_time }} to {{ apt.end_time }}</span>
                    </div>
                    <div v-if="apt.reason" class="d-flex align-center gap-2 mb-2">
                        <v-icon icon="mdi-text" size="18" color="medium-emphasis" />
                        <span class="text-body-2">{{ apt.reason }}</span>
                    </div>
                    <div v-if="apt.notes" class="d-flex align-center gap-2">
                        <v-icon icon="mdi-note-text" size="18" color="medium-emphasis" />
                        <span class="text-body-2">{{ apt.notes }}</span>
                    </div>
                </v-card-text>
            </v-card>

            <v-alert v-if="apt.status === 'cancelled' && apt.cancellation_reason" type="error" variant="tonal" class="mb-4">
                <strong>Cancelled:</strong> {{ apt.cancellation_reason }}
            </v-alert>

            <!-- Payment section -->
            <v-card variant="outlined" class="mb-4">
                <v-card-text class="pa-5">
                    <div class="text-caption text-medium-emphasis text-uppercase mb-3">Payment</div>
                    <div class="d-flex align-center justify-space-between mb-2">
                        <span class="text-body-2 text-medium-emphasis">Amount</span>
                        <span class="text-h6 font-weight-bold text-primary">KSh {{ apt.amount_paid ?? 0 }}</span>
                    </div>
                    <div class="d-flex align-center justify-space-between">
                        <span class="text-body-2 text-medium-emphasis">Status</span>
                        <v-chip :color="apt.payment_status === 'paid' ? 'success' : 'warning'" variant="tonal" size="small">
                            {{ apt.payment_status }}
                        </v-chip>
                    </div>
                    <v-divider class="my-3" />
                    <div class="d-flex align-center justify-space-between">
                        <span class="text-body-2 text-medium-emphasis">Booked on</span>
                        <span class="text-body-2">{{ apt.created_at ? formatDate(apt.created_at) : '---' }}</span>
                    </div>
                    <template v-if="apt.payment_status !== 'paid' && !['cancelled', 'no_show'].includes(apt.status)">
                        <v-divider class="my-4" />
                        <v-btn block color="success" size="large" @click="openMpesaDialog = true">
                            <v-icon icon="mdi-cellphone-nfc" start />
                            Pay with M-Pesa
                        </v-btn>
                    </template>
                </v-card-text>
            </v-card>

            <!-- M-Pesa payment dialog (phone input) -->
            <v-dialog v-model="openMpesaDialog" max-width="500" persistent>
                <v-card>
                    <v-card-title class="d-flex align-center pa-4">
                        <v-icon icon="mdi-cellphone-nfc" color="success" class="mr-2" />
                        <span>Pay with M-Pesa</span>
                    </v-card-title>
                    <v-card-text class="pa-4">
                        <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                            An M-Pesa prompt will be sent to your phone. Enter your PIN to confirm.
                        </v-alert>
                        <div class="text-center mb-4">
                            <div class="text-caption text-medium-emphasis">Amount to pay</div>
                            <div class="text-h4 font-weight-bold text-primary">KSh {{ apt.amount_paid ?? 0 }}</div>
                        </div>
                        <v-text-field
                            v-model="mpesaPhone"
                            label="M-Pesa phone number"
                            placeholder="e.g. 0712345678"
                            prepend-inner-icon="mdi-phone"
                            variant="outlined"
                            density="comfortable"
                            :rules="[v => !!v || 'Phone is required', v => /^(0|\+?254)?[71]\d{8}$/.test((v || '').replace(/\s/g,'')) || 'Invalid Kenyan number']"
                        />
                        <v-alert v-if="mpesaError" type="error" variant="tonal" class="mt-2" density="compact">
                            {{ mpesaError }}
                        </v-alert>
                    </v-card-text>
                    <v-card-actions class="pa-4">
                        <v-btn variant="text" @click="openMpesaDialog = false" :disabled="mpesaProcessing">
                            Cancel
                        </v-btn>
                        <v-spacer />
                        <v-btn
                            color="success"
                            variant="flat"
                            :loading="mpesaProcessing"
                            :disabled="!mpesaPhone || mpesaProcessing"
                            @click="payWithMpesa"
                        >
                            <v-icon icon="mdi-send" start />
                            Send STK Push
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- M-Pesa waiting dialog (after STK Push sent) -->
            <v-dialog v-model="mpesaWaiting" max-width="500" persistent>
                <v-card>
                    <v-card-text class="text-center pa-6">
                        <v-progress-circular v-if="mpesaStatus === 'pending'" indeterminate color="success" size="64" class="mb-4" />
                        <v-icon v-else-if="mpesaStatus === 'paid'" icon="mdi-check-circle" color="success" size="80" class="mb-3" />
                        <v-icon v-else icon="mdi-close-circle" color="error" size="80" class="mb-3" />
                        <h3 class="text-h6 font-weight-bold mb-2">
                            <span v-if="mpesaStatus === 'pending'">Check your phone</span>
                            <span v-else-if="mpesaStatus === 'paid'">Payment successful!</span>
                            <span v-else>Payment {{ mpesaStatus }}</span>
                        </h3>
                        <p class="text-body-2 text-medium-emphasis mb-3">
                            <span v-if="mpesaStatus === 'pending'">
                                An M-Pesa prompt has been sent. Enter your PIN to complete the payment.
                            </span>
                            <span v-else-if="mpesaStatus === 'paid'">
                                Your appointment is now confirmed and paid for.
                            </span>
                            <span v-else>{{ mpesaError }}</span>
                        </p>
                        <v-chip v-if="mpesaStatus === 'pending'" color="warning" variant="tonal" size="small">
                            <v-icon icon="mdi-clock-outline" start size="14" />
                            Auto-checking...
                        </v-chip>
                    </v-card-text>
                    <v-card-actions class="pa-4">
                        <v-btn v-if="mpesaStatus !== 'pending'" color="primary" block @click="closeMpesaWaiting">
                            Close
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-card v-if="timeline.length > 0" variant="outlined" class="mb-4">
                <v-card-text class="pa-5">
                    <div class="text-caption text-medium-emphasis text-uppercase mb-3">Timeline</div>
                    <v-timeline density="compact" side="end">
                        <v-timeline-item v-for="ev in timeline" :key="ev.label" :dot-color="ev.color" size="x-small">
                            <div class="d-flex align-center"><v-icon :icon="ev.icon" :color="ev.color" size="16" class="mr-2"></v-icon><span class="text-body-2 font-weight-medium">{{ ev.label }}</span></div>
                            <div class="text-caption text-medium-emphasis ml-7">{{ formatTime(ev.at) }}</div>
                        </v-timeline-item>
                    </v-timeline>
                </v-card-text>
            </v-card>

            <div v-if="canModify" class="mb-4">
                <div class="d-flex gap-3 mb-3">
                    <v-btn v-if="canReschedule" color="primary" variant="outlined" block @click="showReschedule = true">
                        <v-icon icon="mdi-calendar-edit" start />
                        Reschedule
                    </v-btn>
                    <v-btn v-if="!showCancelForm" color="error" variant="outlined" block @click="showCancelForm = true">
                        <v-icon icon="mdi-close-circle-outline" start />
                        Cancel appointment
                    </v-btn>
                </div>
                <v-card v-if="showCancelForm" variant="outlined" class="pa-4">
                    <div class="text-body-2 font-weight-bold mb-3">Cancel this appointment?</div>
                    <v-textarea v-model="cancelReason" label="Reason (optional)" variant="outlined" density="compact" rows="2" maxlength="500" class="mb-3" />
                    <div class="d-flex gap-2">
                        <v-btn variant="outlined" block @click="showCancelForm = false">Keep</v-btn>
                        <v-btn color="error" variant="flat" block :loading="cancelling" @click="doCancel">Confirm cancel</v-btn>
                    </div>
                </v-card>
            </div>

            <div class="d-flex gap-3">
                <v-btn variant="outlined" to="/my-appointments" block>All appointments</v-btn>
                <v-btn color="primary" variant="flat" :to="'/doctors/' + apt.doctor?.slug" block>Book again</v-btn>
            </div>
        </div>

        <v-snackbar v-model="showError" color="error" :timeout="5000">
            {{ errorMessage }}
            <template #actions>
                <v-btn variant="text" @click="showError = false">Close</v-btn>
            </template>
        </v-snackbar>
        <v-snackbar v-model="showCancelled" color="success" :timeout="4000">Appointment cancelled successfully.</v-snackbar>
        <v-snackbar v-model="showRescheduled" color="success" :timeout="4000">Appointment rescheduled successfully.</v-snackbar>

        <!-- Phase 17: Reschedule with real availability -->
        <RescheduleDialog v-model="showReschedule" :apt="apt" @rescheduled="onRescheduled" />
    </v-container>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAppointmentStore } from "../../stores/appointmentStore";
import { financeService } from "../../services/financeService";
import RescheduleDialog from "../../components/patient/RescheduleDialog.vue";

const route = useRoute();
const router = useRouter();
const aptStore = useAppointmentStore();

const apt = ref(null);
const loading = ref(true);
const showCancelForm = ref(false);
const cancelReason = ref("");
const cancelling = ref(false);
const showError = ref(false);
const showCancelled = ref(false);
const showReschedule = ref(false);
const showRescheduled = ref(false);
const errorMessage = ref("");

// Phase 17: backend-authoritative allowed actions
const actions = computed(() => apt.value?.allowed_actions || []);
const canModify = computed(() =>
    actions.value.length
        ? actions.value.some(a => ["cancel", "reschedule"].includes(a))
        : ["pending", "confirmed", "checked_in"].includes(apt.value?.status)
);
const canReschedule = computed(() =>
    actions.value.length
        ? actions.value.includes("reschedule")
        : ["pending", "confirmed"].includes(apt.value?.status) && apt.value?.appointment_date >= new Date().toISOString().slice(0, 10)
);

// M-Pesa payment state
const openMpesaDialog = ref(false);
const mpesaPhone = ref("");
const mpesaProcessing = ref(false);
const mpesaError = ref("");
const mpesaWaiting = ref(false);
const mpesaStatus = ref("pending"); // 'pending' | 'paid' | 'failed' | 'timeout'
const currentPaymentId = ref(null);
let mpesaPollInterval = null;

const doctorInitials = computed(() => {
    const name = apt.value?.doctor?.name || "";
    return name.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase();
});

function statusColor(status) {
    const map = {
        pending: "warning",
        confirmed: "success",
        checked_in: "teal",
        in_progress: "amber",
        completed: "info",
        cancelled: "error",
        no_show: "deep-orange",
    };
    return map[status] || "grey";
}

const timeline = computed(() => {
    if (!apt.value) return [];
    const events = [];
    if (apt.value.confirmed_at) events.push({ label: "Appointment confirmed", at: apt.value.confirmed_at, icon: "mdi-calendar-check", color: "success" });
    if (apt.value.checked_in_at) events.push({ label: "Checked in at facility", at: apt.value.checked_in_at, icon: "mdi-account-check", color: "teal" });
    if (apt.value.consultation_started_at) events.push({ label: "Consultation started", at: apt.value.consultation_started_at, icon: "mdi-stethoscope", color: "amber" });
    if (apt.value.completed_at) events.push({ label: "Consultation completed", at: apt.value.completed_at, icon: "mdi-check-circle", color: "green" });
    if (apt.value.cancelled_at) events.push({ label: "Appointment cancelled", at: apt.value.cancelled_at, icon: "mdi-close-circle", color: "error" });
    return events;
});

function formatTime(iso) {
    if (!iso) return "---";
    return new Date(iso).toLocaleString("en-US", { dateStyle: "medium", timeStyle: "short" });
}

async function loadAppointment() {
    loading.value = true;
    try {
        const data = await aptStore.fetchAppointment(route.params.id);
        apt.value = data;
    } catch (e) {
        errorMessage.value = e.response?.data?.error || "Failed to load appointment.";
        showError.value = true;
    } finally {
        loading.value = false;
    }
}

async function doCancel() {
    cancelling.value = true;
    try {
        await aptStore.cancel(route.params.id, cancelReason.value);
        showCancelled.value = true;
        showCancelForm.value = false;
        apt.value = { ...apt.value, status: "cancelled" };
        setTimeout(() => router.push("/my-appointments"), 2000);
    } catch (e) {
        errorMessage.value = e.response?.data?.error || "Failed to cancel.";
        showError.value = true;
    } finally {
        cancelling.value = false;
    }
}

// Phase 17: refresh after a successful reschedule
async function onRescheduled() {
    showRescheduled.value = true;
    await loadAppointment();
}

// ─── M-Pesa payment flow ───────────────────────────────────────────────────

async function payWithMpesa() {
    mpesaProcessing.value = true;
    mpesaError.value = "";
    try {
        const phoneClean = mpesaPhone.value.replace(/\s/g, "");
        const result = await financeService.initiatePayment(apt.value.id, {
            method: "mobile_money",
            provider: "mpesa",
            phone: phoneClean,
        });
        currentPaymentId.value = result.data.id;
        openMpesaDialog.value = false;
        mpesaWaiting.value = true;
        mpesaStatus.value = "pending";
        startMpesaPolling();
    } catch (e) {
        mpesaError.value = e.response?.data?.error || e.response?.data?.details || "Failed to initiate M-Pesa payment.";
    } finally {
        mpesaProcessing.value = false;
    }
}

function startMpesaPolling() {
    stopMpesaPolling();
    let attempts = 0;
    const maxAttempts = 60; // 3 minutes at 3s intervals
    mpesaPollInterval = setInterval(async () => {
        attempts++;
        try {
            const payment = await financeService.getPayment(currentPaymentId.value);
            if (payment.status === "paid") {
                mpesaStatus.value = "paid";
                stopMpesaPolling();
                apt.value = { ...apt.value, payment_status: "paid" };
                setTimeout(closeMpesaWaiting, 2000);
            } else if (payment.status === "failed") {
                mpesaStatus.value = "failed";
                mpesaError.value = payment.failure_reason || "Payment failed";
                stopMpesaPolling();
            } else if (attempts >= maxAttempts) {
                mpesaStatus.value = "timeout";
                mpesaError.value = "M-Pesa prompt was not completed in time.";
                stopMpesaPolling();
            }
        } catch (e) {
            // Network error - keep trying until max attempts
        }
    }, 3000);
}

function stopMpesaPolling() {
    if (mpesaPollInterval) {
        clearInterval(mpesaPollInterval);
        mpesaPollInterval = null;
    }
}

function closeMpesaWaiting() {
    mpesaWaiting.value = false;
    mpesaStatus.value = "pending";
    mpesaPhone.value = "";
    currentPaymentId.value = null;
    stopMpesaPolling();
    if (mpesaStatus.value === "paid" || mpesaStatus.value === "failed") {
        loadAppointment(); // refresh
    }
}

onBeforeUnmount(stopMpesaPolling);

function formatDate(iso) {
    if (!iso) return "---";
    return new Date(iso).toLocaleDateString("en-KE", { weekday: "short", day: "numeric", month: "short", year: "numeric" });
}

onMounted(loadAppointment);
</script>

<style scoped>
.font-mono { font-family: monospace; letter-spacing: 0.05em; }
</style>
