export const RARITY = {
    3: {
        label: 'Legendario',
        color: '#f5c85c',
        soft: 'rgba(245, 200, 92, 0.14)',
        glow: 'rgba(245, 200, 92, 0.45)',
        gradient: 'linear-gradient(135deg, #fdf3d8 0%, #eec25f 45%, #b47f26 100%)',
        blurb: 'Las figuras que cambiaron el rumbo del país.',
    },
    2: {
        label: 'Épico',
        color: '#4ec3ff',
        soft: 'rgba(78, 195, 255, 0.12)',
        glow: 'rgba(78, 195, 255, 0.4)',
        gradient: 'linear-gradient(135deg, #c8f0ff 0%, #4ec3ff 50%, #1b6fa8 100%)',
        blurb: 'Protagonistas de la historia, el arte y el deporte.',
    },
    1: {
        label: 'Común',
        color: '#9fb3c8',
        soft: 'rgba(159, 179, 200, 0.1)',
        glow: 'rgba(159, 179, 200, 0.35)',
        gradient: 'linear-gradient(135deg, #e2ebf3 0%, #9fb3c8 55%, #5f7488 100%)',
        blurb: 'Presidentes, cronistas y exploradores.',
    },
};

export const RARITY_ORDER = [3, 2, 1];

export function rarityMeta(rarity) {
    return RARITY[rarity] ?? RARITY[1];
}

export function formatNumber(value) {
    return new Intl.NumberFormat('es-PY').format(Number(value) || 0);
}

export function formatCoins(value) {
    return `₲ ${formatNumber(value)}`;
}
