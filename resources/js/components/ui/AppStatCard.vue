<template>
    <v-card class="pa-4 h-100">
        <div class="d-flex align-center justify-space-between mb-3 gap-2">
            <span class="text-caption font-weight-bold text-uppercase tracking-wide" style="letter-spacing: 0.08em; color: var(--ink-400);">{{ label }}</span>
            <div v-if="icon" class="d-inline-flex align-center justify-center flex-shrink-0" :style="iconBoxStyle">
                <v-icon :icon="icon" :color="toneColor" size="20"></v-icon>
            </div>
        </div>
        <div class="text-h4 font-weight-bold text-on-surface mt-1">
            <v-skeleton-loader v-if="loading" type="text" width="60%" class="rounded"></v-skeleton-loader>
            <template v-else>{{ value }}</template>
        </div>
        <div v-if="hint && !loading" class="text-caption mt-1 d-flex align-center">
            <v-icon v-if="hintIcon" :icon="hintIcon" size="14" :color="hintToneColor" class="mr-1"></v-icon>
            <span :class="hintClass">{{ hint }}</span>
        </div>
    </v-card>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: "" },
    icon: { type: String, default: "" },
    tone: { type: String, default: "neutral" },
    hint: { type: String, default: "" },
    hintIcon: { type: String, default: "" },
    color: { type: String, default: "" },
    loading: { type: Boolean, default: false },
});

const toneColor = computed(() => ({
    neutral: "primary",
    info: "info",
    success: "success",
    warning: "warning",
    error: "error",
}[props.tone] || "primary"));

const hintToneColor = computed(() => (props.color && props.color !== "neutral" ? props.color : toneColor.value));

const hintClass = computed(() => ({
    neutral: "text-medium-emphasis",
    info: "text-info",
    success: "text-success",
    warning: "text-warning",
    error: "text-error",
}[props.color || "neutral"]));

const iconBoxStyle = computed(() => {
    const map = {
        neutral: "rgb(var(--v-theme-primary) / 0.1)",
        info: "rgb(var(--v-theme-info) / 0.12)",
        success: "rgb(var(--v-theme-success) / 0.12)",
        warning: "rgb(var(--v-theme-warning) / 0.14)",
        error: "rgb(var(--v-theme-error) / 0.12)",
    };
    return { width: "34px", height: "34px", borderRadius: "10px", background: map[props.tone] || map.neutral };
});
</script>