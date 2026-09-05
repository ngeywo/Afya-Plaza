<template>
    <v-card class="doctor-card h-100" :to="`/doctors/${doctor.slug}`">
        <v-card-text class="pa-4">
            <div class="d-flex align-start gap-3 mb-3">
                <v-avatar size="64" color="primary" class="flex-shrink-0">
                    <v-img v-if="doctor.avatar" :src="doctor.avatar" :alt="doctor.name"></v-img>
                    <span v-else class="text-h5 font-weight-bold text-white">{{ initials }}</span>
                </v-avatar>
                <div class="flex-grow-1">
                    <div class="d-flex align-center gap-1 mb-1 flex-wrap">
                        <span class="text-h6 font-weight-bold">{{ doctor.name }}</span>
                        <v-icon v-if="doctor.is_verified" icon="mdi-check-decagram" color="primary" size="18"></v-icon>
                    </div>
                    <div class="d-flex align-center gap-1 mb-1 flex-wrap">
                        <v-chip v-for="s in primarySpecialties" :key="s.id" size="x-small" variant="tonal" :prepend-icon="s.icon">
                            {{ s.name }}
                        </v-chip>
                        <span v-if="doctor.years_of_experience" class="text-caption text-medium-emphasis ml-1">
                            {{ doctor.years_of_experience }}+ yrs
                        </span>
                    </div>
                </div>
            </div>
            <template v-if="session">
                <div class="d-flex align-center gap-1 mb-2">
                    <v-icon icon="mdi-calendar-today" size="14" color="primary"></v-icon>
                    <span class="text-caption font-weight-bold text-primary" style="letter-spacing: 0.5px;">{{ sessionDayLabel }}</span>
                </div>
                <div class="session-info mb-2">
                    <div class="d-flex align-center gap-1 mb-1">
                        <v-icon icon="mdi-hospital-building" size="16" color="medium-emphasis"></v-icon>
                        <span class="text-body-2 font-weight-medium">{{ session.facility.name }}</span>
                    </div>
                    <div class="d-flex align-center gap-1 mb-1">
                        <v-icon icon="mdi-map-marker" size="14" color="medium-emphasis"></v-icon>
                        <span class="text-caption text-medium-emphasis">
                            {{ session.facility.city }}<span v-if="session.facility.county">, {{ session.facility.county }}</span>
                        </span>
                    </div>
                    <div class="d-flex align-center gap-1">
                        <v-icon icon="mdi-clock-outline" size="14" color="medium-emphasis"></v-icon>
                        <span class="text-caption text-medium-emphasis">{{ session.start_time }} - {{ session.end_time }}</span>
                    </div>
                </div>
                <div class="d-flex align-center justify-space-between mt-3">
                    <v-chip :color="availabilityColor" size="small" variant="tonal" :prepend-icon="availabilityIcon">
                        {{ availabilityLabel }}
                    </v-chip>
                    <span class="text-caption text-medium-emphasis">{{ session.available_slots }} slot{{ session.available_slots === 1 ? \'\' : \'s\' }} left</span>
                </div>
            </template>
            <template v-else>
                <div class="no-session-box">
                    <v-icon icon="mdi-calendar-remove" size="18" color="grey"></v-icon>
                    <span class="text-body-2 text-medium-emphasis ml-1">No clinics on this date</span>
                </div>
            </template>
        </v-card-text>
        <v-card-actions class="px-4 pb-4 pt-0">
            <v-btn color="primary" variant="tonal" block :to="`/doctors/${doctor.slug}`" prepend-icon="mdi-arrow-right">
                View Doctor
            </v-btn>
        </v-card-actions>
    </v-card>
</template>
<script setup>
import { computed } from "vue";
const props = defineProps({
    doctor: { type: Object, required: true },
    session: { type: Object, default: null },
    date: { type: String, default: null },
});
const initials = computed(() => (props.doctor.name || "").split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase());
const primarySpecialties = computed(() => {
    const specs = props.doctor.specialties || [];
    const primary = specs.filter(s => s.is_primary);
    return primary.length ? primary.slice(0, 1) : specs.slice(0, 1);
});
const sessionDayLabel = computed(() => {
    if (!props.date) return "TODAY";
    const today = new Date().toISOString().slice(0, 10);
    if (props.date === today) return "TODAY";
    return new Date(props.date).toLocaleDateString("en-KE", { weekday: "short", day: "numeric", month: "short" }).toUpperCase();
});
const availabilityColor = computed(() => {
    if (!props.session) return "grey";
    const slots = props.session.available_slots;
    if (slots <= 0) return "error";
    if (slots <= 3) return "warning";
    return "success";
});
const availabilityLabel = computed(() => {
    if (!props.session) return "Not bookable";
    const slots = props.session.available_slots;
    if (slots <= 0) return "Fully booked";
    if (slots <= 3) return "Limited";
    return "Available";
});
const availabilityIcon = computed(() => {
    if (!props.session) return "mdi-close-circle";
    const slots = props.session.available_slots;
    if (slots <= 0) return "mdi-close-circle";
    if (slots <= 3) return "mdi-alert-circle";
    return "mdi-check-circle";
});
</script>
<style scoped>
.doctor-card { transition: box-shadow 0.2s ease, transform 0.2s ease; cursor: pointer; border: 1px solid rgba(0,0,0,0.06); }
.doctor-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.1); transform: translateY(-2px); border-color: rgb(var(--v-theme-primary)); }
.session-info { background: rgb(var(--v-theme-surface)); border: 1px solid rgba(0,0,0,0.06); border-radius: 8px; padding: 10px 12px; }
.no-session-box { display: flex; align-items: center; padding: 12px; background: rgba(0,0,0,0.02); border: 1px dashed rgba(0,0,0,0.1); border-radius: 8px; }
</style>
