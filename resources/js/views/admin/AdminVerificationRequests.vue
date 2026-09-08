<template>
    <div>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
        <h1 class="text-h5 font-weight-bold mb-1">Verification Queue</h1>
        <p class="text-body-2 text-medium-emphasis mb-4">Evidence-driven review. Every decision records the reviewer, time and reason.</p>
        <v-tabs v-model="tab" color="primary" class="mb-4">
            <v-tab value="doctor">Doctor<v-chip v-if="doctorPending" size="x-small" color="warning" class="ml-2">{{ doctorPending }}</v-chip></v-tab>
            <v-tab value="facility">Facility<v-chip v-if="facilityPending" size="x-small" color="warning" class="ml-2">{{ facilityPending }}</v-chip></v-tab>
        </v-tabs>
        <v-tabs-window v-model="tab">
            <v-tabs-window-item value="doctor">
                <v-card variant="outlined">
                    <v-data-table :headers="headers" :items="rows" :items-per-page="20" density="comfortable">
                        <template #item.type="{ item }"><v-chip size="x-small" variant="tonal">{{ item.type }}</v-chip></template>
                        <template #item.applicant="{ item }"><span class="text-body-2">{{ item.applicant ? item.applicant.name : "—" }}</span></template>
                        <template #item.registry_number="{ item }"><code class="text-body-2">{{ item.registry_number || "—" }}</code></template>
                        <template #item.status="{ item }">
                            <v-chip size="x-small" :color="statusColor(item.status)" variant="flat">{{ item.status_label }}</v-chip>
                        </template>
                        <template #item.submitted_at="{ item }"><span class="text-caption">{{ formatDate(item.submitted_at) }}</span></template>
                        <template #item.actions="{ item }">
                            <v-btn v-if="item.status === 'pending' || item.status === 'more_info'" size="x-small" color="primary" variant="tonal" @click="openReview(item)">Review</v-btn>
                        </template>
                    </v-data-table>
                </v-card>
            </v-tabs-window-item>
            <v-tabs-window-item value="facility">
                <v-card variant="outlined">
                    <v-data-table :headers="headers" :items="rows" :items-per-page="20" density="comfortable">
                        <template #item.type="{ item }"><v-chip size="x-small" variant="tonal">{{ item.type }}</v-chip></template>
                        <template #item.applicant="{ item }"><span class="text-body-2">{{ item.applicant ? item.applicant.name : "—" }}</span></template>
                        <template #item.registry_number="{ item }"><code class="text-body-2">{{ item.registry_number || "—" }}</code></template>
                        <template #item.status="{ item }">
                            <v-chip size="x-small" :color="statusColor(item.status)" variant="flat">{{ item.status_label }}</v-chip>
                        </template>
                        <template #item.submitted_at="{ item }"><span class="text-caption">{{ formatDate(item.submitted_at) }}</span></template>
                        <template #item.actions="{ item }">
                            <v-btn v-if="item.status === 'pending' || item.status === 'more_info'" size="x-small" color="primary" variant="tonal" @click="openReview(item)">Review</v-btn>
                        </template>
                    </v-data-table>
                </v-card>
            </v-tabs-window-item>
        </v-tabs-window>

        <v-dialog v-model="review.show" max-width="560" persistent>
            <v-card>
                <v-card-title class="text-h6">Review #{{ review.item && review.item.id }}</v-card-title>
                <v-card-text v-if="review.item">
                    <div class="mb-3">
                        <p class="text-body-2"><strong>Applicant:</strong> {{ review.item.applicant ? review.item.applicant.name : "—" }}</p>
                        <p class="text-body-2"><strong>Registry:</strong> {{ review.item.registry_number || "—" }}</p>
                        <p class="text-body-2"><strong>Source:</strong> {{ review.item.verification_source || "—" }}</p>
                        <p class="text-body-2"><strong>Notes:</strong> {{ review.item.reviewer_notes || "—" }}</p>
                        <div v-if="review.item.evidence && review.item.evidence.length" class="mt-2">
                            <div class="text-caption text-medium-emphasis mb-1">Evidence</div>
                            <v-chip v-for="(e, i) in review.item.evidence" :key="i" size="x-small" variant="tonal" class="mr-1">{{ e }}</v-chip>
                        </div>
                    </div>
                    <v-form ref="reviewForm">
                        <v-radio-group v-model="review.decision" density="compact" class="mb-2">
                            <v-radio label="Approve" value="approve" color="success"></v-radio>
                            <v-radio label="Reject" value="reject" color="error"></v-radio>
                            <v-radio label="Request more info" value="request_more_info" color="warning"></v-radio>
                            <v-radio label="Suspend" value="suspend" color="deep-orange"></v-radio>
                        </v-radio-group>
                        <v-text-field v-if="['reject', 'request_more_info', 'suspend'].includes(review.decision)" v-model="review.reason" label="Reason *" variant="outlined" density="compact" :rules="[v => !!v || 'A reason is required']" counter="255" maxlength="255"></v-text-field>
                        <v-textarea v-model="review.notes" label="Notes to applicant" variant="outlined" density="compact" rows="2"></v-textarea>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="review.show = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="review.loading" @click="onSubmitReview">Submit</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { useRoute } from "vue-router";
import { adminService } from "../../services/adminService";

const route = useRoute();
const tab = ref(route.query.type === "facility" ? "facility" : "doctor");

watch(() => route.query.type, (t) => {
    tab.value = t === "facility" ? "facility" : "doctor";
});
const loading = ref(false);
const error = ref(null);
const rows = ref([]);
const reviewForm = ref(null);
const review = ref({ show: false, item: null, id: null, decision: "approve", reason: "", notes: "", loading: false });

const doctorPending = computed(() => rows.value.filter(r => r.type === "doctor" && ["pending", "more_info"].includes(r.status)).length);
const facilityPending = computed(() => rows.value.filter(r => r.type === "facility" && ["pending", "more_info"].includes(r.status)).length);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await adminService.getVerificationRequests({ type: tab.value === "doctor" ? "doctor" : "facility", per_page: 50 });
        rows.value = res.data;
    } catch (e) {
        error.value = "Failed to load the verification queue.";
    } finally {
        loading.value = false;
    }
}

watch(tab, load);

function openReview(item) {
    review.value = { show: true, item, id: item.id, decision: "approve", reason: "", notes: "", loading: false };
}

async function onSubmitReview() {
    if (["reject", "request_more_info", "suspend"].includes(review.value.decision)) {
        const { valid } = await reviewForm.value.validate();
        if (!valid) return;
    }
    review.value.loading = true;
    try {
        await adminService.reviewVerificationRequest(review.value.id, {
            decision: review.value.decision,
            reason: review.value.reason || undefined,
            notes: review.value.notes || undefined,
        });
        review.value.show = false;
        await load();
    } catch (e) {
        error.value = e.response && e.response.data && e.response.data.message ? e.response.data.message : "Review failed.";
    } finally {
        review.value.loading = false;
    }
}

function statusColor(s) {
    return { pending: "warning", more_info: "warning", approved: "success", rejected: "error", suspended: "deep-orange" }[s] || "grey";
}

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString() : "—";
}

const headers = [
    { title: "Request", key: "id", sortable: true },
    { title: "Applicant", key: "applicant", sortable: false },
    { title: "Registry", key: "registry_number", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Submitted", key: "submitted_at", sortable: true },
    { title: "", key: "actions", sortable: false, align: "end" },
];

onMounted(load);
</script>