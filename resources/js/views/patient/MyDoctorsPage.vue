<template>
  <v-container class="my-doctors-page" max-width="1000">
    <div class="d-flex align-center mb-6">
      <v-btn icon variant="text" @click="$router.back()">
        <v-icon>mdi-arrow-left</v-icon>
      </v-btn>
      <h1 class="text-h5 ml-2">My Doctors</h1>
      <v-spacer />
      <v-btn
        variant="tonal"
        color="primary"
        prepend-icon="mdi-magnify"
        :to="{ name: 'home' }"
      >
        Find more
      </v-btn>
    </div>

    <div v-if="!authStore.isAuthenticated" class="text-center py-16">
      <v-icon size="80" color="grey-lighten-2">mdi-account-off-outline</v-icon>
      <h2 class="text-h6 mt-6 text-medium-emphasis">Sign in to see your doctors</h2>
      <p class="text-body-2 text-medium-emphasis mt-2">Follow doctors to build your personal healthcare team.</p>
      <v-btn class="mt-4" color="primary" :to="{ name: 'login' }">Sign in</v-btn>
    </div>

    <div v-else-if="followStore.loadingList" class="text-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-alert v-else-if="followStore.error" type="error" variant="tonal" class="mb-4">
      {{ followStore.error }}
      <template #append>
        <v-btn variant="text" size="small" @click="followStore.fetchMyDoctors()">Retry</v-btn>
      </template>
    </v-alert>

    <div v-else-if="followStore.followingDoctors.length === 0" class="text-center py-16">
      <v-icon size="80" color="grey-lighten-2">mdi-doctor</v-icon>
      <h2 class="text-h6 mt-6 text-medium-emphasis">No doctors yet</h2>
      <p class="text-body-2 text-medium-emphasis mt-2 mx-auto" style="max-width: 360px;">
        Follow doctors to get notified when they open new clinic sessions. Their upcoming clinics will appear here.
      </p>
      <v-btn class="mt-4" color="primary" :to="{ name: 'home' }">Find a doctor</v-btn>
    </div>

    <template v-else>
      <div class="d-flex align-center mb-4">
        <span class="text-body-1 text-medium-emphasis">
          {{ followStore.followingDoctors.length }} doctor{{ followStore.followingDoctors.length !== 1 ? 's' : '' }} followed
        </span>
      </div>

      <v-row>
        <v-col
          v-for="doctor in followStore.followingDoctors"
          :key="doctor.id"
          cols="12"
          sm="6"
          md="4"
        >
          <FollowingDoctorCard :doctor="doctor" />
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup>
import { onMounted } from 'vue';
import { useFollowStore } from '../../stores/followStore';
import { useAuthStore } from '../../stores/authStore';
import FollowingDoctorCard from '../../components/FollowingDoctorCard.vue';

const followStore = useFollowStore();
const authStore = useAuthStore();

onMounted(() => {
  if (authStore.isAuthenticated) {
    followStore.fetchMyDoctors();
  }
});
</script>

<style scoped>
.my-doctors-page { padding-top: 24px; }
</style>
