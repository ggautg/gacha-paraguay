<script setup>
import { computed } from 'vue';
import { formatCoins, formatGuaranies } from '../lib/rarity';

const props = defineProps({
    coins: { type: Number, default: 0 },
    compact: { type: Boolean, default: false },
});

const label = computed(() => formatCoins(props.coins));
const title = computed(() => `${label.value} · ${formatGuaranies(props.coins)}`);
</script>

<template>
    <span
        class="inline-flex items-center gap-2 rounded-full border border-gold-400/30 bg-gold-400/10 py-1 pl-1 pr-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]"
        :title="title"
    >
        <span
            class="coin-badge coin-glow grid h-6 w-6 place-items-center rounded-full bg-gradient-to-br from-gold-200 to-gold-600 text-[10px] font-black tracking-tight text-night-950"
        >
            SM
        </span>
        <span class="text-sm font-semibold tabular-nums text-gold-200">{{ label }}</span>
        <span v-if="!compact" class="hidden text-[10px] uppercase tracking-wider text-gold-400/70 sm:inline">sueldo mínimo</span>
    </span>
</template>
