<template>
    <v-card class="doctor-card h-100" :to="`/doctors/${doctor.slug}`">
        <v-card-text class="pa-4">
            <div class="d-flex align-start gap-4">
                <v-avatar size="64" color="primary" class="flex-shrink-0">
                    <v-img v-if="doctor.avatar" :src="doctor.avatar" :alt="doctor.name"></v-img>
                    <span v-else class="text-h5 font-weight-bold text-white">
                        {{ doctor.name.split(' ').map(n => n[0]).join('').slice(0, 2) }}
                    </span>
                </v-avatar>
                <div class="flex-grow-1">
                    <div class="d-flex align-center gap-2 mb-1">
                        <span class="text-h6 font-weight-bold">{{ doctor.name }}</span>
                        <v-chip v-if="doctor.is_verified" size="x-small" color="success" variant="tonal">
                            <v-icon icon="mdi-check-circle" size="12" start></v-icon>
                            Verified
                        </v-chip>
                    </div>
                    <div class="d-flex align-center gap-1 mb-2">
                        <v-chip v-for="s in doctor.specialties.filter(s => s.is_primary).slice(0, 1)" :key="s.id"
                            size="small" variant="tonal" :prepend-icon="s.icon">
                            {{ s.name }}
                        </v-chip>
                        <v-chip size="small" variant="tonal">
                            {{ doctor.years_of_experience }}+ years
                        </v-chip>
                    </div>
                    <div class="text-body-2 text-medium-emphasis mb-3">
                        <v-icon icon="mdi-hospital-building" size="14" class="mr-1"></v-icon>
                        {{ doctor.facilities.map(f => f.name).slice(0, 2).join(', ') }}
                    </div>
                </div>
            </div>

            <!-- Session Today -->
            <div v-if="doctor.session" class="mt-3 session-today">
                <div class="text-caption text-medium-emphasis mb-1">
                    {{ doctor.session.day.toUpperCase() }}
                </div>
                <div class="d-flex align-center justify-space-between">
                    <div>
                        <div class="d-flex align-center gap-1">
                            <v-icon icon="mdi-map-marker" size="16" color="primary"></v-icon>
                            <span class="text-body-2 font-weight-medium">{{ doctor.session.facility.name }}</span>
                        </div>
                        <div class="text-body-2 text-medium-emphasis">
                            {{ doctor.session.start_time }} – {{ doctor.session.end_time }}
                        </div>
                    </div>
                    <div class="text-right">
                        <v-chip :color="doctor.session.is_confirmed ? 'success' : 'warning'" size="small" variant="tonal">
                            {{ doctor.session.is_confirmed ? '🟢 Confirmed' : '🟡 Pending' }}
                        </v-chip>
                        <div class="text-caption text-medium-emphasis mt-1">
                            {{ doctor.session.available_slots }} slots
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="mt-3 text-body-2 text-medium-emphasis">
                <v-icon icon="mdi-calendar-remove" size="16" class="mr-1"></v-icon>
                No confirmed session today
            </div>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
            <v-btn color="primary" variant="tonal" block :to="`/doctors/${doctor.slug}`">
                View Profile
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<script setup>
defineProps({
    doctor: { type: Object, required: true },
});
</script>

<style scoped>
.doctor-card {
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    cursor: pointer;
}
.doctor-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}
.session-today {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px;
    padding: 12px;
}
</style>
