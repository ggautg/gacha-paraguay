<script setup>
/**
 * Botones de tirada, precio, error de saldo y nota del sueldo mínimo.
 *
 * Antes este bloque vivía en Home.vue y mezclaba la petición con la forma de
 * presentar los errores. Aquí solo se decide si cada botón está disponible y
 * se muestra el motivo; la acción se delega con el evento `pull`.
 */
import { computed } from 'vue';
import { formatCoins, formatGuaranies } from '../lib/rarity';

const props = defineProps({
    gacha: { type: Object, required: true },
    coins: { type: Number, default: 0 },
    pulling: { type: Number, default: null },
    error: { type: String, default: '' },
});

const emit = defineEmits(['pull']);

const canSingle = computed(() => props.coins >= props.gacha.price_single);
const canMulti = computed(() => props.coins >= props.gacha.price_multi);
const busy = computed(() => props.pulling !== null);

function pull(count) {
    if (!busy.value) {
        emit('pull', count);
    }
}
</script>

<template>
    <div>
        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
            <button
                type="button"
                class="btn-gold group px-7 py-3.5 text-base"
                :disabled="busy || !canMulti"
                :aria-busy="pulling === gacha.multi_pull"
                :title="canMulti ? `Tirar ${gacha.multi_pull} figuras por ${formatCoins(gacha.price_multi)}` : 'Saldo insuficiente para la tirada múltiple'"
                @click="pull(gacha.multi_pull)"
            >
                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 group-hover:translate-x-full" />
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M4 8.5V6a2 2 0 012-2h12a2 2 0 012 2v2.5M4 8.5v9a2 2 0 002 2h12a2 2 0 002-2v-9M4 8.5h16M9 12h6M9 15.5h6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>{{ pulling === gacha.multi_pull ? 'Tirando…' : `Tirar ×${gacha.multi_pull}` }}</span>
                <span class="rounded-md bg-night-950/15 px-2 py-0.5 text-[11px] font-bold tabular-nums">{{ formatCoins(gacha.price_multi) }}</span>
            </button>

            <button
                type="button"
                class="btn-ghost px-5 py-3.5 text-sm"
                :disabled="busy || !canSingle"
                :aria-busy="pulling === 1"
                :title="canSingle ? `Tirar una figura por ${formatCoins(gacha.price_single)}` : 'Saldo insuficiente para la tirada simple'"
                @click="pull(1)"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <circle cx="12" cy="12" r="8" /><path d="M12 8v8M8 12h8" stroke-linecap="round" />
                </svg>
                <span>{{ pulling === 1 ? 'Tirando…' : 'Tirar ×1' }}</span>
                <span class="text-[11px] tabular-nums text-bone-400">{{ formatCoins(gacha.price_single) }}</span>
            </button>
        </div>

        <!-- El error aparece junto a los botones que lo originaron. -->
        <p v-if="error" class="mt-3 text-[11px] font-medium text-flag-red" role="alert">
            {{ error }}
        </p>
        <p v-else-if="!canSingle" class="mt-3 text-[10px] text-bone-400" role="status">
            Saldo insuficiente: reclamá la bonificación o volvé más tarde.
        </p>

        <p class="mt-3 max-w-xl text-[10px] leading-relaxed text-bone-400">
            <span class="font-semibold text-gold-300/90">1 SM = {{ formatGuaranies(gacha.salary.amount) }}</span>
            · Sueldo mínimo legal vigente desde el {{ gacha.salary.since }} ({{ gacha.salary.decree }}).
            Jornal diario: {{ formatGuaranies(gacha.salary.daily) }}.
        </p>
    </div>
</template>
