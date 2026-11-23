<script setup>
/**
 * Panel de probabilidades, progreso por rareza y medidor de garantía.
 *
 * Reúne tres piezas que antes se armaban dentro de Home.vue. El medidor usa
 * puntos en vez de una barra porque la garantía es una cuenta de tiradas: cada
 * punto es una tirada y el último se enciende cuando el premio es seguro.
 */
import { computed } from 'vue';
import PanelHeader from './PanelHeader.vue';
import ProgressBar from './ProgressBar.vue';
import RarityStars from './RarityStars.vue';

const props = defineProps({
    rates: { type: Array, default: () => [] },
    pity: { type: Number, default: 0 },
    pityLimit: { type: Number, default: 12 },
});

const remaining = computed(() => Math.max(1, props.pityLimit - props.pity));
</script>

<template>
    <div class="panel paper-noise p-5">
        <PanelHeader title="Probabilidades" hint="por tirada" />

        <div class="mt-4 space-y-3.5">
            <div v-for="rate in rates" :key="rate.rarity">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="flex items-center gap-1.5 font-semibold" :style="{ color: rate.meta.color }">
                        <RarityStars :rarity="rate.rarity" />
                        {{ rate.meta.label }}
                    </span>
                    <span class="tabular-nums text-bone-300">{{ rate.rate }}%</span>
                </div>
                <ProgressBar class="mt-1.5" :value="rate.rate" :gradient="rate.meta.gradient" />
                <p class="mt-1 text-[9px] text-bone-400">
                    {{ rate.owned }}/{{ rate.total }} descubiertos
                </p>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between rounded-lg border line-soft surface-2 px-3 py-2">
            <span class="text-[10px] uppercase tracking-wider text-bone-400">Garantía</span>
            <div class="flex items-center gap-1" :aria-label="`${remaining} tiradas restantes para la garantía`">
                <span
                    v-for="dot in pityLimit"
                    :key="dot"
                    class="h-1.5 w-1.5 rounded-full"
                    :class="dot <= pity ? 'bg-gold-400' : 'surface-3'"
                />
            </div>
            <span class="text-[10px] font-medium text-gold-300">{{ remaining }} restantes</span>
        </div>
    </div>
</template>
