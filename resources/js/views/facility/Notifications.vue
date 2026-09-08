<template>
    <div>
        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Notifications</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Updates for this account.</p>
            </div>
            <v-btn color="primary" variant="tonal" prepend-icon="mdi-check-all" :disabled="!notifications.hasUnread || loading" @click="markAll">Mark all read</v-btn>
        </div>

        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

        <v-card v-if="!loading && notifications.items.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-bell-outline" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">All caught up</h3>
            <p class="text-body-2 text-medium-emphasis">You have no notifications.</p>
        </v-card>

        <v-card v-if="!loading && notifications.items.length > 0" variant="outlined">
            <v-list>
                <v-list-item v-for="n in notifications.items" :key="n.id" :class="n.is_unread ? 'v-theme--light' : ''">
                    <template #prepend>
                        <v-avatar :color="n.is_unread ? 'primary' : 'surface-variant'" size="40">
                            <v-icon :icon="iconFor(n)" :color="n.is_unread ? 'white' : 'medium-emphasis'"></v-icon>
                        </v-avatar>
                    </template>
                    <v-list-item-title class="font-weight-medium">
                        {{ n.title }}
                        <v-chip v-if="n.is_unread" size="x-small" color="primary" variant="tonal" class="ml-2">New</v-chip>
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-body-2">{{ n.message }}</v-list-item-subtitle>
                    <template #append>
                        <div class="text-right">
                            <div class="text-caption text-medium-emphasis">{{ n.created_human }}</div>
                            <v-btn v-if="n.is_unread" icon="mdi-check" size="small" variant="text" title="Mark read" @click="notifications.markRead(n.id)"></v-btn>
                        </div>
                    </template>
                </v-list-item>
            </v-list>
        </v-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useNotificationsStore } from "../../stores/notificationsStore";

const notifications = useNotificationsStore();
const loading = ref(false);

function iconFor(n) {
    if (n.category === 'appointment') return 'mdi-calendar';
    if (n.category === 'session' || n.category === 'clinic') return 'mdi-hospital-building';
    if (n.category === 'payment') return 'mdi-credit-card';
    if (n.category === 'verification') return 'mdi-shield-check';
    return 'mdi-bell';
}

async function load() {
    loading.value = true;
    try {
        await notifications.fetchList({ per_page: 50 });
    } finally {
        loading.value = false;
    }
}

async function markAll() {
    try {
        await notifications.markAllRead();
    } catch (e) {
        // swallow
    } finally {
        await load();
    }
}

onMounted(load);
</script>