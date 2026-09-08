<template>
    <v-card variant="outlined" class="pa-10 text-center h-100 d-flex flex-column align-center justify-center">
        <div class="d-inline-flex align-center justify-center rounded-xl mb-4" style="width: 72px; height: 72px; background: rgb(var(--v-theme-primary) / 0.08);">
            <v-icon :icon="icon || 'mdi-information-outline'" size="36" class="text-primary"></v-icon>
        </div>
        <h3 class="text-h6 font-weight-bold mb-1 text-on-surface">{{ title }}</h3>
        <p class="text-body-2 text-medium-emphasis mb-5" style="max-width: 420px;">{{ body }}</p>
        <div class="d-flex gap-2 flex-wrap justify-center">
            <slot name="actions">
                <v-btn v-if="actionLabel && (actionTo || actionVisible)" color="primary" :to="actionTo" :disabled="!actionTo && !actionVisible" @click="$emit('action')" size="large" class="px-6">
                    <v-icon icon="mdi-arrow-right" class="mr-2"></v-icon>{{ actionLabel }}
                </v-btn>
            </slot>
        </div>
    </v-card>
</template>

<script setup>
defineProps({
    icon: { type: String, default: "mdi-information-outline" },
    title: { type: String, default: "Nothing here yet" },
    body: { type: String, default: "" },
    actionLabel: { type: String, default: "" },
    actionTo: { type: String, default: "" },
    // Allow a button that triggers a local @action instead of navigating
    actionVisible: { type: Boolean, default: false },
});

defineEmits(["action"]);
</script>