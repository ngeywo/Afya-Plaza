<template>
    <v-card variant="outlined" class="pa-4 h-100">
        <div class="d-flex align-center mb-2">
            <v-icon :icon="icon" color="primary" class="mr-2"></v-icon>
            <span class="text-subtitle-2 font-weight-bold">{{ title }}</span>
        </div>
        <div class="d-flex align-baseline mb-1">
            <span class="text-h4 font-weight-bold">{{ usage.used }}</span>
            <span class="text-body-2 text-medium-emphasis ml-1">/ {{ usage.unlimited ? '∞' : usage.max ?? 0 }}</span>
        </div>
        <v-progress-linear
            :model-value="usage.unlimited ? 0 : usage.percent"
            :color="progressColor"
            height="8"
            rounded
            class="mb-2"
        ></v-progress-linear>
        <div class="d-flex align-center justify-space-between">
            <v-chip v-if="!usage.unlimited" :color="statusColor" size="x-small" variant="tonal">
                {{ statusLabel }}
            </v-chip>
            <v-chip v-else color="success" size="x-small" variant="tonal">Unlimited</v-chip>
            <v-btn v-if="!usage.unlimited && usage.status === 'at_limit'" size="x-small" variant="text" color="primary" @click="$emit('manage')">
                {{ manageLabel }}
            </v-btn>
        </div>
    </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    usage: { type: Object, required: true },
    icon: { type: String, default: 'mdi-chart-box' },
    manageLabel: { type: String, default: 'Manage' },
});

defineEmits(['manage']);

const progressColor = computed(() => {
    if (props.usage.unlimited) return 'success';
    if (props.usage.status === 'at_limit') return 'error';
    if (props.usage.status === 'near_limit') return 'warning';
    return 'primary';
});

const statusColor = computed(() => {
    if (props.usage.status === 'at_limit') return 'error';
    if (props.usage.status === 'near_limit') return 'warning';
    return 'success';
});

const statusLabel = computed(() => {
    if (props.usage.status === 'at_limit') return 'Limit Reached';
    if (props.usage.status === 'near_limit') return 'Near Limit';
    return 'OK';
});
</script>
