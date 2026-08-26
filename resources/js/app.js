import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n, currentLocale } from './i18n';

// Backend-Locale (Mails, Planungs-Templates) an die UI-Sprache koppeln.
window.axios.defaults.headers.common['X-App-Locale'] = currentLocale();

createInertiaApp({
    title: (title) => title || import.meta.env.VITE_APP_NAME || 'Plandu',
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0f9d76',
    },
});
