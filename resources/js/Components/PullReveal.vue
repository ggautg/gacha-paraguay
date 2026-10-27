<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { formatCoins, rarityMeta } from '../lib/rarity';
import PortraitFallback from './PortraitFallback.vue';
import RarityStars from './RarityStars.vue';

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
const bestRarity = computed(() => Math.max(0, ...results.value.map((result) => result.rarity)));
const hasLegendary = computed(() => results.value.some((result) => result.rarity === 3));

function close() {
    emit('close');
}

function again() {
    if (!props.canAgain || props.busy) {
        return;
    }

    emit('again');
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex flex-col bg-night-950/92 backdrop-blur-xl">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute left-1/2 top-0 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-gold-400/10 blur-[120px]" />
                <div class="absolute bottom-0 right-0 h-[320px] w-[420px] rounded-full bg-flag-blue/15 blur-[120px]" />
            </div>

            <div class="relative flex items-center justify-between gap-4 px-4 py-4 sm:px-8">
                <div>
                    <p class="eyebrow">Resultado de la tirada</p>
                    <h2 class="mt-1 font-display text-lg text-bone-50 sm:text-2xl">
                        <template v-if="results.length === 1">Un nuevo sello para tu colección</template>
                        <template v-else>{{ results.length }} sellos revelados</template>
                    </h2>
                </div>
                <button
                    type="button"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-white/15 bg-white/[0.04] text-bone-200 transition hover:border-gold-400/60 hover:text-gold-300"
                    aria-label="Cerrar"
                    @click="close"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                    </svg>
                </button>
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
                            <img
                                v-if="result.image"
                                :src="result.image"
                                :alt="`Retrato de ${result.name}`"
                                class="h-full w-full object-cover object-top"
                            />
                            <PortraitFallback v-else :name="result.name" owned />

                            <div class="absolute inset-0 bg-gradient-to-t from-night-950 via-night-950/25 to-transparent" />

                            <div class="absolute inset-x-2 top-2 flex items-start justify-between gap-1">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[8px] font-bold uppercase tracking-[0.15em] backdrop-blur"
                                    :style="{
                                        color: rarityMeta(result.rarity).color,
                                        backgroundColor: rarityMeta(result.rarity).soft,
                                        border: `1px solid ${rarityMeta(result.rarity).border}`,
                                    }"
                                >
                                    {{ rarityMeta(result.rarity).label }}
                                </span>
                                <RarityStars :rarity="result.rarity" />
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

                        <div class="flex items-center justify-between gap-2 border-t border-white/[0.06] px-2.5 py-1.5 text-[9px]">
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

            <div class="relative border-t border-white/10 bg-night-950/80 px-4 py-4 sm:px-8">
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
                            :class="{ 'opacity-50': !canAgain }"
                            :disabled="busy || !canAgain"
                            :title="canAgain ? `Repetir la tirada por ${formatCoins(price)}` : 'No te alcanza el saldo para repetir'"
                            @click="again"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 11a8 8 0 10-2.34 5.66M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>Tirar de vuelta ×{{ results.length }}</span>
                            <span class="text-[11px] tabular-nums text-bone-400">{{ formatCoins(price) }}</span>
                        </button>
                        <button type="button" class="btn-gold w-full px-8 py-2.5 text-sm sm:w-auto" @click="close">
                            Continuar
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
    background: linear-gradient(100deg, transparent 25%, rgba(255, 240, 190, 0.28) 50%, transparent 75%);
    transform: translateX(-140%) skewX(-18deg);
    animation: shine-sweep 2.8s ease-in-out 0.9s infinite;
    pointer-events: none;
    mix-blend-mode: screen;
}
</style>
