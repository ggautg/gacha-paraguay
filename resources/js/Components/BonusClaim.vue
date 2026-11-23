<script setup>
/**
 * Botón de bonificación con cuenta regresiva en vivo.
 *
 * Antes el intervalo y el cálculo de tiempo vivían en Home.vue. Al mudarlos
 * aquí, la página solo escucha `claim` y este componente puede desmontarse sin
 * dejar un `setInterval` corriendo: el temporizador se limpia en onBeforeUnmount.
 */
import { computed, onBeforeUnmount, ref } from 'vue';
import { formatCoins } from '../lib/rarity';

const props = defineProps({
    readyAt: { type: String, default: '' },
    coins: { type: Number, default: 0 },
    processing: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['claim']);

const now = ref(Date.now());
const ticker = setInterval(() => {
    now.value = Date.now();
}, 1000);

onBeforeUnmount(() => clearInterval(ticker));

const seconds = computed(() => {
    if (!props.readyAt) {
        return 0;
    }

    return Math.max(0, Math.floor((new Date(props.readyAt).getTime() - now.value) / 1000));
});

const label = computed(() => {
    if (seconds.value <= 0) {
        return 'Reclamar bonificación';
    }

    const hours = Math.floor(seconds.value / 3600);
    const minutes = Math.floor((seconds.value % 3600) / 60);
    const rest = seconds.value % 60;

    if (hours > 0) {
        return `Disponible en ${hours}h ${String(minutes).padStart(2, '0')}m`;
    }

    return `Disponible en ${minutes}:${String(rest).padStart(2, '0')}`;
});

function claim() {
    if (seconds.value <= 0 && !props.processing) {
        emit('claim');
    }
}
</script>

<template>
    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
        <button
            type="button"
            class="group inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] transition"
            :class="seconds <= 0 ? 'text-gold-300 hover:text-gold-200' : 'cursor-default text-bone-400'"
            :disabled="seconds > 0 || processing"
            :aria-busy="processing"
            :title="seconds > 0 ? label : `Reclamar ${formatCoins(coins)}`"
            @click="claim"
        >
            <span class="grid h-6 w-6 place-items-center rounded-full border border-current/30 surface-2">
                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                </svg>
            </span>
            {{ processing ? 'Reclamando…' : label }}
            <span v-if="seconds <= 0" class="text-gold-400/80">+{{ formatCoins(coins) }}</span>
        </button>

        <span v-if="error" class="text-[11px] font-medium text-flag-red" role="alert">{{ error }}</span>
    </div>
</template>
