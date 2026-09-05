<template>
    <v-card class="doctor-search-card h-100" elevation="0" border>
        <v-card-text class="pa-5">
            <div class="d-flex align-start gap-4 mb-4">
                <v-avatar size="72" color="primary" class="flex-shrink-0">
                    <v-img v-if="doctor.avatar" :src="doctor.avatar" :alt="doctor.name" />
                    <span v-else class="text-h5 font-weight-bold text-white">{{ initials }}</span>
                </v-avatar>
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex align-center gap-2 mb-1 flex-wrap">
                        <span class="text-h6 font-weight-bold text-truncate">{{ doctor.name }}</span>
                        <v-icon v-if="doctor.is_verified" icon="mdi-check-decagram" color="primary" size="18" />
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        <v-chip v-if="doctor.primary_specialty" :prepend-icon="doctor.primary_specialty.icon || 'mdi-medical-bag'" variant="tonal" color="primary" size="small">{{ doctor.primary_specialty.name }}</v-chip>
                        <v-chip v-for="s in otherSpecialties" :key="s.id" variant="outlined" size="x-small" class="text-medium-emphasis">{{ s.name }}</v-chip>
                    </div>
                    <div class="d-flex align-center gap-3 text-caption text-medium-emphasis">
                        <span v-if="doctor.years_of_experience">{{ doctor.years_of_experience }}+ yrs</span>
                        <span v-if="doctor.consultation_fee">KSh {{ formatFee(doctor.consultation_fee) }}</span>
                    </div>
                </div>
            </div>
            <div class="session-highlight mb-3">
                <div class="d-flex align-center gap-2 mb-1">
                    <v-chip :color="primarySession.is_confirmed ? 'success' : 'warning'" size="x-small" variant="flat" class="font-weight-bold text-uppercase">{{ primarySession.is_confirmed ? 'Confirmed' : 'Pending' }}</v-chip>
                    <span class="text-caption text-medium-emphasis">{{ metaDate }}</span>
                    <span v-if="sessionCount > 1" class="text-caption text-primary font-weight-bold">+{{ sessionCount - 1 }} location{{ sessionCount > 2 ? 's' : '' }}</span>
                </div>
                <div class="d-flex align-center gap-2 mb-1"><v-icon icon="mdi-hospital-building" size="18" color="primary" /><span class="text-body-2 font-weight-medium">{{ primarySession.facility.name }}</span></div>
                <div class="d-flex align-center gap-2 mb-1"><v-icon icon="mdi-map-marker" size="16" color="medium-emphasis" /><span class="text-caption text-medium-emphasis">{{ primarySession.facility.city }}</span></div>
                <div class="d-flex align-center gap-2"><v-icon icon="mdi-clock-outline" size="16" color="medium-emphasis" /><span class="text-caption text-medium-emphasis">{{ primarySession.start_time }} - {{ primarySession.end_time }}</span></div>
            </div>
            <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                    <v-chip :color="availabilityColor" size="small" variant="tonal" :prepend-icon="availabilityIcon">{{ availabilityLabel }}</v-chip>
                    <span v-if="primarySession.available_slots > 0" class="text-caption text-medium-emphasis">{{ primarySession.available_slots }} slot{{ primarySession.available_slots === 1 ? '' : 's' }} left</span>
                </div>
                <div class="d-flex gap-2">
                    <v-btn color="primary" variant="tonal" size="small" :to="profileLink" prepend-icon="mdi-account">Profile</v-btn>
                    <v-btn v-if="primarySession.is_bookable" color="primary" variant="flat" size="small" :to="bookLink" prepend-icon="mdi-calendar-plus">Book</v-btn>
                </div>
            </div>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
    item: { type: Object, required: true },
    meta: { type: Object, default: () => ({}) },
    date: { type: String, default: '' },
});
const doctor = computed(() => props.item.doctor || {});
const primarySession = computed(() => props.item.primary_session || {});
const sessionCount = computed(() => props.item.session_count || 1);
const profileLink = computed(() => '/doctors/' + (doctor.value.slug || ''));
const bookLink = computed(() => '/book/' + (primarySession.value.id || ''));
const initials = computed(() => (doctor.value.name || '').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase());
const otherSpecialties = computed(() => {
    const specs = doctor.value.specialties || [];
    const primary = doctor.value.primary_specialty;
    return specs.filter(s => !primary || s.id !== primary.id).slice(0, 2);
});
const metaDate = computed(() => {
    if (!props.date) return 'TODAY';
    const today = new Date().toISOString().slice(0, 10);
    if (props.date === today) return 'TODAY';
    return new Date(props.date + 'T00:00:00').toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short' }).toUpperCase();
});
const slots = computed(() => primarySession.value.available_slots || 0);
const availabilityColor = computed(() => {
    if (!primarySession.value.is_bookable) return 'grey';
    if (slots.value <= 0) return 'error';
    if (slots.value <= 3) return 'warning';
    return 'success';
});
const availabilityLabel = computed(() => {
    if (!primarySession.value.is_bookable) return 'Not bookable';
    if (slots.value <= 0) return 'Fully booked';
    if (slots.value <= 3) return 'Limited';
    return 'Available';
});
const availabilityIcon = computed(() => {
    if (!primarySession.value.is_bookable) return 'mdi-close-circle';
    if (slots.value <= 0) return 'mdi-close-circle';
    if (slots.value <= 3) return 'mdi-alert-circle';
    return 'mdi-check-circle';
});
function formatFee(fee) {
    const n = parseFloat(fee);
    return isNaN(n) ? '' : n.toLocaleString();
}
</script>
<style scoped>
.doctor-search-card {
    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.07) !important;
}
.doctor-search-card:hover {
    box-shadow: 0 4px 20px rgba(15, 118, 110, 0.1) !important;
    transform: translateY(-2px);
    border-color: rgb(var(--v-theme-primary)) !important;
}
.session-highlight {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 10px;
    padding: 12px 14px;
}
.min-width-0 { min-width: 0; }
.text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>