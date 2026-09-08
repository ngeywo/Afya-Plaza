<template>
    <div>
        <div class="d-flex justify-space-between align-center mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold mb-0">Notifications</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Platform-wide updates for this admin account.</p>
            </div>
            <v-btn color="primary" variant="tonal" prepend-icon="mdi-check-all" :disabled="!notifications.hasUnread || notifications.loading" @click="markAll">Mark all read</v-btn>
        </div>
        <v-progress-linear v-if="notifications.loading" indeterminate class="mb-4"></v-progress-linear>
        <v-alert v-if="notifications.error" type="error" variant="tonal" class="mb-4" closable @click:close="notifications.error = null">{{ notifications.error }}</v-alert>
        <v-card v-if="!notifications.loading && notifications.items.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-bell-outline" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">All caught up</h3>
            <p class="text-body-2 text-medium-emphasis">You have no notifications.</p>
        </v-card>
        <v-card v-if="!notifications.loading && notifications.items.length > 0" variant="outlined">
            <v-list>
                <v-list-item v-for="n in notifications.items" :key="n.id">
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
            <v-card-actions v-if="notifications.meta && notifications.meta.total > notifications.items.length">
                <v-spacer></v-spacer>
                <v-btn variant="text" size="small" @click="notifications.fetchList({ per_page: 25 })">Load more</v-btn>
            </v-card-actions>
        </v-card>
    </div>
</template>
<script setup>
import { onMounted } from "vue";
import { useNotificationsStore } from "../../stores/notificationsStore";

const notifications = useNotificationsStore();

function iconFor(n) {
    if (!n || !n.category) return "mdi-bell-outline";
    return {
        appointment: "mdi-calendar-check",
        payment: "mdi-cash",
        verification: "mdi-shield-check",
        account: "mdi-account",
        system: "mdi-server",
        security: "mdi-lock",
    }[n.category] || "mdi-bell-outline";
}

function markAll() {
    notifications.markAllRead();
}

onMounted(() => notifications.fetchList({ per_page: 25 }));
</script>