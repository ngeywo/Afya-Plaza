import { defineStore } from 'pinia';
import { ref } from 'vue';
import { appointmentService } from '../services/appointmentService';

export const useAppointmentStore = defineStore('appointment', () => {
    const appointments = ref([]);
    const currentAppointment = ref(null);
    const slots = ref([]);
    const currentSession = ref(null);
    const loading = ref(false);
    const booking = ref(false);

    async function fetchSlots(sessionId) {
        loading.value = true;
        try {
            const data = await appointmentService.sessionSlots(sessionId);
            slots.value = data.data;
            currentSession.value = data.session;
        } finally {
            loading.value = false;
        }
    }

    async function fetchAppointments() {
        loading.value = true;
        try {
            const data = await appointmentService.list();
            appointments.value = data.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchAppointment(id) {
        loading.value = true;
        try {
            const data = await appointmentService.get(id);
            currentAppointment.value = data.data;
            return data.data;
        } finally {
            loading.value = false;
        }
    }

    async function book(sessionId, startTime, reason = '') {
        booking.value = true;
        try {
            const data = await appointmentService.create({
                clinic_session_id: sessionId,
                start_time: startTime,
                reason,
            });
            appointments.value.unshift(data.data);
            return data;
        } finally {
            booking.value = false;
        }
    }

    async function cancel(id, reason) {
        await appointmentService.cancel(id, reason);
        appointments.value = appointments.value.filter(a => a.id !== id);
        if (currentAppointment.value?.id === id) {
            currentAppointment.value = null;
        }
    }

    async function reschedule(appointmentId, newSessionId, newStartTime, reason = '') {
        booking.value = true;
        try {
            const data = await appointmentService.reschedule(appointmentId, {
                new_session_id: newSessionId,
                new_start_time: newStartTime,
                cancellation_reason: reason,
            });
            const idx = appointments.value.findIndex(a => a.id === appointmentId);
            if (idx !== -1) appointments.value[idx] = data.data;
            if (currentAppointment.value?.id === appointmentId) {
                currentAppointment.value = data.data;
            }
            return data;
        } finally {
            booking.value = false;
        }
    }

    function clearSlots() {
        slots.value = [];
        currentSession.value = null;
    }

    function clearCurrent() {
        currentAppointment.value = null;
    }

    return {
        appointments, currentAppointment, slots, currentSession,
        loading, booking,
        fetchSlots, fetchAppointments, fetchAppointment,
        book, cancel, clearSlots, clearCurrent,
    };
});
