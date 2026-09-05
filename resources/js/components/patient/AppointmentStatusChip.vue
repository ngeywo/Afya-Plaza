<template>
    <v-chip :color="chipColor" size="small" variant="tonal" class="text-capitalize">
        <v-icon :icon="chipIcon" size="x-small" start />
        {{ apt.status }}
    </v-chip>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    status: { type: String, required: true },
});

const chipColor = computed(() => ({
    confirmed: "success",
    pending: "warning",
    checked_in: "info",
    in_progress: "info",
    completed: "default",
    cancelled: "error",
    no_show: "error",
})[props.status] || "default");

const chipIcon = computed(() => ({
    confirmed: "mdi-check-circle",
    pending: "mdi-clock-outline",
    checked_in: "mdi-account-check",
    in_progress: "mdi-stethoscope",
    completed: "mdi-check-all",
    cancelled: "mdi-cancel",
    no_show: "mdi-account-off",
})[props.status] || "mdi-calendar");
</script>
