<template>
    <v-dialog :model-value="modelValue" max-width="640" @update:model-value="v => emit('update:modelValue', v)">
        <v-card>
            <v-card-title class="d-flex align-center pa-4">
                <v-icon icon="mdi-calendar-edit" color="primary" class="mr-2" />
                <span>Reschedule appointment</span>
            </v-card-title>
            <v-card-text class="pa-4">
                <div class="text-body-2 text-medium-emphasis mb-4">
                    Current:
                    <strong>{{ formatDate(apt?.appointment_date) }}</strong>
                    at {{ apt?.start_time }} — {{ apt?.facility?.name }}
                </div>

                <div class="text-caption font-weight-bold text-medium-emphasis mb-2">PICK A NEW DATE</div>
                <div class="d-flex gap-2 flex-wrap mb-4">
                    <v-btn v-for="d in dateOptions" :key="d.date" size="small" variant="outlined"
                        :color="selectedDate === d.date ? 'primary' : 'default'"
                        @click="selectDate(d.date)">
                        <div class="text-center">
                            <div class="text-caption font-weight-bold" style="font-size: 10px;">{{ d.day }}</div>
                            <div style="font-size: 14px;">{{ d.dayNum }}</div>
                        </div>
                    </v-btn>
                </div>

                <div v-if="sessionsLoading" class="text-center py-4">
                    <v-progress-circular indeterminate color="primary" size="24" />
                    <div class="text-caption text-medium-emphasis mt-2">Loading clinics...</div>
                </div>
                <template v-else>
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2">PICK A CLINIC</div>
                    <v-select v-model="selectedSessionId" :items="sessionItems" item-title="label" item-value="id"
                        label="Available clinics" variant="outlined" density="compact" hide-details
                        :no-data-text="sessionItems.length ? '' : 'No clinics on this date'" class="mb-4"
                        @update:model-value="loadSlots" />
                </template>

                <div v-if="slotsLoading" class="text-center py-4">
                    <v-progress-circular indeterminate color="primary" size="24" />
                    <div class="text-caption text-medium-emphasis mt-2">Loading times...</div>
                </div>
                <template v-else-if="selectedSessionId">
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2">PICK A TIME</div>
                    <div class="d-flex flex-wrap gap-2">
                        <v-btn v-for="s in slots" :key="s.start_time" size="small"
                            :variant="selectedSlot === s.start_time ? 'flat' : 'outlined'"
                            :color="s.is_available ? (selectedSlot === s.start_time ? 'primary' : 'default') : 'error'"
                            :disabled="!s.is_available"
                            @click="selectSlot(s)">
                            {{ s.start_time }}
                        </v-btn>
                    </div>
                    <div v-if="!slots.length" class="text-caption text-medium-emphasis mt-2">
                        No bookable times for this clinic.
                    </div>
                </template>

                <v-textarea v-model="reason" label="Reason (optional)" variant="outlined" density="compact"
                    rows="2" maxlength="500" class="mt-4" />
            </v-card-text>
            <v-card-actions class="pa-4">
                <v-btn variant="text" @click="close">Keep current</v-btn>
                <v-spacer />
                <v-btn color="primary" variant="flat" :disabled="!selectedSlot" :loading="saving" @click="confirm">
                    Confirm reschedule
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
<script setup>
// Phase 17: RescheduleDialog — real alternatives only.
// Sessions and slots come from the same availability endpoints used for
// booking (/doctors/{id}/availability + /sessions/{id}/slots). The backend
// (AppointmentService::reschedule) remains authoritative: same-doctor,
// bookable, capacity and slot-conflict checks run server-side.
import { ref, computed, watch } from 'vue';
import { doctorService, sessionSearchService } from '../../services/doctorService';
import { useAppointmentStore } from '../../stores/appointmentStore';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    apt: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'rescheduled']);

const aptStore = useAppointmentStore();

const selectedDate = ref(null);
const sessions = ref([]);
const sessionsLoading = ref(false);
const selectedSessionId = ref(null);
const slots = ref([]);
const slotsLoading = ref(false);
const selectedSlot = ref(null);
const reason = ref('');
const saving = ref(false);

const dateOptions = computed(() => {
    const dates = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    for (let i = 1; i <= 14; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        dates.push({
            date: d.toISOString().slice(0, 10),
            day: d.toLocaleDateString('en-KE', { weekday: 'short' }),
            dayNum: d.getDate(),
        });
    }
    return dates;
});

const sessionItems = computed(() => sessions.value.map(s => ({
    id: s.id,
    label: `${s.facility?.name ?? 'Clinic'} · ${s.start_time}–${s.end_time}`,
})));

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-KE', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });
}

function selectDate(date) {
    selectedDate.value = date;
    selectedSessionId.value = null;
    selectedSlot.value = null;
    slots.value = [];
    loadSessions();
}

async function loadSessions() {
    if (!props.apt?.doctor?.id || !selectedDate.value) return;
    sessionsLoading.value = true;
    sessions.value = [];
    try {
        const resp = await doctorService.availability(props.apt.doctor.id, selectedDate.value);
        sessions.value = (resp.data?.sessions || []).filter(s => s.is_bookable);
    } catch (e) {
        sessions.value = [];
    } finally {
        sessionsLoading.value = false;
    }
}

async function loadSlots() {
    selectedSlot.value = null;
    if (!selectedSessionId.value) return;
    slotsLoading.value = true;
    try {
        const data = await sessionSearchService.slots(selectedSessionId.value);
        slots.value = data.data || [];
    } catch (e) {
        slots.value = [];
    } finally {
        slotsLoading.value = false;
    }
}

function selectSlot(slot) {
    if (!slot.is_available) return;
    selectedSlot.value = slot.start_time;
}

async function confirm() {
    if (!selectedSessionId.value || !selectedSlot.value) return;
    saving.value = true;
    try {
        const result = await aptStore.reschedule(props.apt.id, selectedSessionId.value, selectedSlot.value, reason.value);
        emit('rescheduled', result?.data ?? null);
        close();
    } catch (e) {
        const code = e.response?.data?.code;
        if (code === 'SLOT_TAKEN') {
            await loadSlots();
            selectedSlot.value = null;
            alert('That time was just taken. Please pick another.');
        } else {
            alert(e.response?.data?.error || 'Failed to reschedule. Please try again.');
        }
    } finally {
        saving.value = false;
    }
}

function close() {
    emit('update:modelValue', false);
}

watch(() => props.modelValue, (open) => {
    if (open) {
        selectedDate.value = null;
        selectedSessionId.value = null;
        selectedSlot.value = null;
        sessions.value = [];
        slots.value = [];
        reason.value = '';
    }
});
</script>
