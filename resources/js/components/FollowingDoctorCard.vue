<template>
  <v-card class="following-doctor-card" :to="doctor.slug ? { name: 'doctor.profile', params: { slug: doctor.slug } } : null" elevation="1" rounded="lg">
    <div class="d-flex align-center pa-4">
      <v-avatar size="64" :color="doctor.avatar ? undefined : 'primary'" class="mr-4">
        <v-img v-if="doctor.avatar" :src="doctor.avatar" :alt="doctor.name" cover />
        <span v-else class="text-h5 text-white">{{ initials(doctor.name) }}</span>
      </v-avatar>
      <div class="flex-grow-1 min-width-0">
        <div class="d-flex align-center">
          <span class="text-h6 text-truncate">{{ doctor.name }}</span>
          <v-icon v-if="doctor.is_verified" color="success" size="20" class="ml-1">mdi-check-decagram</v-icon>
        </div>
        <div v-if="doctor.specialty" class="text-body-2 text-medium-emphasis text-truncate">
          <v-icon size="14" class="mr-1">{{ doctor.specialty.icon || 'mdi-stethoscope' }}</v-icon>
          {{ doctor.specialty.name }}
        </div>
      </div>
    </div>

    <v-divider />

    <div class="pa-4">
      <div v-if="doctor.next_clinic" class="next-clinic">
        <div class="d-flex align-center justify-space-between mb-1">
          <span class="text-caption text-uppercase text-medium-emphasis">Next clinic</span>
          <v-chip size="x-small" color="success" variant="tonal">{{ doctor.next_clinic.available_slots }} slots</v-chip>
        </div>
        <div class="text-body-1 font-weight-medium">
          {{ doctor.next_clinic.day }}
        </div>
        <div class="text-body-2 text-medium-emphasis">
          {{ doctor.next_clinic.start_time }}–{{ doctor.next_clinic.end_time }}
          <span v-if="doctor.next_clinic.facility_name"> · {{ doctor.next_clinic.facility_name }}</span>
          <span v-if="doctor.next_clinic.facility_city"> · {{ doctor.next_clinic.facility_city }}</span>
        </div>
        <v-btn
          class="mt-3"
          color="primary"
          variant="tonal"
          size="small"
          block
          :to="{ name: 'book', params: { sessionId: doctor.next_clinic.id } }"
        >
          View clinic &amp; book
        </v-btn>
      </div>
      <div v-else class="text-center text-medium-emphasis py-4">
        <v-icon size="32" color="grey-lighten-1">mdi-calendar-blank-outline</v-icon>
        <p class="mb-0 mt-1 text-body-2">No upcoming clinic</p>
      </div>
    </div>
  </v-card>
</template>

<script setup>
defineProps({
  doctor: { type: Object, required: true },
});

function initials(name) {
  if (!name) return '?';
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0].toUpperCase())
    .join('');
}
</script>

<style scoped>
.following-doctor-card { height: 100%; transition: transform 0.15s ease, box-shadow 0.15s ease; }
.following-doctor-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.08) !important; }
.min-width-0 { min-width: 0; }
</style>
