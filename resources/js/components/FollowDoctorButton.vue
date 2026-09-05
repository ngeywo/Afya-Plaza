<template>
  <div class="follow-doctor-btn">
    <v-btn
      v-if="!authStore.isAuthenticated"
      variant="outlined"
      color="primary"
      prepend-icon="mdi-account-plus-outline"
      :to="{ name: 'login', query: { redirect: $route.fullPath } }"
    >
      Follow
    </v-btn>

    <v-btn
      v-else-if="followStore.isLoading(doctorId)"
      variant="outlined"
      loading
      disabled
      prepend-icon="mdi-loading"
    />

    <v-btn
      v-else-if="followStore.isFollowing(doctorId)"
      variant="tonal"
      color="primary"
      prepend-icon="mdi-account-check"
      @click="handleUnfollow"
    >
      Following
    </v-btn>

    <v-btn
      v-else
      variant="elevated"
      color="primary"
      prepend-icon="mdi-account-plus-outline"
      @click="handleFollow"
    >
      Follow
    </v-btn>

    <v-snackbar
      v-model="snack"
      :timeout="3000"
      color="error"
      location="bottom"
    >
      {{ snackMsg }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useFollowStore } from '../stores/followStore';

const props = defineProps({
  doctorId: { type: [Number, String], required: true },
  initialFollowing: { type: Boolean, default: false },
});

const authStore = useAuthStore();
const followStore = useFollowStore();

const snack = ref(false);
const snackMsg = ref('');

onMounted(() => {
  if (authStore.isAuthenticated) {
    followStore.fetchStatus(props.doctorId);
  } else {
    followStore.isFollowingMap[props.doctorId] = props.initialFollowing;
  }
});

async function handleFollow() {
  try {
    await followStore.follow(props.doctorId);
  } catch (e) {
    snackMsg.value = followStore.error || 'Failed to follow. Please try again.';
    snack.value = true;
  }
}

async function handleUnfollow() {
  try {
    await followStore.unfollow(props.doctorId);
  } catch (e) {
    snackMsg.value = followStore.error || 'Failed to unfollow. Please try again.';
    snack.value = true;
  }
}
</script>
