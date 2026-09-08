<template>
    <div>
        <AppPageHeader
            title="My Schedule"
            subtitle="Clinic sessions are scheduled by your hospital — review and confirm them here."
            icon="mdi-calendar-clock"
        />

        <v-alert v-if="store.error" type="error" variant="tonal" class="mb-4" closable @click:close="store.error = null">{{ store.error }}</v-alert>
        <v-alert v-if="store.success" type="success" variant="tonal" class="mb-4" closable @click:close="store.success = null">{{ store.success }}</v-alert>
        <v-progress-linear v-if="store.loading" indeterminate></v-progress-linear>

        <template v-if="store.schedule">
            <!-- Recurring schedule per facility -->
            <div v-for="group in store.schedule.recurring" :key="group.facility.id" class="mb-6">
                <h2 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center">
                    <v-icon icon="mdi-hospital-building" color="primary" class="mr-2" size="20"></v-icon>
                    {{ group.facility.name }}
                    <span class="text-body-2 text-medium-emphasis font-weight-regular ml-2">— {{ group.facility.city }}</span>
                </h2>
                <v-card variant="outlined" class="mb-3">
                    <v-list density="compact">
                        <v-list-subheader>Recurring Schedule</v-list-subheader>
                        <template v-if="group.schedules && group.schedules.length > 0">
                            <v-list-item v-for="s in group.schedules" :key="s.id">
                                <template #prepend>
                                    <div class="text-right font-weight-bold mr-3" style="min-width: 80px;">{{ s.day_name }}</div>
                                </template>
                                <v-list-item-title>{{ s.start_time }} – {{ s.end_time }}</v-list-item-title>
                                <v-list-item-subtitle>{{ s.slot_duration_minutes }} min slots · max {{ s.max_appointments ?? 'unlimited' }}</v-list-item-subtitle>
                            </v-list-item>
                        </template>
                        <v-list-item v-else>
                            <v-list-item-title class="text-medium-emphasis">No recurring schedule defined</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-card>
                <!-- Exceptions -->
                <div v-if="group.exceptions && group.exceptions.length > 0">
                    <h3 class="text-subtitle-2 font-weight-bold mb-2">Upcoming Exceptions</h3>
                    <v-card variant="outlined">
                        <v-list density="compact">
                            <v-list-item v-for="e in group.exceptions" :key="e.id">
                                <template #prepend>
                                    <v-icon :icon="exceptionIcon(e.type)" :color="exceptionColor(e.type)" class="mr-2"></v-icon>
                                </template>
                                <v-list-item-title class="font-weight-medium">{{ e.day }}</v-list-item-title>
                                <v-list-item-subtitle>
                                    <v-chip :color="exceptionColor(e.type)" size="x-small" variant="tonal" class="mr-1">{{ e.type }}</v-chip>
                                    {{ e.reason || '' }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card>
                </div>
            </div>

            <!-- Upcoming sessions -->
            <div v-if="store.schedule.upcoming_sessions && store.schedule.upcoming_sessions.length > 0">
                <h2 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center">
                    <v-icon icon="mdi-calendar" color="primary" class="mr-2" size="20"></v-icon>Upcoming Sessions
                </h2>
                <v-card variant="outlined">
                    <v-list density="compact">
                        <v-list-item v-for="s in store.schedule.upcoming_sessions" :key="s.id">
                            <template #prepend>
                                <div class="text-right mr-3" style="min-width: 80px;">
                                    <div class="text-caption text-medium-emphasis">{{ s.day }}</div>
                                    <div class="text-body-2 font-weight-bold">{{ s.start_time }}</div>
                                </div>
                            </template>
                            <v-list-item-title class="font-weight-medium">{{ s.facility.name }}</v-list-item-title>
                            <v-list-item-subtitle>{{ s.end_time }} · {{ s.facility.city }}</v-list-item-subtitle>
                            <template #append>
                                <div class="d-flex align-center ga-2">
                                    <v-chip :color="s.is_confirmed ? 'success' : 'warning'" size="x-small" variant="tonal">{{ s.is_confirmed ? 'Confirmed' : 'Pending' }}</v-chip>
                                    <v-btn v-if="!s.is_confirmed && s.status !== 'cancelled'" color="primary" size="small" variant="tonal"
                                        :loading="confirming === s.id" @click="confirmSession(s)">
                                        Confirm
                                    </v-btn>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </div>

            <v-alert v-else variant="tonal" type="info" density="comfortable" class="mt-2">
                <template #prepend><v-icon icon="mdi-hospital-box-outline"></v-icon></template>
                No upcoming sessions. Your hospital schedules clinic sessions for you — they will appear here once scheduled.
            </v-alert>
        </template>

        <v-snackbar v-model="snackbar" :color="snackColor" variant="tonal">{{ snackText }}</v-snackbar>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useDoctorWorkspaceStore } from "../../stores/doctorWorkspaceStore";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import { useNotifier } from "../../composables/useNotifier";

const store = useDoctorWorkspaceStore();
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();

const confirming = ref(null);

onMounted(() => { store.fetchSchedule(); });

async function confirmSession(s) {
    confirming.value = s.id;
    try {
        await store.confirmSession(s.id);
        notify('Session confirmed.');
        await store.fetchSchedule();
    } catch (e) {
        notifyError(e.response?.data?.error || 'Failed to confirm session');
    } finally {
        confirming.value = null;
    }
}

function exceptionIcon(type) {
    return { cancelled: 'mdi-close-circle', rescheduled: 'mdi-calendar-refresh', additional: 'mdi-plus-circle', location_changed: 'mdi-map-marker' }[type] || 'mdi-information';
}
function exceptionColor(type) {
    return { cancelled: 'error', rescheduled: 'info', additional: 'success', location_changed: 'primary' }[type] || 'default';
}
</script>