<script setup>
/**
 * Botón de cierre compartido por los tres overlays de la aplicación.
 *
 * Tener un solo botón garantiza el mismo área táctil (44 px en móvil), el
 * mismo `aria-label` y el mismo contraste en todos los temas. El SVG se marca
 * como `aria-hidden` porque el texto accesible ya lo aporta `aria-label`.
 */
defineProps({
    label: { type: String, default: 'Cerrar' },
    size: { type: String, default: 'md' }, // 'sm' para el selector de temas
});
</script>

<template>
    <button
        type="button"
        class="grid shrink-0 place-items-center rounded-full border line-strong surface-2 text-bone-200 backdrop-blur transition hover:border-gold-400/60 hover:text-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-400/70"
        :class="size === 'sm' ? 'h-7 w-7' : 'h-10 w-10'"
        :aria-label="label"
    >
        <svg
            viewBox="0 0 24 24"
            class="fill-none stroke-current"
            :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'"
            stroke-width="2"
            aria-hidden="true"
        >
            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
        </svg>
    </button>
</template>
