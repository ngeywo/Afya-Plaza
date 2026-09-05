<template>
    <div>
        <section class="py-16">
            <v-container>
                <v-row justify="center">
                    <v-col cols="12" md="10" lg="8">
                        <div class="text-center mb-8">
                            <h1 class="text-h3 text-md-h2 font-weight-bold mb-4">
                                Where is your doctor <span class="text-primary">today?</span>
                            </h1>
                            <p class="text-h6 text-medium-emphasis">
                                Find where your doctor is practicing and book instantly.
                            </p>
                        </div>
                        <v-card elevation="4" class="pa-2">
                            <v-card-text class="pa-4">
                                <v-row dense align="center">
                                    <v-col cols="12" md="5">
                                        <v-text-field v-model="searchName" label="Doctor name"
                                            placeholder="e.g. Dr. Wanyonyi" prepend-inner-icon="mdi-magnify"
                                            variant="outlined" density="comfortable" hide-details
                                            @keyup.enter="doSearch"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field v-model="searchDate" label="Date" type="date"
                                            variant="outlined" density="comfortable" hide-details></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="3">
                                        <v-btn color="primary" size="large" block height="48"
                                            @click="doSearch" :loading="loading">Search</v-btn>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </section>

        <section class="py-16 bg-white">
            <v-container>
                <div class="text-center mb-12">
                    <h2 class="text-h4 font-weight-bold mb-3">Available Today</h2>
                    <p class="text-body-1 text-medium-emphasis">Doctors with confirmed sessions today</p>
                </div>
                <v-row v-if="loading">
                    <v-col cols="12" md="4" v-for="n in 3" :key="n">
                        <v-skeleton-loader type="card"></v-skeleton-loader>
                    </v-col>
                </v-row>
                <v-row v-else-if="doctors.length">
                    <v-col cols="12" md="4" v-for="doctor in doctors" :key="doctor.id">
                        <DoctorCard :doctor="doctor" />
                    </v-col>
                </v-row>
                <v-alert v-else type="info" variant="tonal">No doctors with confirmed sessions today.</v-alert>
            </v-container>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useDoctorStore } from '../../stores/doctorStore';
import DoctorCard from '../../components/patient/DoctorCard.vue';

const router = useRouter();
const store = useDoctorStore();
const searchName = ref('');
const searchDate = ref('');
const loading = computed(() => store.loading);
const doctors = computed(() => store.doctors);

function doSearch() {
    const params = {};
    if (searchName.value) params.name = searchName.value;
    if (searchDate.value) params.date = searchDate.value;
    router.push({ name: 'doctor-search', query: params });
}

onMounted(() => store.search({ available_only: true }));
</script>
