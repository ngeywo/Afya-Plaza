import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

// Vuetify
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

const vuetify = createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                dark: false,
                colors: {
                    // Deep healthcare teal – primary actions & brand
                    primary: '#0F766E',
                    // Soft slate – supporting/secondary actions
                    secondary: '#475569',
                    accent: '#2563EB',
                    // Meaningful semantics only
                    success: '#16A34A',
                    warning: '#D97706',
                    error: '#DC2626',
                    info: '#2563EB',
                    // Neutral shells
                    background: '#F4F6F8',
                    surface: '#FFFFFF',
                    'surface-variant': '#F1F5F9',
                    'on-surface': '#0F172A',
                    'on-primary': '#FFFFFF',
                    'on-secondary': '#FFFFFF',
                    'on-success': '#FFFFFF',
                    'on-warning': '#FFFFFF',
                    'on-error': '#FFFFFF',
                },
            },
        },
    },
    defaults: {
        // Consistent, calm card language across the whole platform
        VCard: {
            rounded: 'lg',
            elevation: 0,
            border: true,
        },
        // Consistent form language
        VTextField: {
            variant: 'outlined',
            density: 'comfortable',
        },
        VSelect: {
            variant: 'outlined',
            density: 'comfortable',
        },
        VTextarea: {
            variant: 'outlined',
        },
        // Refined button shape
        VBtn: {
            rounded: 'lg',
        },
        VAppBar: {
            flat: true,
        },
        // Calmer dialog radius
        VDialog: {
            rounded: 'lg',
        },
        VList: {
            density: 'comfortable',
        },
    },
});

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.use(vuetify);
app.mount('#app');