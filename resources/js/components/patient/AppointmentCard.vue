<template>
    <v-card variant="outlined" class="pa-4 h-100">
        <!-- Header row: reference + status -->
        <div class="d-flex align-start justify-space-between mb-3">
            <div>
                <div class="text-caption text-medium-emphasis">Reference</div>
                <div class="text-subtitle-2 font-weight-medium font-mono">{{ apt.appointment_number }}</div>
            </div>
            <AppointmentStatusChip :status="apt.status" />
        </div>

        <v-divider class="my-2"></v-divider>

        <!-- Doctor info -->
        <div class="d-flex align-center mb-2">
            <v-avatar size="32" color="primary" class="mr-2">
                <v-img v-if="apt.doctor?.avatar" :src="apt.doctor.avatar" />
                <span v-else class="text-caption font-weight-bold text-white">{{ initials }}</span>
            </v-avatar>
            <div>
                <div class="text-body-2 font-weight-medium">{{ apt.doctor?.name }}</div>
                <div v-if="apt.doctor?.specialty" class="text-caption text-primary">{{ apt.doctor.specialty }}</div>
            </div>
        </div>

        <!-- Date + time -->
        <div class="d-flex align-center mb-1">
            <v-icon icon="mdi-calendar" size="small" color="medium-emphasis" class="mr-2"></v-icon>
            <span class="text-body-2">{{ formatDate(apt.appointment_date) }}</span>
        </div>
        <div class="d-flex align-center mb-1">
            <v-icon icon="mdi-clock-outline" size="small" color="medium-emphasis" class="mr-2"></v-icon>
            <span class="text-body-2">{{ apt.start_time?.slice(0,5) }} – {{ apt.end_time?.slice(0,5) }}</span>
        </div>

        <!-- Facility -->
        <div class="d-flex align-center mb-1">
            <v-icon icon="mdi-hospital-building" size="small" color="medium-emphasis" class="mr-2"></v-icon>
            <span class="text-body-2">{{ apt.facility?.name }}, {{ apt.facility?.city }}</span>
        </div>

        <!-- Reason -->
        <div v-if="apt.reason" class="text-caption text-medium-emphasis mt-2">
            <v-icon icon="mdi-text" size="x-small" class="mr-1"></v-icon>{{ apt.reason }}
        </div>

        <!-- Cancellation note -->
        <v-alert v-if="apt.status === 'cancelled' && apt.cancellation_reason"
            type="error" variant="tonal" density="compact" class="mt-2 caption">
            Cancelled: {{ apt.cancellation_reason }}
        </v-alert>

        <v-divider class="my-3"></v-divider>

        <!-- Fee -->
        <div class="d-flex align-center mb-3">
            <span class="text-body-2 text-medium-emphasis">Fee</span>
            <v-spacer></v-spacer>
            <span class="text-body-1 font-weight-bold text-primary">
                KSh {{ apt.amount_paid || '0.00' }}
            </span>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2">
            <v-btn :to="`/appointments/${apt.id}`" variant="outlined" size="small" block>
                View details
            </v-btn>
            <v-btn v-if="canReschedule" color="primary" variant="tonal" size="small"
                @click="doReschedule">
                Reschedule
            </v-btn>
            <v-btn v-if="canCancel" color="error" variant="outlined" size="small"
                :loading="cancelling === apt.id" @click="doCancel">
                Cancel
            </v-btn>
        </div>
    </v-card>
</template>

<script setup>
import { computed } from "vue";
import AppointmentStatusChip from "./AppointmentStatusChip.vue";

const props = defineProps({
    apt: { type: Object, required: true },
    cancelling: { type: Number, default: null },
});

const emit = defineEmits(["cancel", "reschedule"]);

const initials = computed(() => {
    const name = props.apt.doctor?.name || "";
    return name.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase();
});

// Phase 17: only show actions the backend will accept (allowed_actions from API).
const actions = computed(() => props.apt.allowed_actions || []);
const canCancel = computed(() =>
    actions.value.length ? actions.value.includes("cancel")
        : ["pending", "confirmed", "checked_in"].includes(props.apt.status)
);
const canReschedule = computed(() =>
    actions.value.length ? actions.value.includes("reschedule")
        : ["pending", "confirmed"].includes(props.apt.status)
);

function doCancel() { emit("cancel", props.apt); }
function doReschedule() { emit("reschedule", props.apt); }

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString("en-KE", {
        weekday: "short", day: "numeric", month: "short", year: "numeric"
    }) : "";
}
</script>

<style scoped>
.font-mono { font-family: monospace; letter-spacing: 0.03em; }
</style>
