<template>
    <div class="py-8">
        <v-container>
            <v-card class="mb-6" elevation="1" border>
                <v-card-text class="pa-4">
                    <div class="mb-4">
                        <div class="text-caption text-medium-emphasis mb-2 font-weight-medium">SELECT DATE</div>
                        <div class="date-nav d-flex gap-2 flex-wrap">
                            <v-btn v-for="d in weekDates" :key="d.date" size="small" variant="outlined"
                                :color="selectedDate === d.date ? `primary` : `default`"
                                :class="{ 'date-btn-active': selectedDate === d.date }"
                                @click="selectDate(d.date)">
                                <div class="text-center">
                                    <div class="text-caption font-weight-bold" style="font-size: 10px;">{{ d.day }}</div>
                                    <div style="font-size: 15px;">{{ d.dayNum }}</div>
                                    <div class="text-caption" style="font-size: 9px;">{{ d.month }}</div>
                                </div>
                            </v-btn>
                        </div>
                    </div>
                    <v-divider class="mb-4"></v-divider>
                    <v-row dense align="center">
                        <v-col cols="12" md="4">
                            <v-text-field v-model="filters.q" label="Doctor name" prepend-inner-icon="mdi-magnify"
                                variant="outlined" density="compact" hide-details clearable
                                @update:model-value="debouncedSearch"></v-text-field>
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-select v-model="filters.specialty_id" :items="specialties" item-title="name" item-value="id"
                                label="Specialty" variant="outlined" density="compact" hide-details clearable
                                @update:model-value="doSearch"></v-select>
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-select v-model="filters.county_id" :items="counties" item-title="name" item-value="id"
                                label="County" variant="outlined" density="compact" hide-details clearable
                                @update:model-value="doSearch"></v-select>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-text-field v-model="filters.city" label="City / Town" variant="outlined" density="compact"
                                hide-details clearable @update:model-value="debouncedSearch"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-btn color="primary" size="large" block height="48" @click="doSearch" :loading="loading">Search</v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
            <div class="d-flex align-center justify-space-between mb-4" v-if="results.length || loading">
                <div>
                    <h2 class="text-h5 font-weight-bold">{{ meta.day }}</h2>
                    <p class="text-body-2 text-medium-emphasis" v-if="!loading">{{ meta.total }} doctor{{ meta.total === 1 ? '' : 's' }} with confirmed sessions</p>
                </div>
                <v-btn v-if="hasActiveFilters" variant="text" color="primary" size="small"
                    prepend-icon="mdi-filter-off" @click="clearFilters">Clear Filters</v-btn>
            </div>
            <v-row v-if="loading">
                <v-col cols="12" md="6" lg="4" v-for="n in 3" :key="n"><v-skeleton-loader type="card"></v-skeleton-loader></v-col>
            </v-row>
            <v-row v-else-if="results.length">
                <v-col cols="12" md="6" lg="4" v-for="item in results" :key="item.doctor.id">
                    <DoctorSearchCard :item="item" :meta="meta" :date="selectedDate" />
                </v-col>
            </v-row>
            <div class="d-flex justify-center mt-6" v-if="!loading && meta.last_page > 1">
                <v-pagination v-model="currentPage" :length="meta.last_page" :total-visible="7" color="primary" @update:model-value="goToPage"></v-pagination>
            </div>
            <v-card v-else-if="!loading" class="text-center pa-12" elevation="0" border>
                <v-icon icon="mdi-doctor" size="60" color="grey-lighten-1" class="mb-3"></v-icon>
                <h3 class="text-h6 font-weight-bold mb-2">No doctors available</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">We couldn't find a specialist practicing<br>in this location on the selected date.<br><br>Try another date, location, or specialty.</p>
                <!-- Phase 15: Smart suggestions from DiscoveryService -->
                <v-row v-if="suggestions.length" dense justify="center" class="mb-4">
                    <v-col cols="12" md="8">
                        <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">Suggestions</div>
                        <div class="d-flex flex-column gap-2">
                            <v-btn
                                v-for="(s, i) in suggestions"
                                :key="i"
                                variant="tonal"
                                color="primary"
                                size="small"
                                block
                                @click="applySuggestion(s)"
                            >
                                <v-icon left>{{ s.type === 'date' ? 'mdi-calendar-arrow-right' : 'mdi-filter-off' }}</v-icon>
                                {{ s.label }}
                                <span class="text-body-2 text-medium-emphasis ml-1">{{ s.message }}</span>
                            </v-btn>
                        </div>
                    </v-col>
                </v-row>
                <v-btn color="primary" variant="text" @click="clearFilters">Clear Filters</v-btn>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { doctorService, specialtyService, countyService } from '../../services/doctorService';
import DoctorSearchCard from '../../components/patient/DoctorSearchCard.vue';
const route = useRoute();
const filters = ref({ q: '', specialty_id: null, county_id: null, city: '' });
const specialties = ref([]);
const counties = ref([]);
const results = ref([]);
const meta = ref({ total: 0, per_page: 12, current_page: 1, last_page: 0, date: '', day: '' });
const suggestions = ref([]);
const loading = ref(false);
const selectedDate = ref(todayStr());
const currentPage = ref(1);
function todayStr() { return new Date().toISOString().slice(0, 10); }
const weekDates = computed(() => {
    const dates = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    for (let i = 0; i < 7; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        const dateStr = d.toISOString().slice(0, 10);
        dates.push({
            date: dateStr,
            day: d.toLocaleDateString('en-KE', { weekday: 'short' }),
            dayNum: d.getDate(),
            month: d.toLocaleDateString('en-KE', { month: 'short' }),
        });
    }
    return dates;
});
const hasActiveFilters = computed(() => !!filters.value.q || !!filters.value.specialty_id || !!filters.value.county_id || !!filters.value.city);
function selectDate(date) { selectedDate.value = date; currentPage.value = 1; doSearch(); }
let debounceTimer = null;
function debouncedSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => doSearch(), 400); }
async function doSearch() {
    loading.value = true;
    try {
        const params = { date: selectedDate.value, page: currentPage.value };
        if (filters.value.q) params.q = filters.value.q;
        if (filters.value.specialty_id) params.specialty_id = filters.value.specialty_id;
        if (filters.value.county_id) params.county_id = filters.value.county_id;
        if (filters.value.city) params.city = filters.value.city;
        const resp = await doctorService.search(params);
        results.value = resp.data.data;
        meta.value = resp.data.meta;
        suggestions.value = resp.data.suggestions || [];
    } catch (e) {
        console.error('Search failed:', e);
        results.value = [];
        suggestions.value = [];
    } finally {
        loading.value = false;
    }
}
function applySuggestion(s) {
    if (s.type === 'date' && s.date) {
        selectedDate.value = s.date;
        currentPage.value = 1;
        doSearch();
    } else if (s.type === 'clear_filter') {
        clearFilters();
    }
}
function goToPage(page) { currentPage.value = page; doSearch(); }
function clearFilters() { filters.value = { q: '', specialty_id: null, county_id: null, city: '' }; currentPage.value = 1; doSearch(); }
onMounted(async () => {
    const [specResp, countyResp] = await Promise.all([specialtyService.list(), countyService.list()]);
    specialties.value = specResp.data.data;
    counties.value = countyResp.data.data;
    if (route.query.name) filters.value.q = route.query.name;
    if (route.query.specialty_id) filters.value.specialty_id = parseInt(route.query.specialty_id);
    if (route.query.date) selectedDate.value = route.query.date;
    if (route.query.county_id) filters.value.county_id = parseInt(route.query.county_id);
    if (route.query.city) filters.value.city = route.query.city;
    await doSearch();
});
</script>