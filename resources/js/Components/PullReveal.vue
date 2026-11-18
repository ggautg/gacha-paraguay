<script setup>
/**
 * Pantalla completa con el resultado de una tirada.
 *
 * Muestra cada figura revelada, el resumen económico (gasto, reembolso por
 * duplicados y saldo) y la acción de repetir la tirada sin volver a la home.
 *
 * El cierre queda bloqueado mientras `busy` es verdadero: si el overlay se
 * cerrara a mitad de la petición, el `watch` de Home volvería a abrirlo al
 * llegar la respuesta y el usuario vería un parpadeo confuso.
 */
import { computed } from 'vue';
import { formatCoins, rarityMeta } from '../lib/rarity';
import { useDismissableOverlay } from '../composables/useDismissableOverlay';
import CharacterPortrait from './CharacterPortrait.vue';
import CloseButton from './CloseButton.vue';
import RarityBadge from './RarityBadge.vue';

const props = defineProps({
    pull: { type: Object, required: true },
    price: { type: Number, default: 0 },
    canAgain: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'again']);

const results = computed(() => props.pull?.results ?? []);
const summary = computed(() => props.pull?.summary ?? {});
const isMulti = computed(() => results.value.length > 1);
const hasLegendary = computed(() => results.value.some((result) => result.rarity === 3));

function close() {
    if (!props.busy) {
        emit('close');
    }
}

function again() {
    if (!props.canAgain || props.busy) {
        return;
    }

    emit('again');
}

// Escape y bloqueo de scroll delegados al composable compartido.
useDismissableOverlay({ active: () => true, onEscape: close });
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex flex-col bg-night-950/92 backdrop-blur-xl" role="dialog" aria-modal="true" aria-label="Resultado de la tirada">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute left-1/2 top-0 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-gold-400/10 blur-[120px]" />
                <div class="absolute bottom-0 right-0 h-[320px] w-[420px] rounded-full bg-flag-blue/15 blur-[120px]" />
            </div>

            <div class="relative flex items-center justify-between gap-4 px-4 py-4 sm:px-8">
                <div>
                    <p class="eyebrow">Resultado de la tirada</p>
                    <h2 class="mt-1 font-display text-lg text-bone-50 sm:text-2xl">
                        <template v-if="results.length === 1">Una nueva figura para tu colección</template>
                        <template v-else>{{ results.length }} figuras reveladas</template>
                    </h2>
                </div>
                <CloseButton label="Cerrar resultado de la tirada" @click="close" />
            </div>

            <div class="relative flex-1 overflow-y-auto px-4 pb-6 sm:px-8">
                <div
                    class="mx-auto grid w-full gap-3 sm:gap-4"
                    :class="isMulti ? 'max-w-6xl grid-cols-2 lg:grid-cols-5' : 'max-w-sm grid-cols-1'"
                >
                    <article
                        v-for="(result, index) in results"
                        :key="`${result.id}-${index}`"
                        class="reveal-card relative overflow-hidden rounded-2xl border bg-night-850"
                        :class="{ 'legendary-card': result.rarity === 3 }"
                        :style="{
                            animationDelay: `${index * 80}ms`,
                            borderColor: rarityMeta(result.rarity).border,
                            boxShadow: `0 24px 70px -34px ${rarityMeta(result.rarity).glow}`,
                        }"
                    >
                        <span
                            v-for="dot in result.rarity === 3 ? 5 : 0"
                            :key="`dot-${dot}`"
                            class="pointer-events-none absolute h-1 w-1 rounded-full bg-gold-200 animate-sparkle"
                            :style="{
                                top: `${12 + dot * 15}%`,
                                left: `${dot % 2 === 0 ? 8 + dot * 6 : 78 - dot * 4}%`,
                                animationDelay: `${dot * 0.28}s`,
                            }"
                        />

                        <div class="relative aspect-[3/4] overflow-hidden">
                            <CharacterPortrait :image="result.image" :name="result.name" owned />

                            <div class="absolute inset-0 bg-gradient-to-t from-night-950 via-night-950/25 to-transparent" />

                            <div class="absolute inset-x-2 top-2 flex items-start justify-between gap-1">
                                <RarityBadge :rarity="result.rarity" size="xs" />
                            </div>

                            <span
                                v-if="result.is_new"
                                class="absolute -right-8 top-7 rotate-45 bg-gold-400 px-8 py-0.5 text-[8px] font-black uppercase tracking-[0.18em] text-night-950 shadow-lg"
                            >
                                Nuevo
                            </span>

                            <div class="absolute inset-x-2.5 bottom-2.5">
                                <p class="font-display text-sm font-semibold leading-tight text-bone-50">{{ result.name }}</p>
                                <p class="mt-0.5 line-clamp-1 text-[10px] text-bone-300">{{ result.role }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 border-t line-soft px-2.5 py-1.5 text-[9px]">
                            <span v-if="result.is_new" class="font-semibold uppercase tracking-wider text-gold-300">Primera copia</span>
                            <span v-else class="font-medium text-bone-400">Duplicado</span>
                            <span v-if="!result.is_new" class="tabular-nums text-gold-300">
                                +{{ formatCoins(result.refund) }} <span class="text-bone-400">({{ result.refund_percent }} %)</span>
                            </span>
                        </div>
                    </article>
                </div>

                <p
                    v-if="!isMulti && results[0]?.extract"
                    class="mx-auto mt-5 max-w-xl text-center text-[12px] leading-relaxed text-bone-300/80"
                >
                    {{ results[0].extract }}
                </p>

                <div
                    v-if="hasLegendary"
                    class="mx-auto mt-5 flex max-w-xl items-center justify-center gap-2 rounded-full border border-gold-400/30 bg-gold-400/10 px-4 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.2em] text-gold-200"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                        <path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.4l6.5-.9z" />
                    </svg>
                    ¡Legendario! Rareza máxima
                </div>
            </div>

            <div class="relative border-t line-soft bg-night-950/80 px-4 py-4 sm:px-8">
                <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 sm:flex-row">
                    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[11px] text-bone-400">
                        <span>Gastaste <strong class="text-bone-200">{{ formatCoins(summary.spent) }}</strong></span>
                        <span v-if="summary.refund > 0">Recuperaste <strong class="text-gold-300">{{ formatCoins(summary.refund) }}</strong></span>
                        <span>Saldo <strong class="text-gold-200">{{ formatCoins(summary.coins) }}</strong></span>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <button
                            type="button"
                            class="btn-ghost px-5 py-2.5 text-sm"
                            :class="{ 'opacity-50': !canAgain, 'animate-pulse': busy }"
                            :disabled="busy || !canAgain"
                            :title="busy ? 'Tirada en curso…' : canAgain ? `Repetir la tirada por ${formatCoins(price)}` : 'No te alcanza el saldo para repetir'"
                            @click="again"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 11a8 8 0 10-2.34 5.66M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>{{ busy ? 'Tirando…' : `Tirar de vuelta ×${results.length}` }}</span>
                            <span class="text-[11px] tabular-nums text-bone-400">{{ formatCoins(price) }}</span>
                        </button>
                        <button type="button" class="btn-gold w-full px-8 py-2.5 text-sm sm:w-auto" :disabled="busy" @click="close">
                            {{ busy ? 'Un momento…' : 'Continuar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.reveal-card {
    opacity: 0;
    animation: reveal-card 0.62s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.legendary-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, transparent 25%, rgb(var(--accent-rgb) / 0.28) 50%, transparent 75%);
    transform: translateX(-140%) skewX(-18deg);
    animation: shine-sweep 2.8s ease-in-out 0.9s infinite;
    pointer-events: none;
    mix-blend-mode: screen;
}
</style>
