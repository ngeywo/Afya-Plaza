<template>
  <v-card
    variant="text"
    :to="linkFor(notification)"
    @click="onClick"
    :class="['notification-item-card pa-4', notification.is_unread ? 'is-unread' : '']"
  >
    <div class="d-flex">
      <v-avatar
        :color="iconColor"
        variant="tonal"
        size="44"
        class="mr-3"
      >
        <v-icon>{{ icon }}</v-icon>
      </v-avatar>
      <div class="flex-grow-1">
        <div class="d-flex align-center">
          <span class="text-body-1 font-weight-medium">{{ notification.title }}</span>
          <span v-if="notification.is_unread" class="unread-dot" aria-hidden="true"></span>
        </div>
        <p class="text-body-2 text-medium-emphasis mb-1 text-wrap">
          {{ notification.message }}
        </p>
        <span class="text-caption text-medium-emphasis">{{ notification.created_human }}</span>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  notification: { type: Object, required: true },
});
const emit = defineEmits(['mark-read']);
const icon = computed(() => ({
  // Phase 10/11
  clinic_confirmed: 'mdi-calendar-check',
  session_cancelled: 'mdi-calendar-remove',
  appointment_booked: 'mdi-calendar-plus',
  appointment_cancelled: 'mdi-calendar-minus',
  appointment_checked_in: 'mdi-account-check',
  appointment_completed: 'mdi-check-circle',
  // Phase 14
  appointment_session_changed: 'mdi-map-marker-alert',
  appointment_reminder: 'mdi-alarm',
  doctor_new_booking: 'mdi-account-plus',
  doctor_appointment_cancelled: 'mdi-account-remove',
  doctor_session_changed: 'mdi-calendar-edit',
  doctor_session_cancelled: 'mdi-calendar-remove',
  facility_new_booking: 'mdi-hospital-building',
  facility_session_cancelled: 'mdi-calendar-remove',
}[props.notification.category] || 'mdi-bell-outline'));

const iconColor = computed(() => ({
  clinic_confirmed: 'success',
  session_cancelled: 'error',
  appointment_booked: 'primary',
  appointment_cancelled: 'warning',
  appointment_checked_in: 'success',
  appointment_completed: 'success',
  appointment_session_changed: 'orange',
  appointment_reminder: 'info',
  doctor_new_booking: 'primary',
  doctor_appointment_cancelled: 'error',
  doctor_session_changed: 'warning',
  doctor_session_cancelled: 'error',
  facility_new_booking: 'primary',
  facility_session_cancelled: 'error',
}[props.notification.category] || 'grey'));



function linkFor(n) {
  const data = n.data || {};
  if (data.clinic_session_id) {
    return { name: 'book', params: { sessionId: data.clinic_session_id } };
  }
  if (data.appointment_id) {
    return { name: 'appointment-detail', params: { id: data.appointment_id } };
  }
  if (data.doctor_slug) {
    return { name: 'doctor-profile', params: { slug: data.doctor_slug } };
  }
  return null;
}

function onClick() {
  if (props.notification.is_unread) emit('mark-read');
}
</script>

<style scoped>
.notification-item-card {
  background: transparent;
  transition: background 0.15s ease;
  border-radius: 12px;
}
.notification-item-card:hover {
  background: rgba(0, 0, 0, 0.03);
}
.is-unread {
  background: rgba(33, 150, 243, 0.07);
}
.unread-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: #1976d2;
  border-radius: 50%;
  margin-left: 6px;
  vertical-align: middle;
}
</style>
