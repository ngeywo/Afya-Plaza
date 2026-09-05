<template>
    <v-card variant="outlined" class="pa-12 text-center">
        <v-icon :icon="icon || 'mdi-information-outline'" size="80" color="medium-emphasis" class="mb-4"></v-icon>
        <h2 class="text-h6 font-weight-bold mb-2">{{ title }}</h2>
        <p class="text-body-2 text-medium-emphasis mb-4">{{ body }}</p>
        <v-btn v-if="actionLabel && actionTo" color="primary" :to="actionTo" size="large">
            {{ actionLabel }}
        </v-btn>
    </v-card>
</template>

<script setup>
defineProps({
    icon: { type: String, default: "mdi-information-outline" },
    title: { type: String, default: "Nothing here yet" },
    body: { type: String, default: "" },
    actionLabel: { type: String, default: "" },
    actionTo: { type: String, default: "" },
});
</script>
