<template>
  <v-menu
    v-model="store.dropdownOpen"
    :close-on-content-click="false"
    location="bottom end"
    transition="slide-y-transition"
    max-width="380"
    @update:model-value="(v) => v ? store.openDropdown() : store.closeDropdown()"
  >
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        icon
        variant="text"
        :aria-label="`Notifications (${store.unreadCount} unread)`"
      >
        <v-badge
          :content="store.unreadCount"
          :model-value="store.unreadCount > 0"
          color="error"
          offset-x="-2"
          offset-y="2"
          :max="99"
        >
          <v-icon size="26">mdi-bell-outline</v-icon>
        </v-badge>
      </v-btn>
    </template>

    <v-card min-width="360" max-width="380" class="notification-dropdown">
      <div class="d-flex align-center px-4 py-3 border-b">
        <span class="text-h6">Notifications</span>
        <v-spacer />
        <v-btn
          v-if="store.hasUnread"
          variant="text"
          size="small"
          color="primary"
          @click="store.markAllRead()"
        >
          Mark all read
        </v-btn>
        <v-btn variant="text" size="small" :to="{ name: 'notifications' }" @click="store.closeDropdown()">
          See all
        </v-btn>
      </div>

      <div v-if="store.loading && store.items.length === 0" class="pa-6 text-center text-medium-emphasis">
        <v-progress-circular indeterminate color="primary" size="24" />
      </div>
      <div v-else-if="store.items.length === 0" class="pa-8 text-center text-medium-emphasis">
        <v-icon size="40" color="grey-lighten-1">mdi-bell-off-outline</v-icon>
        <p class="mt-2 mb-0">No notifications yet</p>
        <p class="text-caption mb-0">When a doctor you follow opens a clinic, you'll hear about it here.</p>
      </div>
      <NotificationDropdown
        v-else
        :items="store.items"
        @mark-read="(id) => store.markRead(id)"
      />
    </v-card>
  </v-menu>
</template>

<script setup>
import { useNotificationsStore } from '../stores/notificationsStore';
import NotificationDropdown from './NotificationDropdown.vue';

const store = useNotificationsStore();
</script>

<style scoped>
.notification-dropdown { border-radius: 12px; }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
</style>
