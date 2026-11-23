<script setup>
/**
 * Pantalla de la máquina: coordina los datos y las acciones del juego.
 *
 * Toda la presentación vive en componentes (PullControls, GachaMachine,
 * BonusClaim, RarityRates, ShowcasePanel y RecentPullsPanel). Aquí solo quedan
 * el estado de la petición, el revelado y el armado de las probabilidades.
 * La petición se hace con router.post porque no hay campos que validar: solo
 * un contador. El revelado permanece abierto durante "Tirar de vuelta" para
 * evitar el parpadeo de cerrar y volver a abrir.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import BonusClaim from '../Components/BonusClaim.vue';
import GachaMachine from '../Components/GachaMachine.vue';
import PullControls from '../Components/PullControls.vue';
import PullReveal from '../Components/PullReveal.vue';
import RarityRates from '../Components/RarityRates.vue';
import RecentPullsPanel from '../Components/RecentPullsPanel.vue';
import ShowcasePanel from '../Components/ShowcasePanel.vue';
import StatCard from '../Components/StatCard.vue';
import ProgressBar from '../Components/ProgressBar.vue';
import { RARITY_ORDER, formatNumber, rarityMeta } from '../lib/rarity';

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

    router.post('/tirar', { count }, {
        preserveScroll: true,
        onFinish: () => {
            pulling.value = null;
        },
    });
}

const bonusForm = useForm({});

function claimBonus() {
    bonusForm.post('/bonificacion', { preserveScroll: true });
}

const revealCount = computed(() => ((page.props.lastPull?.results?.length ?? 1) > 1 ? (gacha.value?.multi_pull ?? 10) : 1));
const revealPrice = computed(() => (revealCount.value === 1 ? gacha.value?.price_single : gacha.value?.price_multi) ?? 0);
const canAgain = computed(() => (player.value?.coins ?? 0) >= revealPrice.value);

function pullAgain() {
    if (pulling.value || !canAgain.value) {
        return;
    }

    pull(revealCount.value);
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
                        Descubrí a los próceres, artistas, presidentes y leyendas que construyeron el Paraguay.
                        Cada tirada revela un retrato y su historia real, tomados de Wikipedia.
                    </p>

                    <PullControls
                        :gacha="gacha"
                        :coins="player?.coins ?? 0"
                        :pulling="pulling"
                        :error="errors.coins"
                        @pull="pull"
                    />

                    <BonusClaim
                        :ready-at="player?.bonus_ready_at ?? ''"
                        :coins="player?.bonus_coins ?? 0"
                        :processing="bonusForm.processing"
                        :error="errors.bonus"
                        @claim="claimBonus"
                    />

                    <div class="mt-8 grid max-w-lg grid-cols-3 gap-3">
                        <StatCard
                            label="Colección"
                            :value="player?.stats?.owned ?? 0"
                            :suffix="`/${player?.stats?.total ?? 0}`"
                        />
                        <StatCard label="Tiradas" :value="formatNumber(player?.total_pulls)" />
                        <StatCard
                            label="Legendarios"
                            :value="player?.stats?.by_rarity?.[3]?.owned ?? 0"
                            :suffix="`/${player?.stats?.by_rarity?.[3]?.total ?? 0}`"
                            accent
                        />
                    </div>

                    <div class="mt-3 max-w-lg">
                        <div class="flex items-center justify-between text-[10px] text-bone-400">
                            <span>Progreso de la colección</span>
                            <span class="tabular-nums text-bone-300">{{ player?.stats?.completion }}%</span>
                        </div>
                        <ProgressBar class="mt-1.5" :value="player?.stats?.completion ?? 0" />
                    </div>
                </div>

                <GachaMachine :active="pulling !== null" />
            </div>
        </div>

        <div class="divider mx-auto max-w-6xl" />

        <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <div class="grid gap-4 lg:grid-cols-[1.1fr_1fr_1fr]">
                <RarityRates
                    :rates="rates"
                    :pity="player?.pity ?? 0"
                    :pity-limit="player?.pity_limit ?? 12"
                />
                <ShowcasePanel :characters="showcase" :coins="player?.coins ?? 0" />
                <RecentPullsPanel :items="recent.slice(0, 4)" :total="player?.total_pulls ?? 0" />
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 pb-14 sm:px-6">
            <div class="relative overflow-hidden rounded-2xl border border-gold-400/20 bg-gradient-to-r from-gold-500/[0.12] via-night-900 to-flag-blue/20 px-6 py-7 sm:px-9">
                <div class="paper-noise absolute inset-0 opacity-40" />
                <div class="relative flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="eyebrow">{{ player?.stats?.total }} figuras · 3 rarezas</p>
                        <h2 class="mt-1.5 font-display text-xl text-bone-50 sm:text-2xl">¿Ya viste tu colección completa?</h2>
                        <p class="mt-1 text-xs text-bone-300">Los retratos se revelan a medida que los descubrís.</p>
                    </div>
                    <Link href="/coleccion" class="btn-ghost px-6 py-3 text-sm">
                        Abrir la colección
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
