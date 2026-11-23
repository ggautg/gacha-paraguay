export const RARITY = {
    3: {
        label: 'Legendario',
        color: 'var(--color-rare-3)',
        soft: 'var(--rare-3-soft)',
        glow: 'var(--rare-3-glow)',
        border: 'var(--rare-3-border)',
        gradient: 'linear-gradient(135deg, color-mix(in srgb, var(--color-rare-3) 55%, white), var(--color-rare-3), color-mix(in srgb, var(--color-rare-3) 70%, black))',
        blurb: 'Las figuras que cambiaron el rumbo del país.',
    },
    2: {
        label: 'Épico',
        color: 'var(--color-rare-2)',
        soft: 'var(--rare-2-soft)',
        glow: 'var(--rare-2-glow)',
        border: 'var(--rare-2-border)',
        gradient: 'linear-gradient(135deg, color-mix(in srgb, var(--color-rare-2) 55%, white), var(--color-rare-2), color-mix(in srgb, var(--color-rare-2) 70%, black))',
        blurb: 'Protagonistas de la historia, el arte y el deporte.',
    },
    1: {
        label: 'Común',
        color: 'var(--color-rare-1)',
        soft: 'var(--rare-1-soft)',
        glow: 'var(--rare-1-glow)',
        border: 'var(--rare-1-border)',
        gradient: 'linear-gradient(135deg, color-mix(in srgb, var(--color-rare-1) 55%, white), var(--color-rare-1), color-mix(in srgb, var(--color-rare-1) 70%, black))',
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
 * Valor del sueldo mínimo usado para convertir guaraníes en SM.
 *
 * Se inicializa con el valor vigente y `app.js` lo sobrescribe con
 * `gacha.salary.amount`, que viene de config/gachapon.php. Así el servidor y
 * el navegador comparten una sola fuente de verdad cuando cambia el decreto.
 */
let sueldoMinimo = 3044000;

export function setSueldoMinimo(amount) {
    const valor = Number(amount);

    if (valor > 0) {
        sueldoMinimo = valor;
    }
}

export function formatGuaranies(value) {
    return `₲ ${formatNumber(value)}`;
}

export function formatSueldos(value) {
    const sueldos = (Number(value) || 0) / sueldoMinimo;
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
