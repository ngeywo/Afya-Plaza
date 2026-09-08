<template>
  <v-container class="not-found-page" max-width="640">
    <div class="text-center pa-8">
      <div class="code-block">
        <span class="code-digit">4</span>
        <span class="code-emoji" aria-hidden="true">🚑</span>
        <span class="code-digit">4</span>
      </div>

      <h1 class="text-h4 font-weight-bold mt-8">Page not found</h1>
      <p class="text-body-1 text-medium-emphasis mt-3 mx-auto" style="max-width: 460px;">
        The page you're looking for doesn't exist, has been moved, or is no longer available.
      </p>

      <div class="d-flex flex-wrap justify-center mt-6">
        <v-btn color="primary" :to="{ name: 'home' }" prepend-icon="mdi-home" class="mr-2 mb-2">
          Back to home
        </v-btn>
        <v-btn
          variant="tonal"
          color="primary"
          :to="{ name: 'doctor-search' }"
          prepend-icon="mdi-magnify"
          class="mr-2 mb-2"
        >
          Find a doctor
        </v-btn>
        <v-btn
          v-if="auth.isAuthenticated"
          variant="text"
          :to="{ name: 'my-appointments' }"
          prepend-icon="mdi-calendar-check"
          class="mb-2"
        >
          My appointments
        </v-btn>
      </div>

      <v-divider class="my-8" />

      <div v-if="suggestedPaths.length" class="text-left">
        <h2 class="text-subtitle-1 font-weight-bold mb-2">Or try one of these:</h2>
        <v-list density="compact" class="suggested-list">
          <v-list-item
            v-for="s in suggestedPaths"
            :key="s.to"
            :to="s.to"
            :prepend-icon="s.icon"
            :title="s.label"
            :subtitle="s.subtitle"
          />
        </v-list>
      </div>

      <p class="text-caption text-medium-emphasis mt-8">
        Error 404 — If you believe this is a bug, please contact support.
      </p>
    </div>
  </v-container>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../stores/authStore';

const auth = useAuthStore();

const suggestedPaths = computed(() => {
    const items = [
        { to: { name: 'home' }, icon: 'mdi-home', label: 'Home', subtitle: 'Find doctors on the marketplace' },
        { to: { name: 'doctor-search' }, icon: 'mdi-magnify', label: 'Search doctors', subtitle: 'Browse the directory' },
    ];
    if (auth.isAuthenticated) {
        if (auth.isPatient) {
            items.push(
                { to: { name: 'my-doctors' }, icon: 'mdi-doctor', label: 'My doctors', subtitle: 'Doctors you follow' },
                { to: { name: 'notifications' }, icon: 'mdi-bell-outline', label: 'Notifications', subtitle: 'Your updates' },
                { to: { name: 'my-appointments' }, icon: 'mdi-calendar-check', label: 'My appointments', subtitle: 'Your bookings' },
            );
        }
        if (auth.isDoctor) {
            items.push({ to: { name: 'doctor-dashboard' }, icon: 'mdi-doctor', label: 'Doctor Workspace', subtitle: 'Your clinic dashboard' });
        }
        if (auth.isFacility) {
            items.push({ to: { name: 'facility-dashboard' }, icon: 'mdi-hospital-building', label: 'Facility Workspace', subtitle: 'Manage your facility' });
        }
        if (auth.isPlatformOperator) {
            items.push({ to: { name: 'admin-dashboard' }, icon: 'mdi-shield-account', label: 'Control Centre', subtitle: 'Admin dashboard' });
        }
    }
    return items.slice(0, 5);
});
</script>

<style scoped>
.not-found-page {
    padding-top: 48px;
    padding-bottom: 48px;
}
.code-block {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    user-select: none;
}
.code-digit {
    font-size: 96px;
    line-height: 1;
    font-weight: 800;
    color: #1976d2;
    opacity: 0.85;
}
.code-emoji {
    font-size: 96px;
    line-height: 1;
    display: inline-block;
    transform: rotate(-12deg);
    animation: wiggle 3s ease-in-out infinite;
}
@keyframes wiggle {
    0%, 100% { transform: rotate(-12deg); }
    50% { transform: rotate(8deg); }
}
.suggested-list {
    background: transparent;
}
</style>
