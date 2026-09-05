<template>
    <v-card class="session-card h-100" elevation="0" border>
        <v-card-text class="pa-4">
            <div class="d-flex align-start gap-3 mb-3">
                <v-avatar size="64" color="primary" class="flex-shrink-0">
                    <v-img v-if="item.doctor.avatar" :src="item.doctor.avatar" :alt="item.doctor.name"></v-img>
                    <span v-else class="text-h5 font-weight-bold text-white">{{ initials }}</span>
                </v-avatar>
                <div class="flex-grow-1">
                    <div class="d-flex align-center gap-1 mb-1 flex-wrap">
                        <span class="text-h6 font-weight-bold">{{ item.doctor.name }}</span>
                        <v-icon v-if="item.doctor.is_verified" icon="mdi-check-decagram" color="primary" size="18"></v-icon>
                    </div>
                    <div class="d-flex align-center gap-1 mb-1 flex-wrap">
                        <v-chip v-for="s in primarySpecialty" :key="s.id" size="x-small" variant="tonal" :prepend-icon="s.icon">
                            {{ s.name }}
                        </v-chip>
                        <span v-if="item.doctor.years_of_experience" class="text-caption text-medium-emphasis ml-1">
                            {{ item.doctor.years_of_experience }}+ yrs
                        </span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-center gap-1 mb-2">
                <v-icon icon="mdi-calendar-today" size="14" color="primary"></v-icon>
                <span class="text-caption font-weight-bold text-primary" style="letter-spacing:0.5px">{{ dateLabel }}</span>
            </div>

            <div class="session-info mb-3">
                <div class="d-flex align-center gap-1 mb-1">
                    <v-icon icon="mdi-hospital-building" size="16" color="medium-emphasis"></v-icon>
                    <span class="text-body-2 font-weight-medium">{{ item.facility.name }}</span>
                </div>
                <div class="d-flex align-center gap-1 mb-1">
                    <v-icon icon="mdi-map-marker" size="14" color="medium-emphasis"></v-icon>
                    <span class="text-caption text-medium-emphasis">
                        {{ item.facility.city }}<span v-if="item.facility.county">, {{ item.facility.county }}</span>
                    </span>
                </div>
                <div class="d-flex align-center gap-1">
                    <v-icon icon="mdi-clock-outline" size="14" color="medium-emphasis"></v-icon>
                    <span class="text-caption text-medium-emphasis">{{ item.start_time }} - {{ item.end_time }}</span>
                </div>
            </div>

            <div class="d-flex align-center justify-space-between mb-2">
                <v-chip :color="availabilityColor" size="small" variant="tonal" :prepend-icon="availabilityIcon">
                    {{ availabilityLabel }}
                </v-chip>
                <span class="text-caption text-medium-emphasis">
                    {{ item.available_slots }} slot{{ item.available_slots === 1 ? '' : 's' }} left
                </span>
            </div>

            <div v-if="item.consultation_fee" class="d-flex align-center gap-1">
                <v-icon icon="mdi-cash" size="14" color="medium-emphasis"></v-icon>
                <span class="text-caption text-medium-emphasis">KSh {{ item.consultation_fee }}</span>
            </div>

        </v-card-text>
        <v-card-actions class="px-4 pb-4 pt-0 gap-2">
            <v-btn color="primary" variant="tonal" size="small" :to="`/doctors/${item.doctor.slug}`" prepend-icon="mdi-account">
                View Doctor
            </v-btn>
            <v-btn
                v-if="item.is_bookable"
                color="primary" variant="flat" size="small"
                :to="`/doctors/${item.doctor.slug}?date=${date}&session=${item.id}`"
                prepend-icon="mdi-calendar-plus"
            >
                Book
            </v-btn>
        </v-card-actions>
    </v-card>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    date: { type: String, default: null },
});

const initials = computed(() => (props.item.doctor.name || '').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase());
const primarySpecialty = computed(() => {
    const specs = props.item.doctor.specialties || [];
    const primary = specs.filter(s => s.is_primary);
    return primary.length ? primary.slice(0, 1) : specs.slice(0, 1);
});

const dateLabel = computed(() => {
    if (!props.date) return 'TODAY';
    const today = new Date().toISOString().slice(0, 10);
    if (props.date === today) return 'TODAY';
    return new Date(props.date).toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short' }).toUpperCase();
});

const availabilityColor = computed(() => {
    const slots = props.item.available_slots;
    if (slots <= 0) return 'error';
    if (slots <= 3) return 'warning';
    return 'success';
});

const availabilityLabel = computed(() => {
    const slots = props.item.available_slots;
    if (slots <= 0) return 'Fully booked';
    if (slots <= 3) return 'Limited availability';
    return 'Available';
});

const availabilityIcon = computed(() => {
    const slots = props.item.available_slots;
    if (slots <= 0) return 'mdi-close-circle';
    if (slots <= 3) return 'mdi-alert-circle';
    return 'mdi-check-circle';
});
</script>


<style scoped>
.session-card {
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.06);
}
.session-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
    border-color: rgb(var(--v-theme-primary));
}
.session-info {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 8px;
    padding: 10px 12px;
}
</style>