<template>
    <div class="register-page" style="min-height: 100vh; background: linear-gradient(135deg, #0c1b3f 0%, #11406e 60%, #147ab4 100%);">
        <div class="d-flex align-center justify-center pa-6" style="min-height: 100vh;">
            <v-card width="940" max-width="100%" elevation="12" rounded="xl" class="overflow-hidden">
                <v-row no-gutters>
                    <!-- Brand / value panel -->
                    <v-col cols="12" md="5" class="d-none d-md-flex">
                        <div class="pa-8 fill-height d-flex flex-column justify-space-between"
                             style="background: linear-gradient(160deg, #0d2b52 0%, #0e5d92 100%); color: #fff;">
                            <div>
                                <div class="d-flex align-center mb-6">
                                    <v-avatar size="40" color="white" class="mr-3">
                                        <v-icon icon="mdi-stethoscope" color="#0e5d92"></v-icon>
                                    </v-avatar>
                                    <span class="text-h5 font-weight-black">Afya Plaza</span>
                                </div>
                                <h2 class="text-h4 font-weight-bold mb-3">Join the healthcare marketplace.</h2>
                                <p class="text-body-1 text-white text-medium-emphasis-seventy" style="color: rgba(255,255,255,.8);">
                                    One account to book verified doctors, run your clinic, or grow your practice.
                                </p>
                            </div>
                            <v-list bg-color="transparent" class="pa-0">
                                <v-list-item v-for="perk in perks" :key="perk.title" class="px-0" density="comfortable">
                                    <template #prepend>
                                        <v-icon :icon="perk.icon" color="primary-lighten-4" class="mr-3"></v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2 font-weight-medium">{{ perk.title }}</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </div>
                    </v-col>

                    <!-- Form panel -->
                    <v-col cols="12" md="7">
                        <div class="pa-8">
                            <div class="d-flex align-center justify-space-between mb-4">
                                <div>
                                    <h1 class="text-h5 font-weight-bold">Create account</h1>
                                    <p class="text-body-2 text-medium-emphasis mt-1">Tell us who you are to personalise your workspace.</p>
                                </div>
                                <v-btn variant="text" color="primary" size="small" :to="'/'">Back home</v-btn>
                            </div>

                            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="error = ''">
                                {{ error }}
                            </v-alert>

                            <!-- Role selector -->
                            <div class="mb-4">
                                <div class="text-subtitle-2 font-weight-bold mb-2">I am a...</div>
                                <v-row dense>
                                    <v-col v-for="opt in roles" :key="opt.value" cols="4">
                                        <v-card
                                            @click="form.role = opt.value"
                                            :variant="form.role === opt.value ? 'tonal' : 'outlined'"
                                            :color="form.role === opt.value ? 'primary' : ''"
                                            class="role-card pa-3 text-center cursor-pointer"
                                            rounded="lg"
                                            style="border-width: 2px;"
                                        >
                                            <v-icon :icon="opt.icon" size="28" class="mb-1" :color="form.role === opt.value ? 'primary' : 'medium-emphasis'"></v-icon>
                                            <div class="text-body-2 font-weight-bold">{{ opt.label }}</div>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </div>

                            <v-form @submit.prevent="handleRegister">
                                <v-text-field v-model="form.name" label="Full name" prepend-inner-icon="mdi-account-outline"
                                    variant="outlined" density="comfortable" :error="!!errors.name" :error-messages="errors.name" class="mb-2" />
                                <v-text-field v-model="form.email" label="Email address" type="email" prepend-inner-icon="mdi-email-outline"
                                    variant="outlined" density="comfortable" :error="!!errors.email" :error-messages="errors.email" class="mb-2" />
                                <v-text-field v-model="form.phone" label="Phone (optional)" type="tel" prepend-inner-icon="mdi-phone-outline"
                                    variant="outlined" density="comfortable" class="mb-2" />
                                <v-row dense>
                                    <v-col cols="12" sm="6">
                                        <v-text-field v-model="form.password" label="Password" :type="showPassword ? 'text' : 'password'"
                                            prepend-inner-icon="mdi-lock-outline"
                                            :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                                            variant="outlined" density="comfortable" :error="!!errors.password" :error-messages="errors.password"
                                            class="mb-2" @click:append-inner="showPassword = !showPassword" />
                                    </v-col>
                                    <v-col cols="12" sm="6">
                                        <v-text-field v-model="form.password_confirmation" label="Confirm password" :type="showPassword ? 'text' : 'password'"
                                            prepend-inner-icon="mdi-lock-check-outline" variant="outlined" density="comfortable" class="mb-2" />
                                    </v-col>
                                </v-row>

                                <v-btn type="submit" color="primary" block size="large" :loading="loading" class="mt-2">
                                    Create my account
                                </v-btn>
                            </v-form>

                            <div class="text-center mt-5">
                                <span class="text-body-2 text-medium-emphasis">
                                    Already have an account?
                                    <router-link to="/login" class="text-primary font-weight-medium">Sign in</router-link>
                                </span>
                            </div>
                        </div>
                    </v-col>
                </v-row>
            </v-card>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';

const auth = useAuthStore();
const router = useRouter();

const roles = [
    { value: 'patient', label: 'Patient', icon: 'mdi-account-heart' },
    { value: 'doctor', label: 'Doctor', icon: 'mdi-stethoscope' },
    { value: 'facility', label: 'Facility', icon: 'mdi-hospital-building' },
];

const form = reactive({
    role: 'patient',
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});
const errors = reactive({ name: '', email: '', password: '' });
const error = ref('');
const loading = ref(false);
const showPassword = ref(false);

const perks = [
    { icon: 'mdi-shield-check-outline', title: 'Verified doctors & vetted facilities' },
    { icon: 'mdi-calendar-check', title: 'Book appointments in seconds' },
    { icon: 'mdi-cash-multiple', title: 'Transparent M-Pesa payments' },
];

function validate() {
    errors.name = '';
    errors.email = '';
    errors.password = '';
    let ok = true;

    if (form.name.trim().length < 2) { errors.name = 'Enter your full name'; ok = false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) { errors.email = 'Enter a valid email address'; ok = false; }
    if (form.password.length < 6) { errors.password = 'Password must be at least 6 characters'; ok = false; }
    if (form.password !== form.password_confirmation) { errors.password = 'Passwords do not match'; ok = false; }
    return ok;
}

async function handleRegister() {
    error.value = '';
    if (!validate()) return;

    loading.value = true;
    try {
        await auth.register({
            name: form.name,
            email: form.email,
            phone: form.phone || null,
            password: form.password,
            password_confirmation: form.password_confirmation,
            role: form.role,
        });
        redirectAfterRegister();
    } catch (e) {
        const data = e.response?.data;
        if (data?.errors?.email) {
            errors.email = data.errors.email[0];
        } else if (data?.message) {
            error.value = data.message;
        } else {
            error.value = 'Registration failed. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}

function redirectAfterRegister() {
    if (form.role === 'doctor') { router.push('/doctor/onboarding'); return; }
    if (form.role === 'facility') { router.push('/facility/onboarding'); return; }
    router.push({ name: 'home' });
}
</script>

<style scoped>
.role-card { transition: transform .15s ease, box-shadow .15s ease; }
.role-card:hover { transform: translateY(-2px); }
</style>