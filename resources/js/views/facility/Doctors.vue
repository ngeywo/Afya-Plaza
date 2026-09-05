<template>
    <div>
        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-progress-linear v-if="store.loading && store.doctors.length === 0" indeterminate></v-progress-linear>
        <h1 class="text-h5 font-weight-bold mb-2">My Doctors</h1>
        <p class="text-body-2 text-medium-emphasis mb-4">Doctors with an active practice relationship at this facility.</p>

        <v-card v-if="!store.loading && store.doctors.length === 0" variant="outlined" class="pa-8 text-center">
            <v-icon icon="mdi-doctor" size="64" color="medium-emphasis" class="mb-3"></v-icon>
            <h3 class="text-h6 font-weight-bold mb-2">No Active Doctors</h3>
            <p class="text-body-2 text-medium-emphasis">No doctors currently have an active practice relationship at this facility.</p>
        </v-card>

        <v-row>
            <v-col v-for="d in store.doctors" :key="d.id" cols="12" md="6">
                <v-card variant="outlined" class="pa-4 h-100">
                    <div class="d-flex align-center mb-3">
                        <v-avatar size="48" color="primary" class="mr-3">
                            <span class="text-white font-weight-bold">{{ initials(d.name) }}</span>
                        </v-avatar>
                        <div class="flex-grow-1">
                            <div class="text-h6 font-weight-bold">{{ d.name }}</div>
                            <div class="text-caption text-medium-emphasis">
                                <v-icon v-if="d.is_verified" icon="mdi-check-decagram" color="success" size="14" class="mr-1"></v-icon>
                                {{ d.specialties?.[0]?.name || 'General' }}
                            </div>
                        </div>
                        <v-chip color="success" size="small" variant="tonal">Active</v-chip>
                    </div>
                    <div class="mb-2">
                        <v-chip v-for="s in d.specialties" :key="s.id" size="x-small" variant="outlined" class="mr-1 mb-1">{{ s.name }}</v-chip>
                    </div>
                    <v-divider class="my-3"></v-divider>
                    <div class="text-caption text-medium-emphasis mb-1">Next clinic</div>
                    <div v-if="d.next_clinic" class="d-flex align-center">
                        <v-icon icon="mdi-calendar-clock" size="16" class="mr-2" color="primary"></v-icon>
                        <div class="flex-grow-1">
                            <div class="text-body-2 font-weight-medium">{{ d.next_clinic.day }} · {{ d.next_clinic.start_time }} – {{ d.next_clinic.end_time }}</div>
                        </div>
                        <v-chip :color="d.next_clinic.is_confirmed ? 'success' : 'warning'" size="x-small" variant="tonal">{{ d.next_clinic.is_confirmed ? 'Confirmed' : 'Pending' }}</v-chip>
                    </div>
                    <div v-else class="text-body-2 text-medium-emphasis">No upcoming clinic</div>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup>
import { onMounted } from "vue";
import { useFacilityWorkspaceStore } from "../../stores/facilityWorkspaceStore";
const store = useFacilityWorkspaceStore();
onMounted(() => { store.fetchDoctors(); });
function initials(name) { return name ? name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase() : "?"; }
</script>
