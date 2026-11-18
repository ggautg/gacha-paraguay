<script setup>
/**
 * Estado vacío reutilizable (vitrina sin figuras, búsqueda sin resultados,
 * personaje todavía no descubierto).
 *
 * Unificarlo evita que cada pantalla invente su propio "?" con estilos
 * distintos. El bloque `action` es opcional: la búsqueda de la colección necesita
 * un botón para limpiar filtros, la vitrina de la home no.
 */
defineProps({
    icon: { type: String, default: '?' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed line-soft px-6 py-12 text-center">
        <span class="grid h-12 w-12 place-items-center rounded-full border border-gold-400/30 bg-gold-400/10 font-display text-lg text-gold-300">
            {{ icon }}
        </span>
        <p class="mt-4 font-display text-base text-bone-100">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-xs leading-relaxed text-bone-400">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
