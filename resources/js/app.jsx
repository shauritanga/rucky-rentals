import './bootstrap';
import '../css/app.css';
// Fonts are served from this site (latin subset), not fetched from Google on every visit.
import '@fontsource/dm-sans/latin-300.css';
import '@fontsource/dm-sans/latin-400.css';
import '@fontsource/dm-sans/latin-500.css';
import '@fontsource/dm-sans/latin-600.css';
import '@fontsource/instrument-serif/latin-400.css';
import '@fontsource/instrument-serif/latin-400-italic.css';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { installInputCase } from './utils/inputCase';
import { installBusyButtons } from './utils/busyButtons';
import { installNetworkGuard } from './utils/networkGuard';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

installInputCase();
installBusyButtons();
installNetworkGuard();

createInertiaApp({
    title: (title) => `${title} — Mwamba Properties`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#3b82f6' },
});
