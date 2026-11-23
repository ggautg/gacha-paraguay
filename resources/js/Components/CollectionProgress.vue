<script setup>
/**
 * Anillo de finalización de la colección.
 *
 * Vive aparte de Album.vue porque el SVG con el degradado del tema ensuciaba
 * la página. `stroke-dashoffset` permite animar el avance sin librerías; los
 * colores salen de tokens CSS, así que siguen la paleta activa.
 */
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
});

const circumference = 2 * Math.PI * 46;
const dashOffset = computed(() => circumference - (Math.min(100, Math.max(0, props.value)) / 100) * circumference);
</script>

<template>
    <div class="relative h-28 w-28 shrink-0">
        <svg viewBox="0 0 110 110" class="h-full w-full -rotate-90" role="img" :aria-label="`Colección completada en un ${value} %`">
            <circle cx="55" cy="55" r="46" fill="none" style="stroke: var(--border-soft)" stroke-width="7" />
            <circle
                cx="55"
                cy="55"
                r="46"
                fill="none"
                stroke="url(#ringGradient)"
                stroke-width="7"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                class="transition-all duration-1000"
            />
            <defs>
                <linearGradient id="ringGradient" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" style="stop-color: var(--accent-surface-3)" />
                    <stop offset="55%" style="stop-color: var(--accent-surface-2)" />
                    <stop offset="100%" style="stop-color: var(--accent-surface-1)" />
                </linearGradient>
            </defs>
        </svg>
        <div class="absolute inset-0 grid place-items-center text-center">
            <div>
                <p class="font-display text-2xl font-semibold text-bone-50">{{ value }}%</p>
                <p class="text-[9px] uppercase tracking-[0.2em] text-bone-400">completo</p>
            </div>
        </div>
    </div>
</template>
