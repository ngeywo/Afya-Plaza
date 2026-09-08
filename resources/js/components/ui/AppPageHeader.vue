<template>
    <div class="d-flex flex-wrap align-end justify-space-between gap-3 mb-6">
        <div class="d-flex align-center gap-3">
            <div v-if="icon" class="d-inline-flex align-center justify-center rounded-lg" style="width: 44px; height: 44px; background: rgb(var(--v-theme-primary) / 0.1);">
                <v-icon :icon="icon" color="primary" size="24"></v-icon>
            </div>
            <div>
                <h1 class="page-title mb-0">{{ title }}</h1>
                <p v-if="subtitle" class="page-subtitle mb-0 mt-1">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="d-flex gap-2 flex-wrap">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    icon: { type: String, default: "" },
});
</script>