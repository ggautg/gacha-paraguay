export const RARITY = {
    3: {
        label: 'Legendario',
        color: 'var(--color-rare-3)',
        soft: 'var(--rare-3-soft)',
        glow: 'var(--rare-3-glow)',
        border: 'var(--rare-3-border)',
        gradient: 'linear-gradient(135deg, #fdf3d8 0%, #eec25f 45%, #b47f26 100%)',
        blurb: 'Las figuras que cambiaron el rumbo del país.',
    },
    2: {
        label: 'Épico',
        color: 'var(--color-rare-2)',
        soft: 'var(--rare-2-soft)',
        glow: 'var(--rare-2-glow)',
        border: 'var(--rare-2-border)',
        gradient: 'linear-gradient(135deg, #c8f0ff 0%, #4ec3ff 50%, #1b6fa8 100%)',
        blurb: 'Protagonistas de la historia, el arte y el deporte.',
    },
    1: {
        label: 'Común',
        color: 'var(--color-rare-1)',
        soft: 'var(--rare-1-soft)',
        glow: 'var(--rare-1-glow)',
        border: 'var(--rare-1-border)',
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

/**
 * Valor del sueldo mínimo vigente (₲ 3.044.000, Decreto 6225/2026).
 * Debe coincidir con `salary.amount` de config/gachapon.php.
 */
export const SUELDO_MINIMO = 3044000;

export function formatGuaranies(value) {
    return `₲ ${formatNumber(value)}`;
}

export function formatSueldos(value) {
    const sueldos = (Number(value) || 0) / SUELDO_MINIMO;
    const label = new Intl.NumberFormat('es-PY', {
        minimumFractionDigits: Number.isInteger(sueldos) ? 0 : 1,
        maximumFractionDigits: 2,
    }).format(sueldos);

    return `${label} SM`;
}

export function formatCoins(value) {
    // La moneda del juego es el sueldo mínimo, no el guaraní suelto.
    return formatSueldos(value);
}
