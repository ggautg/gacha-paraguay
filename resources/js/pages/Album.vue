<script setup>
/**
 * Colección completa: filtros, búsqueda y grilla de figuras.
 *
 * Los filtros viven en el cliente porque el plantel es chico (84 figuras) y
 * así cambiar de rareza no provoca una navegación. La presentación se reparte
 * entre CollectionFilters, CollectionProgress y CharacterCard; esta página
 * conserva el estado y la regla de filtrado.
 */
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import CharacterCard from '../Components/CharacterCard.vue';
import CharacterModal from '../Components/CharacterModal.vue';
import CollectionFilters from '../Components/CollectionFilters.vue';
import CollectionProgress from '../Components/CollectionProgress.vue';
import EmptyState from '../Components/EmptyState.vue';
import RarityStars from '../Components/RarityStars.vue';
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

                <CollectionProgress :value="completion" />
            </div>
        </section>

        <section class="sticky top-16 z-30 border-y line-soft bg-night-950/85 py-3 backdrop-blur-xl">
            <CollectionFilters
                v-model:filter="filter"
                v-model:search="search"
                v-model:only-owned="onlyOwned"
                :filters="filters"
                :has-filters="hasFilters"
                @clear="clearFilters"
            />
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
