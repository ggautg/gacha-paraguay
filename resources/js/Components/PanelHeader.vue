<script setup>
/**
 * Encabezado nuevo de panel: título pequeño, pista opcional y acción a la derecha.
 *
 * Extraído porque "Probabilidades", "Tu vitrina" y "Últimas tiradas"
 * repetían exactamente el mismo `flex items-center justify-between`. El slot
 * `action` permite pasar desde un texto hasta un `Link` sin crear props
 * especiales para cada caso.
 */
defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-baseline gap-2">
            <h2 class="eyebrow">{{ title }}</h2>
            <span v-if="hint" class="text-[10px] text-bone-400">{{ hint }}</span>
        </div>
        <slot name="action" />
    </div>
</template>
