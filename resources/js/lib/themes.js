export const THEMES = [
    {
        id: 'default',
        name: 'Default',
        description: 'Noche guaraní, oro y azul',
        colors: ['#05080b', '#eec25f', '#0038a8'],
        themeColor: '#05080b',
    },
    {
        id: 'claro',
        name: 'Claro',
        description: 'Papel, tinta y sol de mediodía',
        colors: ['#f4efe3', '#d9a93f', '#1e2b26'],
        themeColor: '#f4efe3',
    },
    {
        id: 'terere',
        name: 'Tereré',
        description: 'Yerba fresca, guampa y monte',
        colors: ['#04100a', '#bfe167', '#93bf42'],
        themeColor: '#04100a',
    },
    {
        id: 'larissa',
        name: 'Larissa Riquelme',
        description: 'Rosa pasarela y oro',
        colors: ['#120410', '#ff2d78', '#f5b93f'],
        themeColor: '#120410',
    },
    {
        id: 'baila',
        name: 'Baila Conmigo',
        description: 'Neón violeta de estudio',
        colors: ['#08040f', '#e17dff', '#3b7bff'],
        themeColor: '#08040f',
    },
    {
        id: 'futbol',
        name: 'Fútbol',
        description: 'Albirroja de domingo',
        colors: ['#0a0305', '#e11d2f', '#0a2f8f'],
        themeColor: '#0a0305',
    },
    {
        id: 'asado',
        name: 'Asado',
        description: 'Brasa, carbón y humo',
        colors: ['#0d0705', '#f9a03d', '#a85710'],
        themeColor: '#0d0705',
    },
    {
        id: 'mandioca',
        name: 'Mandioca',
        description: 'Tierra, raíz y almidón',
        colors: ['#0e0b06', '#ddb84a', '#8c6a17'],
        themeColor: '#0e0b06',
    },
    {
        id: 'emo',
        name: 'Emo 2007',
        description: 'Negro, flequillo y rosa chicle',
        colors: ['#040407', '#ff5cbb', '#a11c66'],
        themeColor: '#040407',
    },
];

export const THEME_STORAGE_KEY = 'gacha-theme';

export const DEFAULT_THEME = THEMES[0];

export function findTheme(id) {
    return THEMES.find((theme) => theme.id === id) ?? DEFAULT_THEME;
}

export function getStoredTheme() {
    try {
        const stored = window.localStorage.getItem(THEME_STORAGE_KEY);

        return stored && THEMES.some((theme) => theme.id === stored) ? stored : DEFAULT_THEME.id;
    } catch {
        return DEFAULT_THEME.id;
    }
}

export function applyTheme(id) {
    const theme = findTheme(id);

    document.documentElement.setAttribute('data-theme', theme.id);

    try {
        window.localStorage.setItem(THEME_STORAGE_KEY, theme.id);
    } catch {
        // Sin almacenamiento disponible: el tema vale solo para esta visita.
    }

    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', theme.themeColor);
    }

    return theme.id;
}

export function initTheme() {
    const id = getStoredTheme();

    document.documentElement.setAttribute('data-theme', id);

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            document.documentElement.classList.add('theme-ready');
        });
    });

    return id;
}
