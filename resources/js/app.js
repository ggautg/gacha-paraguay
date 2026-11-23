import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { setSueldoMinimo } from './lib/rarity';
import { initTheme } from './lib/themes';

initTheme();

const pages = import.meta.glob('./pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · Gacha Guaraní` : 'Gacha Guaraní · Historia del Paraguay'),
    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`No se encontró la página de Inertia: ${name}`);
        }

        return page();
    },
    setup({ el, App, props, plugin }) {
        // El valor inicial de Inertia trae el sueldo de config/gachapon.php.
        setSueldoMinimo(props.initialPage.props.gacha?.salary?.amount);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    // La barra de navegación usa el acento del tema activo en vez de un dorado
    // fijo: en paletas como Emo o Tereré el dorado se veía fuera de lugar.
    progress: {
        color: 'rgb(var(--accent-rgb))',
        showSpinner: false,
    },
});
