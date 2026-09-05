import { defineStore } from 'pinia';
import { ref } from 'vue';
import { appointmentService } from '../services/appointmentService';

export const useAppointmentStore = defineStore('appointment', () => {
    const appointments = ref([]);
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

    async function book(sessionId, startTime, reason = '') {
        booking.value = true;
        try {
            const data = await appointmentService.create({ clinic_session_id: sessionId, start_time: startTime, reason });
            return data;
        } finally {
            booking.value = false;
        }
    }

    async function cancel(id) {
        await appointmentService.cancel(id);
        appointments.value = appointments.value.filter(a => a.id !== id);
    }

    function clearSlots() {
        slots.value = [];
        currentSession.value = null;
    }

    return { appointments, slots, currentSession, loading, booking, fetchSlots, fetchAppointments, book, cancel, clearSlots };
});
