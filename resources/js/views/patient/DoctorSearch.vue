<template>
    <div class="py-8">
        <v-container>
            <!-- Search Filters — Section 3: search with date as first-class filter -->
            <v-card class="mb-6" elevation="2">
                <v-card-text class="pa-4">
                    <v-row dense align="center">
                        <v-col cols="12" md="4">
                            <v-text-field v-model="filters.name" label="Doctor name"
                                prepend-inner-icon="mdi-magnify" variant="outlined"
                                density="comfortable" hide-details clearable
                                @update:model-value="debouncedSearch"></v-text-field>
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-select v-model="filters.specialty_id" :items="specialties"
                                item-title="name" item-value="id" label="Specialty"
                                variant="outlined" density="comfortable" hide-details clearable></v-select>
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-text-field v-model="filters.date" label="Date" type="date"
                                variant="outlined" density="comfortable" hide-details></v-text-field>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-btn-toggle v-model="filters.available_only" color="primary" variant="outlined" density="comfortable">
                                <v-btn :value="true" size="small">Available</v-btn>
                            </v-btn-toggle>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-btn color="primary" size="large" block @click="search" :loading="loading">
                                <v-icon icon="mdi-magnify" class="mr-1"></v-icon>
                                Search
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- Results -->
            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <h1 class="text-h5 font-weight-bold">Search Results</h1>
                    <p class="text-body-2 text-medium-emphasis">
                        {{ store.doctors.length }} doctors found
                        <span v-if="store.meta.date"> for {{ store.meta.date }}</span>
                    </p>
                </div>
            </div>

            <v-row v-if="loading">
                <v-col cols="12" md="4" v-for="n in 3" :key="n">
                    <v-skeleton-loader type="card"></v-skeleton-loader>
                </v-col>
            </v-row>

            <v-row v-else-if="store.doctors.length">
                <v-col cols="12" md="4" v-for="doctor in store.doctors" :key="doctor.id">
                    <DoctorCard :doctor="doctor" />
                </v-col>
            </v-row>

            <v-card v-else class="text-center pa-16" elevation="0" border>
                <v-icon icon="mdi-doctor" size="80" color="grey-lighten-1" class="mb-4"></v-icon>
                <h3 class="text-h6 font-weight-bold mb-2">No doctors found</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">
                    Try adjusting your search filters or search for a different name.
                </p>
                <v-btn color="primary" variant="tonal" @click="clearFilters">Clear Filters</v-btn>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useDoctorStore } from '../../stores/doctorStore';
import { specialtyService } from '../../services/doctorService';
import DoctorCard from '../../components/patient/DoctorCard.vue';

const store = useDoctorStore();
const route = useRoute();

const filters = ref({ name: '', specialty_id: null, date: '', available_only: true });
const specialties = ref([]);
const loading = ref(false);

let debounceTimer = null;
function debouncedSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => search(), 500);
}

async function search() {
    loading.value = true;
    try {
        const params = {};
        if (filters.value.name) params.name = filters.value.name;
        if (filters.value.specialty_id) params.specialty_id = filters.value.specialty_id;
        if (filters.value.date) params.date = filters.value.date;
        if (filters.value.available_only) params.available_only = true;
        await store.search(params);
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    filters.value = { name: '', specialty_id: null, date: '', available_only: false };
    search();
}

onMounted(async () => {
    const resp = await specialtyService.list();
    specialties.value = resp.data.data;

    if (route.query.name) filters.value.name = route.query.name;
    if (route.query.specialty_id) filters.value.specialty_id = parseInt(route.query.specialty_id);
    if (route.query.date) filters.value.date = route.query.date;
    if (route.query.available_only === 'false') filters.value.available_only = false;

    await search();
});

watch(() => filters.value.specialty_id, search);
watch(() => filters.value.date, search);
</script>
