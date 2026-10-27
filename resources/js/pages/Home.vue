<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import CoinChip from '../Components/CoinChip.vue';
import PortraitFallback from '../Components/PortraitFallback.vue';
import PullReveal from '../Components/PullReveal.vue';
import RarityStars from '../Components/RarityStars.vue';
import { RARITY_ORDER, formatCoins, formatGuaranies, formatNumber, rarityMeta } from '../lib/rarity';

defineProps({
    recent: { type: Array, default: () => [] },
    showcase: { type: Array, default: () => [] },
});

const page = usePage();
const player = computed(() => page.props.player);
const gacha = computed(() => page.props.gacha);
const errors = computed(() => page.props.errors ?? {});

const pulling = ref(null);
const showReveal = ref(false);

watch(
    () => page.props.lastPull,
    (pull) => {
        showReveal.value = Boolean(pull);
    },
    { immediate: true },
);

function pull(count) {
    if (pulling.value) {
        return;
    }

    pulling.value = count;

    router.post(
        '/tirar',
        { count },
        {
            preserveScroll: true,
            onFinish: () => {
                pulling.value = null;
            },
        },
    );
}

const bonusForm = useForm({});
const now = ref(Date.now());
const ticker = setInterval(() => {
    now.value = Date.now();
}, 1000);

onBeforeUnmount(() => clearInterval(ticker));

const bonusSeconds = computed(() => {
    if (!player.value?.bonus_ready_at) {
        return 0;
    }

    return Math.max(0, Math.floor((new Date(player.value.bonus_ready_at).getTime() - now.value) / 1000));
});

const bonusLabel = computed(() => {
    if (bonusSeconds.value <= 0) {
        return 'Reclamar bonificación';
    }

    const hours = Math.floor(bonusSeconds.value / 3600);
    const minutes = Math.floor((bonusSeconds.value % 3600) / 60);
    const seconds = bonusSeconds.value % 60;

    if (hours > 0) {
        return `Disponible en ${hours}h ${String(minutes).padStart(2, '0')}m`;
    }

    return `Disponible en ${minutes}:${String(seconds).padStart(2, '0')}`;
});

function claimBonus() {
    if (bonusSeconds.value > 0 || bonusForm.processing) {
        return;
    }

    bonusForm.post('/bonificacion', { preserveScroll: true });
}

const canSingle = computed(() => (player.value?.coins ?? 0) >= (gacha.value?.price_single ?? 0));
const canMulti = computed(() => (player.value?.coins ?? 0) >= (gacha.value?.price_multi ?? 0));
const nextLegendary = computed(() => Math.max(1, (player.value?.pity_limit ?? 12) - (player.value?.pity ?? 0)));

const revealCount = computed(() => ((page.props.lastPull?.results?.length ?? 1) > 1 ? (gacha.value?.multi_pull ?? 10) : 1));
const revealPrice = computed(() => (revealCount.value === 1 ? gacha.value?.price_single : gacha.value?.price_multi) ?? 0);
const canAgain = computed(() => (player.value?.coins ?? 0) >= revealPrice.value);

function pullAgain() {
    if (pulling.value || !canAgain.value) {
        return;
    }

    const count = revealCount.value;
    showReveal.value = false;
    pull(count);
}

const rates = computed(() => RARITY_ORDER.map((rarity) => ({
    rarity,
    meta: rarityMeta(rarity),
    rate: gacha.value?.rates?.[rarity] ?? 0,
    ...player.value?.stats?.by_rarity?.[rarity],
})));
</script>

<template>
    <AppLayout>
        <Head title="Gachapón" />

        <PullReveal
            v-if="showReveal && page.props.lastPull"
            :pull="page.props.lastPull"
            :price="revealPrice"
            :can-again="canAgain"
            :busy="pulling !== null"
            @again="pullAgain"
            @close="showReveal = false"
        />

        <div class="relative overflow-hidden">
            <span class="pointer-events-none absolute left-[8%] top-24 hidden text-gold-300/30 animate-sparkle lg:block">✦</span>
            <span class="pointer-events-none absolute right-[12%] top-52 hidden text-gold-300/30 animate-sparkle lg:block" style="animation-delay: 1.1s">✦</span>
            <span class="pointer-events-none absolute left-[46%] top-10 hidden text-gold-300/20 animate-sparkle lg:block" style="animation-delay: 0.6s">✦</span>

            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 pb-12 pt-10 sm:px-6 lg:grid-cols-[1fr_360px] lg:gap-14 lg:pt-16">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-gold-400/25 bg-gold-400/[0.07] px-3 py-1.5">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-gold-300 opacity-70" />
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-gold-300" />
                        </span>
                        <span class="text-[10px] font-semibold uppercase tracking-[0.28em] text-gold-300">Edición Patria · {{ player?.stats?.total }} figuras</span>
                    </div>

                    <h1 class="mt-5 font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl xl:text-[3.6rem]">
                        <span class="gold-text">El gachapón de la</span><br />
                        <span class="text-bone-50">historia paraguaya</span>
                    </h1>

                    <p class="mt-5 max-w-lg text-sm leading-relaxed text-bone-300 sm:text-[15px]">
                        Invocá a los próceres, artistas, presidentes y leyendas que construyeron el Paraguay.
                        Cada tirada revela un retrato y su historia real, tomados de Wikipedia.
                    </p>

                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="btn-gold group px-7 py-3.5 text-base"
                            :disabled="pulling !== null || !canMulti"
                            @click="pull(gacha.multi_pull)"
                        >
                            <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 group-hover:translate-x-full" />
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M4 8.5V6a2 2 0 012-2h12a2 2 0 012 2v2.5M4 8.5v9a2 2 0 002 2h12a2 2 0 002-2v-9M4 8.5h16M9 12h6M9 15.5h6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>Tirar ×{{ gacha.multi_pull }}</span>
                            <span class="rounded-md bg-night-950/15 px-2 py-0.5 text-[11px] font-bold tabular-nums">{{ formatCoins(gacha.price_multi) }}</span>
                        </button>

                        <button
                            type="button"
                            class="btn-ghost px-5 py-3.5 text-sm"
                            :disabled="pulling !== null || !canSingle"
                            @click="pull(1)"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7">
                                <circle cx="12" cy="12" r="8" /><path d="M12 8v8M8 12h8" stroke-linecap="round" />
                            </svg>
                            <span>{{ pulling === 1 ? 'Invocando…' : 'Tirar ×1' }}</span>
                            <span class="text-[11px] tabular-nums text-bone-400">{{ formatCoins(gacha.price_single) }}</span>
                        </button>
                    </div>

                    <p class="mt-3 max-w-xl text-[10px] leading-relaxed text-bone-400">
                        <span class="font-semibold text-gold-300/90">1 SM = {{ formatGuaranies(gacha.salary.amount) }}</span>
                        · Sueldo mínimo legal vigente desde el {{ gacha.salary.since }} ({{ gacha.salary.decree }}).
                        Jornal diario: {{ formatGuaranies(gacha.salary.daily) }}.
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <button
                            type="button"
                            class="group inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] transition"
                            :class="bonusSeconds <= 0 ? 'text-gold-300 hover:text-gold-200' : 'cursor-default text-bone-400'"
                            :disabled="bonusSeconds > 0 || bonusForm.processing"
                            @click="claimBonus"
                        >
                            <span class="grid h-6 w-6 place-items-center rounded-full border border-current/30 bg-white/[0.04]">
                                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                                </svg>
                            </span>
                            {{ bonusForm.processing ? 'Reclamando…' : bonusLabel }}
                            <span v-if="bonusSeconds <= 0" class="text-gold-400/80">+{{ formatCoins(player.bonus_coins) }}</span>
                        </button>

                        <span v-if="errors.coins" class="text-[11px] font-medium text-[#ff9c8f]">{{ errors.coins }}</span>
                        <span v-else-if="errors.bonus" class="text-[11px] font-medium text-[#ff9c8f]">{{ errors.bonus }}</span>
                    </div>

                    <div class="mt-8 grid max-w-lg grid-cols-3 gap-3">
                        <div class="panel px-3.5 py-3">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-bone-400">Colección</p>
                            <p class="mt-1 font-display text-lg text-bone-50">
                                {{ player?.stats?.owned }}<span class="text-xs text-bone-400">/{{ player?.stats?.total }}</span>
                            </p>
                        </div>
                        <div class="panel px-3.5 py-3">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-bone-400">Tiradas</p>
                            <p class="mt-1 font-display text-lg text-bone-50">{{ formatNumber(player?.total_pulls) }}</p>
                        </div>
                        <div class="panel px-3.5 py-3">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-bone-400">Legendarios</p>
                            <p class="mt-1 font-display text-lg text-gold-300">
                                {{ player?.stats?.by_rarity?.[3]?.owned }}<span class="text-xs text-bone-400">/{{ player?.stats?.by_rarity?.[3]?.total }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 max-w-lg">
                        <div class="flex items-center justify-between text-[10px] text-bone-400">
                            <span>Progreso del álbum</span>
                            <span class="tabular-nums text-bone-300">{{ player?.stats?.completion }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-gold-600 via-gold-400 to-gold-200 transition-all duration-700"
                                :style="{ width: `${player?.stats?.completion}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div class="machine-shell">
                        <div class="machine-glow" />

                        <div class="machine" :class="{ 'machine-active': pulling !== null }">
                            <div class="dome">
                                <span class="capsule cap-1">★</span>
                                <span class="capsule cap-2">✦</span>
                                <span class="capsule cap-3">●</span>
                                <span class="capsule cap-4">★</span>
                                <span class="capsule cap-5">◆</span>
                                <div class="dome-shine" />
                            </div>

                            <div class="machine-base">
                                <div class="coin-slot" />
                                <div class="machine-plate">
                                    <span>GACHA</span>
                                    <strong>GUARANÍ</strong>
                                </div>
                                <div class="knob" :class="{ 'knob-turn': pulling !== null }">
                                    <span class="knob-star">★</span>
                                </div>
                                <div class="tray" />
                            </div>

                            <div class="lever" :class="{ 'lever-pull': pulling !== null }">
                                <span class="lever-channel" />
                                <span class="lever-handle">
                                    <span class="lever-arm" />
                                    <span class="lever-ball" />
                                </span>
                            </div>
                        </div>

                        <p class="mt-6 text-center text-[10px] uppercase tracking-[0.32em] text-bone-400">
                            {{ pulling !== null ? 'Invocando al pasado…' : 'Tirá de la palanca' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="divider mx-auto max-w-6xl" />

        <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <div class="grid gap-4 lg:grid-cols-[1.1fr_1fr_1fr]">
                <div class="panel paper-noise p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="eyebrow">Probabilidades</h2>
                        <span class="text-[10px] text-bone-400">por tirada</span>
                    </div>
                    <div class="mt-4 space-y-3.5">
                        <div v-for="rate in rates" :key="rate.rarity">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="flex items-center gap-1.5 font-semibold" :style="{ color: rate.meta.color }">
                                    <RarityStars :rarity="rate.rarity" />
                                    {{ rate.meta.label }}
                                </span>
                                <span class="tabular-nums text-bone-300">{{ rate.rate }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :style="{ width: `${rate.rate}%`, background: rate.meta.gradient }"
                                />
                            </div>
                            <p class="mt-1 text-[9px] text-bone-400">
                                {{ rate.owned }}/{{ rate.total }} descubiertos
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between rounded-lg border border-white/[0.07] bg-white/[0.02] px-3 py-2">
                        <span class="text-[10px] uppercase tracking-wider text-bone-400">Pity</span>
                        <div class="flex items-center gap-1">
                            <span
                                v-for="dot in player?.pity_limit ?? 12"
                                :key="dot"
                                class="h-1.5 w-1.5 rounded-full"
                                :class="dot <= (player?.pity ?? 0) ? 'bg-gold-400' : 'bg-white/15'"
                            />
                        </div>
                        <span class="text-[10px] font-medium text-gold-300">{{ nextLegendary }} restantes</span>
                    </div>
                </div>

                <div class="panel p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="eyebrow">Tu vitrina</h2>
                        <Link href="/coleccion" class="text-[10px] font-semibold uppercase tracking-wider text-gold-300 hover:text-gold-200">
                            Ver todo
                        </Link>
                    </div>

                    <div v-if="showcase.length" class="mt-4 grid grid-cols-3 gap-2">
                        <div
                            v-for="character in showcase"
                            :key="character.id"
                            class="relative aspect-[3/4] overflow-hidden rounded-lg border bg-night-850"
                            :style="{ borderColor: rarityMeta(character.rarity).border }"
                        >
                            <img
                                v-if="character.image"
                                :src="character.image"
                                :alt="character.name"
                                class="h-full w-full object-cover object-top"
                            />
                            <PortraitFallback v-else :name="character.name" owned />
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-night-950 to-transparent px-1.5 pb-1 pt-4">
                                <p class="truncate text-[9px] font-semibold text-bone-100">{{ character.name }}</p>
                            </div>
                            <span
                                class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full"
                                :style="{ backgroundColor: rarityMeta(character.rarity).color }"
                            />
                        </div>
                    </div>

                    <div v-else class="mt-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-white/12 px-4 py-9 text-center">
                        <span class="grid h-10 w-10 place-items-center rounded-full border border-gold-400/30 bg-gold-400/10 font-display text-gold-300">?</span>
                        <p class="mt-3 text-xs font-semibold text-bone-200">Tu vitrina está vacía</p>
                        <p class="mt-1 text-[10px] leading-relaxed text-bone-400">Tu primera tirada ya puede revelar a un prócer.</p>
                    </div>

                    <div class="mt-4">
                        <CoinChip :coins="player?.coins" />
                    </div>
                </div>

                <div class="panel p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="eyebrow">Últimas tiradas</h2>
                        <span class="text-[10px] tabular-nums text-bone-400">{{ player?.total_pulls }} en total</span>
                    </div>

                    <ul v-if="recent.length" class="mt-4 space-y-2">
                        <li v-for="item in recent.slice(0, 4)" :key="item.id" class="flex items-center gap-2.5">
                            <span
                                class="relative h-9 w-9 shrink-0 overflow-hidden rounded-full border"
                                :style="{ borderColor: rarityMeta(item.rarity).border }"
                            >
                                <img v-if="item.image" :src="item.image" :alt="item.name" class="h-full w-full object-cover object-top" />
                                <PortraitFallback v-else :name="item.name" owned />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[11px] font-semibold text-bone-100">{{ item.name }}</span>
                                <span class="block text-[9px] text-bone-400">{{ item.at }}</span>
                            </span>
                            <RarityStars :rarity="item.rarity" />
                        </li>
                    </ul>

                    <p v-else class="mt-6 text-center text-[11px] leading-relaxed text-bone-400">
                        Todavía no hay tiradas.<br />La historia te está esperando.
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 pb-14 sm:px-6">
            <div class="relative overflow-hidden rounded-2xl border border-gold-400/20 bg-gradient-to-r from-gold-500/[0.12] via-night-900 to-flag-blue/20 px-6 py-7 sm:px-9">
                <div class="paper-noise absolute inset-0 opacity-40" />
                <div class="relative flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="eyebrow">{{ player?.stats?.total }} sellos · 3 rarezas</p>
                        <h2 class="mt-1.5 font-display text-xl text-bone-50 sm:text-2xl">¿Ya viste tu colección completa?</h2>
                        <p class="mt-1 text-xs text-bone-300">Los retratos se revelan a medida que los descubrís.</p>
                    </div>
                    <Link href="/coleccion" class="btn-ghost px-6 py-3 text-sm">
                        Abrir el álbum
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.machine-shell {
    position: relative;
    width: 100%;
}

.machine-glow {
    position: absolute;
    inset: 12% 3% 6%;
    border-radius: 999px;
    background: radial-gradient(closest-side, rgba(238, 194, 95, 0.3), transparent 72%);
    filter: blur(34px);
}

.machine {
    position: relative;
    width: 300px;
    height: 432px;
    margin: 0 auto;
    filter: drop-shadow(0 40px 60px rgba(0, 0, 0, 0.65));
}

.dome {
    position: absolute;
    left: 16px;
    right: 16px;
    top: 4px;
    height: 218px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.26);
    border-radius: 50% 50% 24px 24px / 58% 58% 16px 16px;
    background:
        radial-gradient(120% 90% at 28% 10%, rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0.06) 38%, rgba(255, 255, 255, 0.015) 62%),
        linear-gradient(160deg, rgba(150, 205, 245, 0.22), rgba(8, 18, 28, 0.62) 72%);
    box-shadow:
        inset 0 20px 34px rgba(255, 255, 255, 0.14),
        inset 0 -30px 46px rgba(0, 0, 0, 0.62),
        0 14px 40px rgba(0, 0, 0, 0.45);
}

.dome::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.14), transparent 22%, transparent 78%, rgba(255, 255, 255, 0.08));
    pointer-events: none;
}

.dome-shine {
    position: absolute;
    left: 12%;
    top: 7%;
    width: 34%;
    height: 40%;
    border-radius: 50%;
    background: linear-gradient(140deg, rgba(255, 255, 255, 0.6), transparent 72%);
    filter: blur(7px);
}

.capsule {
    position: absolute;
    display: grid;
    width: 46px;
    height: 46px;
    place-items: center;
    border-radius: 999px;
    font-size: 13px;
    color: rgba(5, 8, 11, 0.5);
    box-shadow:
        inset -7px -10px 14px rgba(0, 0, 0, 0.36),
        inset 7px 9px 13px rgba(255, 255, 255, 0.55),
        0 10px 16px rgba(0, 0, 0, 0.35);
    animation: float-soft 6s ease-in-out infinite;
}

.cap-1 {
    left: 28px;
    bottom: 26px;
    background: radial-gradient(circle at 32% 26%, #fff6d8, #eec25f 46%, #9c6d15);
}

.cap-2 {
    left: 96px;
    bottom: 66px;
    width: 40px;
    height: 40px;
    background: radial-gradient(circle at 32% 26%, #d6f3ff, #4ec3ff 48%, #116a9c);
    animation-delay: -1.4s;
}

.cap-3 {
    right: 34px;
    bottom: 34px;
    background: radial-gradient(circle at 32% 26%, #ffd9d4, #e04534 48%, #8c150d);
    animation-delay: -2.6s;
}

.cap-4 {
    right: 74px;
    bottom: 92px;
    width: 38px;
    height: 38px;
    background: radial-gradient(circle at 32% 26%, #ffffff, #ded3ba 48%, #8d8069);
    animation-delay: -3.4s;
}

.cap-5 {
    left: 46px;
    bottom: 112px;
    width: 34px;
    height: 34px;
    background: radial-gradient(circle at 32% 26%, #d8ffe9, #65d6a4 48%, #176a4b);
    animation-delay: -4.2s;
}

.machine-base {
    position: absolute;
    inset: auto 0 0 0;
    height: 228px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 24px 24px 20px 20px;
    background: linear-gradient(180deg, #d43a2b 0%, #b3261c 26%, #821810 70%, #4c0c08 100%);
    box-shadow:
        inset 0 2px 0 rgba(255, 255, 255, 0.24),
        inset 0 -26px 44px rgba(0, 0, 0, 0.55),
        0 30px 50px -20px rgba(0, 0, 0, 0.85);
}

.machine-base::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.35);
    background: linear-gradient(180deg, #fbe8b4, #d8a43c 55%, #966a1a);
}

.machine-base::after {
    content: '';
    position: absolute;
    top: 15px;
    bottom: 0;
    left: 7%;
    width: 9%;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.2), transparent);
}

.machine-plate {
    position: absolute;
    top: 32px;
    left: 50%;
    width: 192px;
    transform: translateX(-50%);
    padding: 8px 0 7px;
    border: 1px solid rgba(255, 235, 190, 0.45);
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(18, 7, 5, 0.94), rgba(42, 12, 8, 0.94));
    text-align: center;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16), 0 6px 16px rgba(0, 0, 0, 0.4);
}

.machine-plate span {
    display: block;
    font-size: 7px;
    letter-spacing: 0.45em;
    color: #d8a43c;
}

.machine-plate strong {
    display: block;
    margin-top: 1px;
    font-family: var(--font-display);
    font-size: 17px;
    letter-spacing: 0.15em;
    color: #f6d98d;
    text-shadow: 0 0 18px rgba(238, 194, 95, 0.45);
}

.knob {
    position: absolute;
    top: 96px;
    left: 50%;
    display: grid;
    width: 74px;
    height: 74px;
    place-items: center;
    transform: translateX(-50%);
    border: 3px solid #5d3f0e;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 26%, #fff4cd, #eec25f 44%, #b47f26 72%, #7a5312);
    box-shadow:
        0 10px 20px rgba(0, 0, 0, 0.5),
        inset 0 -4px 8px rgba(0, 0, 0, 0.35),
        inset 0 4px 8px rgba(255, 255, 255, 0.45);
    transition: transform 0.55s cubic-bezier(0.2, 1, 0.3, 1);
}

.knob::before {
    content: '';
    position: absolute;
    inset: 9px;
    border: 1px dashed rgba(0, 0, 0, 0.28);
    border-radius: 50%;
}

.knob-star {
    font-size: 24px;
    color: #6b4a0d;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.45);
}

.knob-turn {
    transform: translateX(-50%) rotate(220deg);
}

.coin-slot {
    position: absolute;
    bottom: 66px;
    left: 22px;
    width: 26px;
    height: 44px;
    border: 1px solid rgba(238, 194, 95, 0.35);
    border-radius: 6px;
    background: linear-gradient(180deg, #2a0b08, #140505);
    box-shadow: inset 0 0 10px #000;
}

.coin-slot::after {
    content: '';
    position: absolute;
    top: 9px;
    bottom: 9px;
    left: 50%;
    width: 2px;
    transform: translateX(-50%);
    border-radius: 2px;
    background: rgba(238, 194, 95, 0.55);
}

.tray {
    position: absolute;
    bottom: 16px;
    left: 50%;
    width: 130px;
    height: 28px;
    transform: translateX(-50%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    background: linear-gradient(180deg, #120504, #290b08);
    box-shadow: inset 0 6px 14px rgba(0, 0, 0, 0.9), 0 1px 0 rgba(255, 255, 255, 0.14);
}

.lever {
    position: absolute;
    right: -28px;
    top: 178px;
    width: 56px;
    height: 92px;
}

.lever-channel {
    position: absolute;
    top: 6px;
    bottom: 0;
    left: 20px;
    width: 14px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 999px;
    background: linear-gradient(90deg, #1b0a08, #3d1510);
    box-shadow: inset 0 0 8px #000;
}

.lever-handle {
    position: absolute;
    top: 8px;
    left: 23px;
    transform-origin: 3px 0;
    transition: transform 0.4s cubic-bezier(0.2, 1, 0.3, 1);
}

.lever-arm {
    display: block;
    width: 6px;
    height: 46px;
    border-radius: 4px;
    background: linear-gradient(90deg, #9c7420, #f6d98d, #9c7420);
}

.lever-ball {
    position: absolute;
    top: -16px;
    left: -14px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, #ff9b8d, #d52b1e 52%, #7b120c);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.5), inset 0 3px 6px rgba(255, 255, 255, 0.35);
}

.lever-pull .lever-handle {
    transform: rotate(-34deg);
}

.machine-active .dome {
    animation: machine-shake 0.55s ease-in-out infinite;
}

@keyframes machine-shake {
    0%, 100% { transform: translate(0, 0) rotate(0); }
    25% { transform: translate(-2px, 1px) rotate(-0.4deg); }
    75% { transform: translate(2px, -1px) rotate(0.4deg); }
}
</style>
