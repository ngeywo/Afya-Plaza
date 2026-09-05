<template>
    <v-card class="clinic-finder-card" elevation="2" color="primary" theme="dark">
        <v-card-text class="pa-6">
            <div class="d-flex align-center gap-2 mb-3">
                <v-icon icon="mdi-map-marker-radius" size="22"></v-icon>
                <h2 class="text-h6 font-weight-bold" style="letter-spacing:1px">WHERE TO FIND THIS DOCTOR</h2>
            </div>
            <p class="text-body-2 mb-4" style="opacity:0.85">{{ subtitle }}</p>
            <div class="date-nav d-flex gap-2 flex-wrap mb-4">
                <v-btn v-for="d in weekDates" :key="d.date" size="small" variant="tonal"
                    :color="selectedDate === d.date ? 'white' : 'primary-lighten-1'"
                    @click="$emit('select-date', d.date)" class="date-btn">
                    <div class="text-center">
                        <div class="text-caption font-weight-bold" style="font-size:10px">{{ d.day }}</div>
                        <div style="font-size:15px">{{ d.dayNum }}</div>
                        <div class="text-caption" style="font-size:9px">{{ d.month }}</div>
                    </div>
                </v-btn>
            </div>
            <v-divider class="mb-4" style="opacity:0.3"></v-divider>
            <div v-if="loading"><v-progress-linear indeterminate color="white"></v-progress-linear></div>
            <div v-else-if="sessionsOnDate && sessionsOnDate.length">
                <div v-for="session in sessionsOnDate" :key="session.id" class="clinic-session-block mb-3">
                    <div class="d-flex align-center gap-2 mb-2">
                        <v-chip :color="session.is_confirmed ? 'success' : 'warning'" size="small" variant="flat" class="font-weight-bold text-uppercase">
                            {{ session.is_confirmed ? 'CONFIRMED' : 'PENDING' }}
                        </v-chip>
                        <span class="text-caption">{{ dateLabel }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-1">
                        <v-icon icon="mdi-hospital-building" size="20" color="white"></v-icon>
                        <span class="text-h6 font-weight-medium">{{ session.facility.name }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-1">
                        <v-icon icon="mdi-map-marker" size="16" color="white"></v-icon>
                        <span class="text-body-2">{{ session.facility.city }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-clock-outline" size="16" color="white"></v-icon>
                        <span class="text-body-1 font-weight-medium">{{ session.start_time }} - {{ session.end_time }}</span>
                    </div>
                    <div class="d-flex align-center gap-3 flex-wrap">
                        <v-chip color="white" variant="outlined" size="small" prepend-icon="mdi-calendar-check">
                            {{ session.available_slots }} slot{{ session.available_slots === 1 ? '' : 's' }} available
                        </v-chip>
                        <v-btn v-if="session.is_bookable" color="white" class="text-primary" variant="flat" :to="'/book/' + session.id" size="large" prepend-icon="mdi-calendar-plus">
                            Book Appointment
                        </v-btn>
                        <v-btn v-else color="white" variant="outlined" size="large" disabled>
                            {{ session.available_slots <= 0 ? 'Fully Booked' : 'Not bookable' }}
                        </v-btn>
                    </div>
                </div>
            </div>
            <div v-else>
                <div class="d-flex align-center gap-2 mb-3">
                    <v-icon icon="mdi-calendar-remove" size="32" color="white"></v-icon>
                    <div>
                        <h3 class="text-h6 font-weight-bold mb-1">No clinic on {{ dateLabel }}</h3>
                        <p class="text-body-2" style="opacity:0.85">No confirmed session for this date.</p>
                    </div>
                </div>
                <div v-if="nextClinic" class="mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.2)">
                    <div class="text-caption text-uppercase font-weight-bold mb-2" style="opacity:0.85">Next Clinic</div>
                    <div class="d-flex align-center gap-2 mb-1">
                        <v-icon icon="mdi-calendar" size="16" color="white"></v-icon>
                        <span class="text-body-1 font-weight-medium">{{ nextClinic.day_short }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-1">
                        <v-icon icon="mdi-hospital-building" size="16" color="white"></v-icon>
                        <span class="text-body-2">{{ nextClinic.facility.name }}, {{ nextClinic.facility.city }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 mb-3">
                        <v-icon icon="mdi-clock-outline" size="16" color="white"></v-icon>
                        <span class="text-body-2">{{ nextClinic.start_time }} - {{ nextClinic.end_time }}</span>
                    </div>
                    <v-btn v-if="nextClinic.is_bookable" color="white" class="text-primary" variant="flat" :to="'/book/' + nextClinic.id" prepend-icon="mdi-calendar-plus">
                        Book this clinic
                    </v-btn>
                </div>
            </div>
        </v-card-text>
    </v-card>
</template>
<script setup>
import { ref, computed, watch } from 'vue';
const props = defineProps({
    doctor: { type: Object, required: true },
    loading: { type: Boolean, default: false },
});
const selectedDate = ref(todayStr());
const selectedDateSessions = ref([]);
const loadingAvail = ref(false);
const firstName = computed(() => { const n = props.doctor.name || ''; return n.split(' ')[1] || n.split(' ')[0] || ''; });
const subtitle = computed(() => 'Pick a date to see where Dr. ' + firstName.value + ' is practicing');
const todayStr = () => new Date().toISOString().slice(0, 10);
const weekDates = computed(() => {
    const dates = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    for (let i = 0; i < 7; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        dates.push({ date: d.toISOString().slice(0, 10), day: d.toLocaleDateString('en-KE', { weekday: 'short' }), dayNum: d.getDate(), month: d.toLocaleDateString('en-KE', { month: 'short' }) });
    }
    return dates;
});
const dateLabel = computed(() => {
    const d = selectedDate.value;
    if (!d) return '';
    const today = todayStr();
    if (d === today) return 'TODAY';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short' }).toUpperCase();
});
const upcomingSessions = computed(() => props.doctor.upcoming_sessions || []);
const sessionsOnDate = computed(() => upcomingSessions.value.filter(s => s.session_date === selectedDate.value));
const nextClinic = computed(() => props.doctor.next_clinic || null);
function todayShort() { return todayStr(); }
watch(selectedDate, () => {});
</script>
<style scoped>
.clinic-session-block { background: rgba(255,255,255,0.12); border-radius: 12px; padding: 14px; border: 1px solid rgba(255,255,255,0.15); }
.date-btn { min-width: 58px; }
</style>