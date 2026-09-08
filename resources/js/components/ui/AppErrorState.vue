<template>
    <v-card variant="outlined" class="pa-10 text-center h-100 d-flex flex-column align-center justify-center">
        <div class="d-inline-flex align-center justify-center rounded-xl mb-4" style="width: 72px; height: 72px; background: rgb(var(--v-theme-error) / 0.08);">
            <v-icon :icon="icon" size="36" color="error"></v-icon>
        </div>
        <h3 class="text-h6 font-weight-bold mb-1">{{ title }}</h3>
        <p class="text-body-2 text-medium-emphasis mb-5" style="max-width: 420px;">{{ message }}</p>
        <v-btn v-if="showRetry" color="primary" variant="flat" prepend-icon="mdi-refresh" class="px-6" :loading="loading" @click="$emit('retry')">
            {{ retryLabel }}
        </v-btn>
    </v-card>
</template>

<script setup>
defineProps({
    title: { type: String, default: "We couldn't load this." },
    message: { type: String, default: "Please check your connection and try again." },
    icon: { type: String, default: "mdi-cloud-alert-outline" },
    showRetry: { type: Boolean, default: true },
    retryLabel: { type: String, default: "Try Again" },
    loading: { type: Boolean, default: false },
});

defineEmits(["retry"]);
</script>