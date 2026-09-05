<template>
    <div>
        <section class="py-16 hero-section">
            <v-container>
                <v-row justify="center">
                    <v-col cols="12" md="10" lg="8">
                        <div class="text-center mb-8">
                            <h1 class="text-h3 text-md-h2 font-weight-bold mb-4">
                                Find your doctor<br><span class="text-primary">where they practice today</span>
                            </h1>
                            <p class="text-h6 text-medium-emphasis mb-2">The marketplace for confirmed clinic sessions across Kenya.</p>
                            <p class="text-body-2 text-medium-emphasis"><v-icon icon="mdi-shield-check" size="14" color="primary" class="mr-1" />Real availability from real doctors. No guesswork.</p>
                        </div>
                        <v-card elevation="4" class="pa-2 search-card">
                            <v-card-text class="pa-4">
                                <v-row dense align="center">
                                    <v-col cols="12" md="6">
                                        <v-text-field v-model="searchName" label="Where is your doctor?" placeholder="e.g. Dr. Wanyonyi" prepend-inner-icon="mdi-magnify" variant="outlined" density="comfortable" hide-details @keyup.enter="goToDoctorSearch"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="4"><v-text-field v-model="searchDate" label="Date" type="date" variant="outlined" density="comfortable" hide-details></v-text-field></v-col>
                                    <v-col cols="12" md="2"><v-btn color="primary" size="large" block height="48" @click="goToDoctorSearch" prepend-icon="mdi-arrow-right">Find</v-btn></v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </section>
        <section class="py-12 bg-white">
            <v-container>
                <v-card class="specialist-card" elevation="2" border>
                    <v-card-text class="pa-6 pa-md-8">
                        <div class="d-flex align-center gap-2 mb-4"><v-icon icon="mdi-stethoscope" color="primary" size="28" /><h2 class="text-h5 font-weight-bold">Find a specialist</h2></div>
                        <p class="text-body-2 text-medium-emphasis mb-6">Search by specialty, location, and date — only doctors with confirmed sessions today will appear.</p>
                        <v-row dense align="end">
                            <v-col cols="12" md="3"><v-select v-model="filterSpecialty" :items="specialties" item-title="name" item-value="id" label="Specialty" variant="outlined" density="comfortable" prepend-inner-icon="mdi-medical-bag" hide-details clearable></v-select></v-col>
                            <v-col cols="12" md="3"><v-text-field v-model="filterCity" label="Location" placeholder="e.g. Busia, Nairobi" variant="outlined" density="comfortable" prepend-inner-icon="mdi-map-marker" hide-details clearable></v-text-field></v-col>
                            <v-col cols="12" md="3"><v-text-field v-model="filterDate" label="Date" type="date" variant="outlined" density="comfortable" prepend-inner-icon="mdi-calendar" hide-details></v-text-field></v-col>
                            <v-col cols="12" md="3"><v-btn color="primary" size="large" block height="48" @click="goToSpecialistSearch" prepend-icon="mdi-magnify">Find Available Doctors</v-btn></v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-container>
        </section>
        <section class="py-12 bg-grey-lighten-5">
            <v-container>
                <div class="text-center mb-8"><h2 class="text-h4 font-weight-bold mb-2">Available today</h2><p class="text-body-1 text-medium-emphasis">Confirmed clinic sessions, ready to book</p></div>
                <v-row v-if="loading"><v-col cols="12" md="6" lg="4" v-for="n in 3" :key="n"><v-skeleton-loader type="card"></v-skeleton-loader></v-col></v-row>
                <v-row v-else-if="results.length"><v-col cols="12" md="6" lg="4" v-for="item in results.slice(0, 6)" :key="item.doctor.id"><DoctorSearchCard :item="item" :meta="meta" :date="todayStr()" /></v-col></v-row>
                <v-card v-else class="text-center pa-12" elevation="0" border>
                    <v-icon icon="mdi-calendar-search" size="60" color="grey-lighten-1" class="mb-3"></v-icon>
                    <h3 class="text-h6 font-weight-bold mb-2">No clinics available today</h3>
                    <p class="text-body-2 text-medium-emphasis mb-4">No confirmed sessions scheduled for today. Try another date.</p>
                    <v-btn color="primary" variant="tonal" to="/search" append-icon="mdi-arrow-right">Browse all dates</v-btn>
                </v-card>
                <div v-if="results.length" class="text-center mt-8"><v-btn color="primary" variant="tonal" to="/search" append-icon="mdi-arrow-right">View all doctors</v-btn></div>
            </v-container>
        </section>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { doctorService, specialtyService } from '../../services/doctorService';
import DoctorSearchCard from '../../components/patient/DoctorSearchCard.vue';
const router = useRouter();
const searchName = ref('');
const searchDate = ref('');
const filterSpecialty = ref(null);
const filterCity = ref('');
const filterDate = ref('');
const specialties = ref([]);
const results = ref([]);
const meta = ref({ total: 0, day: '' });
const loading = ref(false);
function todayStr() { return new Date().toISOString().slice(0, 10); }
function goToDoctorSearch() {
    const params = {};
    if (searchName.value) params.name = searchName.value;
    if (searchDate.value) params.date = searchDate.value;
    router.push({ name: 'doctor-search', query: params });
}
function goToSpecialistSearch() {
    const params = {};
    if (filterSpecialty.value) params.specialty_id = filterSpecialty.value;
    if (filterCity.value) params.city = filterCity.value;
    params.date = filterDate.value || todayStr();
    router.push({ name: 'doctor-search', query: params });
}
async function loadAvailableToday() {
    loading.value = true;
    try {
        const resp = await doctorService.search({ date: todayStr(), per_page: 6 });
        results.value = resp.data.data;
        meta.value = resp.data.meta;
    } catch (e) { console.error(e); results.value = []; }
    finally { loading.value = false; }
}
onMounted(async () => {
    try { const resp = await specialtyService.list(); specialties.value = resp.data.data; }
    catch (e) { console.error(e); }
    await loadAvailableToday();
});
</script>
<style scoped>
.hero-section { background: linear-gradient(135deg, rgba(15,118,110,0.04) 0%, rgba(37,99,235,0.04) 100%); }
.search-card { border: 1px solid rgba(0,0,0,0.06); }
.specialist-card { background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%); }
</style>