<template>
    <div class="loading-state">
        <template v-if="variant === 'skeleton'">
            <v-skeleton-loader
                v-for="n in rows"
                :key="n"
                type="list-item-two-line"
            ></v-skeleton-loader>
        </template>
        <template v-else-if="variant === 'board'">
            <v-skeleton-loader type="card" v-for="n in rows" :key="n" class="mb-3"></v-skeleton-loader>
        </template>
        <div v-else class="d-flex flex-column align-center justify-center pa-10 gap-2">
            <v-progress-circular indeterminate color="primary" size="40" width="3"></v-progress-circular>
            <span v-if="label" class="text-body-2 text-medium-emphasis mt-2">{{ label }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    variant: { type: String, default: "skeleton" },
    rows: { type: Number, default: 3 },
    label: { type: String, default: "" },
});
</script>