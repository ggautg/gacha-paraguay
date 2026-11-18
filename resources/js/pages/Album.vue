<script setup>
/**
 * Colección completa: filtros por rareza, búsqueda y grilla de figuras.
 *
 * Los filtros viven en el cliente porque el plantel es chico (84 figuras) y
 * así cambiar de rareza no provoca una navegación. `hasFilters` permite
 * deshabilitar «Limpiar filtros» cuando no hay nada para limpiar: un botón
 * que no hace nada es ruido visual. El anillo de progreso se dibuja con SVG
 * porque `stroke-dashoffset` da un movimiento suave sin librerías extra.
 */
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CharacterCard from '../Components/CharacterCard.vue';
import CharacterModal from '../Components/CharacterModal.vue';
import EmptyState from '../Components/EmptyState.vue';
import RarityStars from '../Components/RarityStars.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { RARITY_ORDER, rarityMeta } from '../lib/rarity';

const props = defineProps({
    characters: { type: Array, default: () => [] },
});

const page = usePage();
const player = computed(() => page.props.player);

const search = ref('');
const filter = ref('all');
const onlyOwned = ref(false);
const selected = ref(null);

const ownedCount = computed(() => props.characters.filter((character) => character.owned).length);
const completion = computed(() => (props.characters.length ? Math.round((ownedCount.value / props.characters.length) * 100) : 0));

const filters = computed(() => {
    const byRarity = player.value?.stats?.by_rarity ?? {};

    return [
        { key: 'all', label: 'Todas', owned: ownedCount.value, total: props.characters.length, rarity: 'all' },
        ...RARITY_ORDER.map((rarity) => ({
            key: String(rarity),
            label: rarityMeta(rarity).label,
            owned: byRarity[rarity]?.owned ?? 0,
            total: byRarity[rarity]?.total ?? 0,
            rarity,
            color: rarityMeta(rarity).color,
            border: rarityMeta(rarity).border,
        })),
    ];
});

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.characters.filter((character) => {
        if (filter.value !== 'all' && String(character.rarity) !== filter.value) {
            return false;
        }

        if (onlyOwned.value && !character.owned) {
            return false;
        }

        if (!term) {
            return true;
        }

        return `${character.name} ${character.role} ${character.category} ${character.era}`.toLowerCase().includes(term);
    });
});

const circumference = 2 * Math.PI * 46;
const dashOffset = computed(() => circumference - (completion.value / 100) * circumference);
const hasFilters = computed(() => search.value.trim() !== '' || filter.value !== 'all' || onlyOwned.value);

function clearFilters() {
    search.value = '';
    filter.value = 'all';
    onlyOwned.value = false;
}
</script>

<template>
    <AppLayout>
        <Head title="Colección" />

        <CharacterModal :character="selected" @close="selected = null" />

        <section class="mx-auto max-w-6xl px-4 pb-8 pt-10 sm:px-6 lg:pt-14">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="eyebrow">La colección de la patria</p>
                    <h1 class="mt-2 font-display text-3xl font-semibold text-bone-50 sm:text-4xl">Tu colección</h1>
                    <p class="mt-3 max-w-xl text-sm leading-relaxed text-bone-300">
                        {{ ownedCount }} de {{ characters.length }} figuras descubiertas. Cada retrato es una puerta a la historia del Paraguay.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span
                            v-for="item in filters.slice(1)"
                            :key="item.key"
                            class="chip"
                            :style="{ borderColor: item.border, color: item.color }"
                        >
                            <RarityStars :rarity="Number(item.key)" />
                            {{ item.owned }}/{{ item.total }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-5">
                    <div class="relative h-28 w-28 shrink-0">
                        <svg viewBox="0 0 110 110" class="h-full w-full -rotate-90">
                            <circle cx="55" cy="55" r="46" fill="none" style="stroke: var(--border-soft)" stroke-width="7" />
                            <circle
                                cx="55"
                                cy="55"
                                r="46"
                                fill="none"
                                stroke="url(#ringGradient)"
                                stroke-width="7"
                                stroke-linecap="round"
                                :stroke-dasharray="circumference"
                                :stroke-dashoffset="dashOffset"
                                class="transition-all duration-1000"
                            />
                            <defs>
                                <linearGradient id="ringGradient" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" style="stop-color: var(--accent-surface-3)" />
                                    <stop offset="55%" style="stop-color: var(--accent-surface-2)" />
                                    <stop offset="100%" style="stop-color: var(--accent-surface-1)" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="absolute inset-0 grid place-items-center text-center">
                            <div>
                                <p class="font-display text-2xl font-semibold text-bone-50">{{ completion }}%</p>
                                <p class="text-[9px] uppercase tracking-[0.2em] text-bone-400">completo</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="sticky top-16 z-30 border-y line-soft bg-night-950/85 py-3 backdrop-blur-xl">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="item in filters"
                        :key="item.key"
                        type="button"
                        class="filter-chip rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
                        :class="filter === item.key ? 'filter-chip-active' : 'line-soft surface-2 text-bone-300 hover:line-strong'"
                        :data-rarity="item.rarity"
                        :aria-pressed="filter === item.key"
                        @click="filter = item.key"
                    >
                        {{ item.label }}
                        <span class="ml-1 text-[9px] opacity-70">{{ item.owned }}/{{ item.total }}</span>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
                        :class="onlyOwned ? 'border-gold-400/50 bg-gold-400/15 text-gold-200' : 'line-soft surface-2 text-bone-400 hover:text-bone-200'"
                        :aria-pressed="onlyOwned"
                        @click="onlyOwned = !onlyOwned"
                    >
                        Solo descubiertos
                    </button>

                    <label class="relative block w-full sm:w-64">
                        <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-bone-400" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M16.5 16.5L21 21" stroke-linecap="round" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre, rol o época…"
                            aria-label="Buscar figuras por nombre, rol o época"
                            class="w-full rounded-full border line-soft surface-2 py-2 pl-9 pr-3 text-xs text-bone-100 placeholder:text-bone-400/70 focus:border-gold-400/50 focus:outline-none focus:ring-1 focus:ring-gold-400/40"
                        />
                    </label>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <div v-if="visible.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                <CharacterCard
                    v-for="character in visible"
                    :key="character.id"
                    :character="character"
                    @select="selected = $event"
                />
            </div>

            <EmptyState
                v-else
                title="No encontramos figuras"
                description="Probá con otro nombre, cambiá la rareza o desactivá el filtro de descubiertos."
            >
                <template #action>
                    <button type="button" class="btn-ghost px-4 py-2 text-xs" :disabled="!hasFilters" @click="clearFilters">
                        Limpiar filtros
                    </button>
                </template>
            </EmptyState>

            <p class="mt-8 text-center text-[10px] leading-relaxed text-bone-400/70">
                Los retratos sin imagen disponible en Wikipedia se muestran con una figura tipográfica.
            </p>
        </section>
    </AppLayout>
</template>
