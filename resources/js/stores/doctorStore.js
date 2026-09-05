import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { doctorService } from '../services/doctorService';

export const useDoctorStore = defineStore('doctor', () => {
    const doctors = ref([]);
    const currentDoctor = ref(null);
    const loading = ref(false);
    const meta = ref({ total: 0, date: null });

    async function search(params = {}) {
        loading.value = true;
        try {
            const response = await doctorService.search(params);
            doctors.value = response.data.data;
            meta.value = response.data.meta;
        } finally {
            loading.value = false;
        }
    }

    async function find(slug) {
        loading.value = true;
        try {
            const response = await doctorService.find(slug);
            currentDoctor.value = response.data.data;
        } finally {
            loading.value = false;
        }
    }

    function clearCurrent() {
        currentDoctor.value = null;
    }

    return { doctors, currentDoctor, loading, meta, search, find, clearCurrent };
});
