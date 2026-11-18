<script setup>
/**
 * Barra de progreso delgada, con el relleno a cargo del tema activo.
 *
 * Se usa tanto para el avance de la colección como para las probabilidades
 * de cada rareza. El ancho se aplica con un estilo en línea porque es un
 * valor dinámico del servidor, pero los colores son tokens CSS: así una
 * paleta nueva no obliga a tocar este componente.
 */
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, required: true },
    gradient: { type: String, default: '' },
});

const width = computed(() => `${Math.min(100, Math.max(0, Number(props.value) || 0))}%`);
const fill = computed(() => props.gradient || 'linear-gradient(to right, var(--accent-surface-3), var(--accent-surface-1))');
</script>

<template>
    <div class="h-1.5 w-full overflow-hidden rounded-full surface-3" role="progressbar" :aria-valuenow="Math.round(value)" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full transition-all duration-700" :style="{ width, background: fill }" />
    </div>
</template>
