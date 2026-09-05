<template>
    <v-chip
        :color="color"
        :variant="variant"
        :size="size"
        :prepend-icon="icon"
        density="comfortable"
    >
        {{ label }}
    </v-chip>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    size: { type: String, default: 'small' },
    variant: { type: String, default: 'tonal' },
});

const config = computed(() => {
    const map = {
        pending:      { color: 'grey',   label: 'Pending',      icon: 'mdi-clock-outline' },
        confirmed:    { color: 'blue',   label: 'Confirmed',    icon: 'mdi-calendar-check' },
        checked_in:   { color: 'teal',   label: 'Checked in',   icon: 'mdi-account-check' },
        in_progress:  { color: 'amber',  label: 'In progress',  icon: 'mdi-stethoscope' },
        completed:    { color: 'green',  label: 'Completed',    icon: 'mdi-check-circle' },
        cancelled:    { color: 'red',    label: 'Cancelled',    icon: 'mdi-close-circle' },
        no_show:      { color: 'deep-orange', label: 'No-show', icon: 'mdi-account-off' },
    };
    return map[props.status] || { color: 'grey', label: props.status, icon: 'mdi-help-circle' };
});

const color = computed(() => config.value.color);
const label = computed(() => config.value.label);
const icon = computed(() => config.value.icon);
</script>