<template>
    <v-dialog
        :model-value="modelValue"
        :max-width="maxWidth"
        @update:model-value="v => $emit('update:modelValue', v)"
        persistent
    >
        <v-card>
            <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold px-6 pt-6">
                <v-icon :icon="confirmIcon" :color="confirmColor"></v-icon>
                {{ title }}
            </v-card-title>
            <v-card-text class="text-body-2 px-6" style="color: var(--ink-500); line-height: 1.6;">
                {{ message }}
                <slot name="content" />
            </v-card-text>
            <v-card-actions class="px-6 pb-6">
                <v-spacer></v-spacer>
                <v-btn variant="tonal" :disabled="loading" class="px-5" @click="$emit('update:modelValue', false)">
                    {{ cancelLabel }}
                </v-btn>
                <v-btn :color="confirmColor" variant="flat" :loading="loading" class="px-5" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, default: "Are you sure?" },
    message: { type: String, default: "" },
    confirmLabel: { type: String, default: "Confirm" },
    cancelLabel: { type: String, default: "Cancel" },
    confirmColor: { type: String, default: "error" },
    confirmIcon: { type: String, default: "mdi-alert-outline" },
    loading: { type: Boolean, default: false },
    maxWidth: { type: [String, Number], default: 440 },
});

defineEmits(["update:modelValue", "confirm"]);
</script>