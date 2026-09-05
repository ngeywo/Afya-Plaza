<template>
    <v-btn
        v-if="canCheckIn"
        color="teal"
        size="small"
        variant="tonal"
        prepend-icon="mdi-account-check"
        :loading="loading"
        @click="handleCheckIn"
    >
        Check in
    </v-btn>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    appointment: { type: Object, required: true },
    onCheckIn: { type: Function, required: true },
});

const loading = ref(false);

const canCheckIn = computed(() => ['pending', 'confirmed'].includes(props.appointment.status));

async function handleCheckIn() {
    loading.value = true;
    try {
        await props.onCheckIn(props.appointment);
    } finally {
        loading.value = false;
    }
}
</script>