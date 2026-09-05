<template>
  <v-list lines="three" class="py-0">
    <template v-for="(n, i) in items" :key="n.id">
      <v-list-item
        :to="linkFor(n)"
        @click="onClick(n)"
        :class="['notification-item', n.is_unread ? 'is-unread' : '']"
        :title="n.title"
      >
        <template #prepend>
          <v-avatar
            :color="iconColor(n.category)"
            variant="tonal"
            size="40"
            class="mr-3"
          >
            <v-icon>{{ iconFor(n.category) }}</v-icon>
          </v-avatar>
        </template>
        <v-list-item-title class="font-weight-medium">
          {{ n.title }}
          <span v-if="n.is_unread" class="unread-dot" aria-hidden="true"></span>
        </v-list-item-title>
        <v-list-item-subtitle class="text-wrap">
          {{ n.message }}
        </v-list-item-subtitle>
        <v-list-item-subtitle class="text-caption mt-1 text-medium-emphasis">
          {{ n.created_human }}
        </v-list-item-subtitle>
      </v-list-item>
      <v-divider v-if="i < items.length - 1" />
    </template>
  </v-list>
</template>

<script setup>
import { useNotificationsStore } from '../stores/notificationsStore';

const props = defineProps({
  items: { type: Array, required: true },
});
const emit = defineEmits(['mark-read']);
const store = useNotificationsStore();

function iconFor(category) {
  return {
    clinic_confirmed: 'mdi-calendar-check',
    session_cancelled: 'mdi-calendar-remove',
    appointment_booked: 'mdi-calendar-plus',
    appointment_cancelled: 'mdi-calendar-minus',
    appointment_checked_in: 'mdi-account-check',
    appointment_completed: 'mdi-check-circle',
    appointment_session_changed: 'mdi-map-marker-alert',
    appointment_reminder: 'mdi-alarm',
    doctor_new_booking: 'mdi-account-plus',
    doctor_appointment_cancelled: 'mdi-account-remove',
    doctor_session_changed: 'mdi-calendar-edit',
    doctor_session_cancelled: 'mdi-calendar-remove',
    facility_new_booking: 'mdi-hospital-building',
    facility_session_cancelled: 'mdi-calendar-remove',
  }[category] || 'mdi-bell-outline';
}

function iconColor(category) {
  return {
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
  }[category] || 'grey';
}


function linkFor(n) {
  const data = n.data || {};
  // Phase 10/14: backend sends clinic_session_id, not session_id
  if (data.clinic_session_id) {
    return { name: 'book', params: { sessionId: data.clinic_session_id } };
  }
  if (data.appointment_id) {
    return { name: 'appointment-detail', params: { id: data.appointment_id } };
  }
  if (data.doctor_slug) {
    return { name: 'doctor-profile', params: { slug: data.doctor_slug } };
  }
  return { name: 'notifications' };
}

function onClick(n) {
  if (n.is_unread) emit('mark-read', n.id);
  store.closeDropdown();
}
</script>

<style scoped>
.notification-item { padding: 12px 16px; }
.notification-item.is-unread { background: rgba(33, 150, 243, 0.05); }
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
