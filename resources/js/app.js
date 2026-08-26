import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n, currentLocale } from './i18n';

const APP_NAME = import.meta.env.VITE_APP_NAME || 'ORGDATE';

// Backend-Locale (Mails, Planungs-Templates) an die UI-Sprache koppeln.
window.axios.defaults.headers.common['X-App-Locale'] = currentLocale();

createInertiaApp({
    /*
     * Titel-Regel, identisch zur serverseitigen in app.blade.php:
     *   Startseite      Marke – Slogan
     *   alle anderen    Seitentitel – Marke
     *
     * Ohne das Anhängen der Marke würde der Titel beim Start von JavaScript
     * springen: der Server liefert "Team-BBQ – ORGDATE", Inertia machte
     * daraus "Team-BBQ". Die Startseite gibt den Titel bereits vollständig
     * vor und wird deshalb übersprungen.
     */
    title: (title) => {
        if (!title) return APP_NAME
        return title.startsWith(APP_NAME) ? title : `${title} – ${APP_NAME}`
    },
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
        color: '#5B4BE8',
    },
});
