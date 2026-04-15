import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';

// When Laravel returns 419 (CSRF token expired / session timeout), reload the
// page with preserveState so all Vue component state (open modals, form values)
// is kept intact. The user simply retries the same action immediately.
router.on('invalid', (event) => {
    if (event.detail.response.status === 419) {
        event.preventDefault();
        sessionStorage.setItem('csrf_expired', '1');
        router.reload({ preserveState: true, preserveScroll: true });
    }
});

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        delay: 0,
        color: '#2563eb', // A bright blue that stands out
        includeCSS: true,
        showSpinner: false,
    },
});

// Strip Unovis tooltip container background so ChartTooltip owns the visual design.
// Must be done via JS because Unovis injects its :root CSS variables at runtime,
// after app.css loads, and inline element styles always beat stylesheet declarations.
const root = document.documentElement;
root.style.setProperty('--vis-tooltip-background-color', 'transparent');
root.style.setProperty('--vis-tooltip-border-color', 'transparent');
root.style.setProperty('--vis-tooltip-box-shadow', 'none');
root.style.setProperty('--vis-tooltip-padding', '0');
root.style.setProperty('--vis-tooltip-border-radius', '0');
root.style.setProperty('--vis-dark-tooltip-background-color', 'transparent');
root.style.setProperty('--vis-dark-tooltip-border-color', 'transparent');

// This will set light / dark mode on page load...
initializeTheme();
