<template>
  <v-container class="notifications-page" max-width="700">
    <div class="d-flex align-center mb-6">
      <v-btn icon variant="text" @click="$router.back()">
        <v-icon>mdi-arrow-left</v-icon>
      </v-btn>
      <h1 class="text-h5 ml-2">Notifications</h1>
      <v-spacer />
      <v-btn
        v-if="store.hasUnread"
        variant="text"
        color="primary"
        size="small"
        @click="store.markAllRead()"
      >
        Mark all read
      </v-btn>
    </div>

    <div v-if="store.loading && store.items.length === 0" class="text-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-alert v-else-if="store.error" type="error" variant="tonal" class="mb-4">
      {{ store.error }}
      <template #append>
        <v-btn variant="text" size="small" @click="load">Retry</v-btn>
      </template>
    </v-alert>

    <div v-else-if="store.items.length === 0" class="text-center py-16">
      <v-icon size="80" color="grey-lighten-2">mdi-bell-off-outline</v-icon>
      <h2 class="text-h6 mt-6 text-medium-emphasis">You're all caught up!</h2>
      <p class="text-body-2 text-medium-emphasis mt-2 mx-auto" style="max-width: 360px;">
        Follow doctors to get notified when they open clinic sessions. You'll see those updates here.
      </p>
      <v-btn class="mt-4" color="primary" :to="{ name: 'home' }">Find a doctor</v-btn>
    </div>

    <template v-else>
      <div v-for="n in store.items" :key="n.id">
        <NotificationItem :notification="n" @mark-read="store.markRead(n.id)" />
        <v-divider class="my-1" />
      </div>

      <div class="text-center mt-4">
        <v-pagination
          v-if="store.meta.last_page > 1"
          v-model="page"
          :length="store.meta.last_page"
          :total-visible="5"
          @update:model-value="load({ page })"
        />
      </div>
    </template>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useNotificationsStore } from '../../stores/notificationsStore';
import NotificationItem from '../../components/NotificationItem.vue';

const store = useNotificationsStore();
const page = ref(1);

function load(params = {}) {
  store.fetchList({ per_page: 15, ...params });
}

onMounted(() => load({ page: page.value }));
</script>

<style scoped>
.notifications-page { padding-top: 24px; }
</style>
