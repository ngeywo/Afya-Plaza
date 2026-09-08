<template>
    <v-chip
        :color="config.color"
        :variant="variant"
        :size="size"
        :prepend-icon="config.icon"
        density="comfortable"
        class="app-status-chip"
    >
        {{ displayLabel }}
    </v-chip>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: "" },
    size: { type: String, default: "small" },
    variant: { type: String, default: "tonal" },
});

const CONFIG = {
    // Confirmed / positive
    active: { color: "success", icon: "mdi-check-circle-outline" },
    confirmed: { color: "success", icon: "mdi-check-circle" },
    verified: { color: "success", icon: "mdi-shield-check-outline" },
    approved: { color: "success", icon: "mdi-check-all" },
    paid: { color: "success", icon: "mdi-cash-check" },
    completed: { color: "success", icon: "mdi-check-circle-outline" },
    complete: { color: "success", icon: "mdi-check-circle-outline" },
    checked_in: { color: "success", icon: "mdi-account-check" },
    available: { color: "success", icon: "mdi-calendar-check" },
    accepted: { color: "success", icon: "mdi-check" },
    // Attention / pending
    pending: { color: "warning", icon: "mdi-clock-outline" },
    awaiting: { color: "warning", icon: "mdi-clock-alert-outline" },
    requested: { color: "warning", icon: "mdi-clock-edit-outline" },
    unpaid: { color: "warning", icon: "mdi-credit-card-off-outline" },
    processing: { color: "warning", icon: "mdi-progress-clock" },
    review: { color: "warning", icon: "mdi-magnify" },
    // Destructive / failure
    cancelled: { color: "error", icon: "mdi-close-circle-outline" },
    rejected: { color: "error", icon: "mdi-cancel" },
    expired: { color: "error", icon: "mdi-timer-off-outline" },
    no_show: { color: "error", icon: "mdi-account-off-outline" },
    failed: { color: "error", icon: "mdi-alert-circle-outline" },
    terminated: { color: "error", icon: "mdi-link-off" },
    ended: { color: "error", icon: "mdi-stop-circle-outline" },
    // Informational
    in_progress: { color: "info", icon: "mdi-stethoscope" },
    info: { color: "info", icon: "mdi-information-outline" },
    draft: { color: "info", icon: "mdi-file-document-edit-outline" },
    // Neutral states
    suspended: { color: "default", icon: "mdi-pause-circle-outline" },
    inactive: { color: "default", icon: "mdi-minus-circle-outline" },
    unverified: { color: "default", icon: "mdi-shield-off-outline" },
    dormant: { color: "default", icon: "mdi-sleep" },
};

const normalized = computed(() => (props.status || "").trim().toLowerCase().replace(/\s+/g, "_"));

const config = computed(() => CONFIG[normalized.value] || { color: "default", icon: "mdi-help-circle-outline" });

const displayLabel = computed(() => {
    if (props.label) return props.label;
    if (config.value.label) return config.value.label;
    const raw = props.status || "";
    return raw.split(/[\s_]+/).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(" ");
});
</script>