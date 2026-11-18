<script setup>
/**
 * Pastilla de rareza: color, etiqueta y estrellas en un único componente.
 *
 * Antes el mismo `<span>` con tres estilos en línea estaba repetido en la
 * tarjeta, el modal de detalle y el revelado de la tirada. Centralizarlo
 * permite que un cambio de paleta se refleje en los tres sitios a la vez.
 * Los colores vienen de `lib/rarity.js`, que a su vez usa variables CSS:
 * por eso la pastilla sigue el tema activo sin lógica adicional.
 */
import { computed } from 'vue';
import { rarityMeta } from '../lib/rarity';
import RarityStars from './RarityStars.vue';

const props = defineProps({
    rarity: { type: Number, required: true },
    size: { type: String, default: 'sm' }, // 'xs' para tarjetas chicas, 'lg' para el modal
    stars: { type: Boolean, default: true },
    label: { type: String, default: '' },
});

const meta = computed(() => rarityMeta(props.rarity));
const textClass = computed(() => (props.size === 'lg' ? 'px-2.5 py-1 text-[10px]' : 'px-2 py-0.5 text-[8px]'));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1 rounded-full font-bold uppercase tracking-[0.15em] backdrop-blur"
        :class="textClass"
        :style="{
            color: meta.color,
            backgroundColor: meta.soft,
            border: `1px solid ${meta.border}`,
        }"
    >
        {{ label || meta.label }}
        <RarityStars v-if="stars" :rarity="rarity" />
    </span>
</template>
