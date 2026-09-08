<template>
    <div class="login-page d-flex align-center justify-center" style="min-height: 80vh;">
        <v-card class="pa-6" width="440" max-width="100%" elevation="2">
            <div class="text-center mb-6">
                <v-icon icon="mdi-stethoscope" color="primary" size="48" class="mb-2"></v-icon>
                <h1 class="text-h5 font-weight-bold">Welcome back</h1>
                <p class="text-body-2 text-medium-emphasis mt-1">Sign in to your Docta Plaza account</p>
            </div>

            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
                {{ error }}
            </v-alert>

            <v-form @submit.prevent="handleLogin">
                <v-text-field
                    v-model="form.email"
                    label="Email address"
                    type="email"
                    prepend-inner-icon="mdi-email-outline"
                    variant="outlined"
                    density="comfortable"
                    :error="!!errors.email"
                    :error-messages="errors.email"
                    class="mb-2"
                />
                <v-text-field
                    v-model="form.password"
                    label="Password"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock-outline"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                    variant="outlined"
                    density="comfortable"
                    :error="!!errors.password"
                    :error-messages="errors.password"
                    class="mb-2"
                    @click:append-inner="showPassword = !showPassword"
                />
                <v-btn
                    type="submit"
                    color="primary"
                    block
                    size="large"
                    :loading="loading"
                    class="mt-2"
                >
                    Sign in
                </v-btn>
            </v-form>

            <div class="text-center mt-6">
                <span class="text-body-2 text-medium-emphasis">
                    Don't have an account?
                    <router-link to="/register" class="text-primary font-weight-medium">Sign up</router-link>
                </span>
            </div>

            <!-- <v-divider class="my-4"></v-divider>
            <div class="text-caption text-medium-emphasis text-center">
                Test credentials: <code>patient@docta-plaza.test</code> / <code>password</code>
            </div> -->
        </v-card>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const errors = reactive({ email: '', password: '' });
const error = ref('');
const loading = ref(false);
const showPassword = ref(false);

async function handleLogin() {
    error.value = '';
    errors.email = '';
    errors.password = '';

    if (!form.email) { errors.email = 'Email is required'; return; }
    if (!form.password) { errors.password = 'Password is required'; return; }

    loading.value = true;
    try {
        await auth.login({ email: form.email, password: form.password });
        const redirect = router.currentRoute.value.query.redirect;
        if (redirect) { router.push(redirect); return; }
        if (auth.isPlatformOperator) { router.push("/admin/dashboard"); return; }
        if (auth.isDoctor) { router.push("/doctor/dashboard"); return; }
        if (auth.isFacility) { router.push("/facility/dashboard"); return; }
        router.push("/");
    } catch (e) {
        if (e.response?.data?.message) {
            error.value = e.response.data.message;
        } else if (e.response?.data?.errors?.email) {
            errors.email = e.response.data.errors.email[0];
        } else {
            error.value = 'Login failed. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}
</script>
