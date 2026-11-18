<script setup>
/**
 * Tarjeta chica de estadística para la cabecera de la máquina.
 *
 * Las tres cifras (colección, tiradas y legendarios) repetían el mismo panel
 * con distinto color de valor. Centralizarlo deja un solo lugar para ajustar
 * el espaciado y el contraste en las nueve paletas; el sufijo se usa para el
 * denominador («12/84») sin tener que armar markup en la página.
 */
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    suffix: { type: String, default: '' },
    accent: { type: Boolean, default: false },
});
</script>

<template>
    <div class="panel px-3.5 py-3">
        <p class="text-[9px] uppercase tracking-[0.18em] text-bone-400">{{ label }}</p>
        <p class="mt-1 font-display text-lg" :class="accent ? 'text-gold-300' : 'text-bone-50'">
            {{ value }}<span v-if="suffix" class="text-xs text-bone-400">{{ suffix }}</span>
        </p>
    </div>
</template>
