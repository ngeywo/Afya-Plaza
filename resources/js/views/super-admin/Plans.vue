<template>
    <div class="plans-page">
        <AppPageHeader title="Plans" subtitle="Facility subscription plans and pricing" icon="mdi-tag-multiple">
            <template #actions><v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Plan</v-btn></template>
        </AppPageHeader>
        <v-card>
            <v-card-text>
                <v-data-table :headers="headers" :items="plans" :loading="loading" class="elevation-0">
                    <template #item.name="{ item }">
                        <div class="font-weight-medium">{{ item.name }}</div>
                        <div class="text-caption text-medium-emphasis">{{ item.slug }}</div>
                    </template>
                    <template #item.price="{ item }">
                        <div>{{ formatMoney(item) }}</div>
                        <div class="text-caption text-medium-emphasis">{{ item.trial_days ? `${item.trial_days}d trial` : "No trial" }}</div>
                    </template>
                    <template #item.limits="{ item }">
                        <div class="text-caption">{{ limitsLabel(item) }}</div>
                    </template>
                    <template #item.version="{ item }">
                        <span>v{{ item.version }} <span class="text-caption text-medium-emphasis" v-if="item.version_count > 1">({{ item.version_count }})</span></span>
                    </template>
                    <template #item.status="{ item }">
                        <v-chip v-if="item.is_default" size="small" color="secondary" variant="tonal">Default</v-chip>
                        <v-chip :color="item.is_active ? 'success' : 'grey'" size="small" variant="flat">{{ item.is_active ? "Active" : "Inactive" }}</v-chip>
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn icon="mdi-history" size="small" variant="text" title="Versions" @click="openVersions(item)"></v-btn>
                        <v-btn v-if="item.is_active" icon="mdi-pause-circle" size="small" variant="text" color="warning" title="Deactivate" @click="openStatus(item, false)"></v-btn>
                        <v-btn v-else icon="mdi-play-circle" size="small" variant="text" color="success" title="Activate" @click="openStatus(item, true)"></v-btn>
                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)"></v-btn>
                    </template>
                    <template #no-data>
                        <div class="text-center py-8">
                            <v-icon icon="mdi-tag-off-outline" size="40" color="grey-lighten-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis mt-2">No plans defined yet.</div>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <v-dialog v-model="formDialog" max-width="640" persistent>
            <v-card><v-card-title>{{ isEditing ? "Edit Plan" : "Create Plan" }}</v-card-title>
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6"><v-text-field v-model="form.name" label="Name *"></v-text-field></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="form.slug" label="Slug *" hint="e.g. clinic-basic"></v-text-field></v-col>
                    </v-row>
                    <v-textarea v-model="form.description" label="Description" rows="2" class="mb-2"></v-textarea>
                    <v-row>
                        <v-col cols="12" md="4"><v-text-field v-model="form.currency" label="Currency" hint="KES"></v-text-field></v-col>
                        <v-col cols="12" md="4"><v-text-field v-model="form.monthly_price" label="Monthly Price" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="4"><v-text-field v-model="form.annual_price" label="Annual Price" type="number"></v-text-field></v-col>
                    </v-row>
                    <v-row>
                        <v-col cols="12" md="4"><v-text-field v-model="form.trial_days" label="Trial Days" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="4"><v-select v-model="form.support_level" :items="['standard', 'priority', 'dedicated']" label="Support Level"></v-select></v-col>
                        <v-col cols="12" md="4"><v-switch v-model="form.is_default" label="Default Plan" color="secondary"></v-switch></v-col>
                    </v-row>
                    <v-divider class="my-2"></v-divider>
                    <p class="text-caption text-medium-emphasis mb-2">Limits (leave blank for unlimited)</p>
                    <v-row>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_doctors" label="Max Doctors" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_staff" label="Max Staff" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_locations" label="Max Locations" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_monthly_bookings" label="Max Bookings / mo" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_sms" label="Max SMS / mo" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_storage_mb" label="Storage (MB)" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.max_admin_users" label="Max Admin Users" type="number"></v-text-field></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.sort_order" label="Sort Order" type="number"></v-text-field></v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="formDialog = false">Cancel</v-btn><v-btn color="primary" @click="savePlan">{{ isEditing ? "Update" : "Create" }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="statusDialog" max-width="420">
            <v-card><v-card-title>{{ statusTarget?.is_active ? "Deactivate Plan" : "Activate Plan" }}</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-2">{{ statusTarget?.is_active ? `Deactivate "${statusTarget?.name}"?` : `Activate "${statusTarget?.name}"?` }}</p>
                    <v-textarea v-model="statusReason" label="Reason (optional)" rows="2"></v-textarea>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="statusDialog = false">Cancel</v-btn><v-btn :color="statusTarget?.is_active ? 'warning' : 'success'" @click="saveStatus">{{ statusTarget?.is_active ? "Deactivate" : "Activate" }}</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="versionsDialog" max-width="640">
            <v-card><v-card-title>Plan Versions — {{ versionsTarget?.name }}</v-card-title>
                <v-card-text>
                    <v-list v-if="versions.length">
                        <v-list-item v-for="v in versions" :key="v.id" border="b-thin" class="py-1">
                            <template #prepend><v-chip size="small" color="primary" variant="tonal">v{{ v.version }}</v-chip></template>
                            <v-list-item-subtitle class="text-caption">{{ v.created_by || "System" }} · {{ formatDate(v.created_at) }}</v-list-item-subtitle>
                        </v-list-item>
                    </v-list>
                    <div v-else class="text-center text-body-2 text-medium-emphasis pa-4">No versions recorded.</div>
                </v-card-text>
                <v-card-actions><v-spacer></v-spacer><v-btn @click="versionsDialog = false">Close</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="snackbar" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
    </div>
</template>
<script setup>
import { ref, onMounted } from "vue";
import { adminService } from "../../services/adminService";
import AppPageHeader from "../../components/ui/AppPageHeader.vue";
import { useNotifier } from "../../composables/useNotifier";
const { snackbar, snackText, snackColor, notify, notifyError } = useNotifier();
const plans = ref([]);
const loading = ref(false);
const formDialog = ref(false);
const statusDialog = ref(false);
const versionsDialog = ref(false);
const isEditing = ref(false);
const selectedPlan = ref(null);
const statusTarget = ref(null);
const versionsTarget = ref(null);
const statusReason = ref("");
const versions = ref([]);
const headers = [
    { title: "Plan", key: "name" },
    { title: "Price", key: "price", sortable: false },
    { title: "Limits", key: "limits", sortable: false },
    { title: "Version", key: "version" },
    { title: "Status", key: "status", sortable: false },
    { title: "Actions", key: "actions", sortable: false },
];
const emptyForm = () => ({ name: "", slug: "", description: "", currency: "KES", monthly_price: "", annual_price: "", trial_days: "", support_level: "standard", is_default: false, max_doctors: "", max_staff: "", max_locations: "", max_monthly_bookings: "", max_sms: "", max_storage_mb: "", max_admin_users: "", sort_order: "" });
const form = ref(emptyForm());
async function fetchPlans() { loading.value = true; try { const { data } = await adminService.getPlans(); plans.value = data.data; } catch (err) { console.error(err); } finally { loading.value = false; } }
function openCreate() { isEditing.value = false; form.value = emptyForm(); formDialog.value = true; }
function openEdit(p) { isEditing.value = true; selectedPlan.value = p; form.value = { name: p.name, slug: p.slug, description: p.description || "", currency: p.currency || "KES", monthly_price: p.monthly_price, annual_price: p.annual_price, trial_days: p.trial_days ?? "", support_level: p.support_level || "standard", is_default: !!p.is_default, max_doctors: p.max_doctors ?? "", max_staff: p.max_staff ?? "", max_locations: p.max_locations ?? "", max_monthly_bookings: p.max_monthly_bookings ?? "", max_sms: p.max_sms ?? "", max_storage_mb: p.max_storage_mb ?? "", max_admin_users: p.max_admin_users ?? "", sort_order: p.sort_order ?? "" }; formDialog.value = true; }
async function savePlan() {
    try {
        const payload = { ...form.value, trial_days: form.value.trial_days === "" ? null : form.value.trial_days };
        if (isEditing.value) { await adminService.updatePlan(selectedPlan.value.id, payload); }
        else { await adminService.createPlan(payload); }
        formDialog.value = false; notify(isEditing.value ? "Plan updated" : "Plan created"); fetchPlans();
    } catch (err) { notifyError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(", ") : (err.response?.data?.error || "Error saving plan")); }
}
function openStatus(p, active) { statusTarget.value = p; statusReason.value = ""; statusDialog.value = true; }
async function saveStatus() { try { await adminService.setPlanActive(statusTarget.value.id, statusTarget.value.is_active ? false : true, statusReason.value); statusDialog.value = false; notify(statusTarget.value.is_active ? "Plan deactivated" : "Plan activated"); fetchPlans(); } catch (err) { notifyError(err.response?.data?.error || "Error updating plan status"); } }
async function openVersions(p) { versionsTarget.value = p; versionsDialog.value = true; versions.value = []; try { const { data } = await adminService.getPlanVersions(p.id); versions.value = data.data || []; } catch (err) { console.error(err); } }
function formatMoney(p) { const cur = p.currency || "KES"; return `${cur} ${Number(p.monthly_price || 0).toLocaleString()} / mo`; }
function limitsLabel(p) { return `Docs ${p.max_doctors ?? "∞"} · Staff ${p.max_staff ?? "∞"} · Locs ${p.max_locations ?? "∞"} · BK ${p.max_monthly_bookings ?? "∞"}`; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : "-"; }
onMounted(fetchPlans);
</script>