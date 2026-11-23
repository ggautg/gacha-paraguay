<script setup>
/**
 * Barra de filtros de la colección: rareza, búsqueda y solo descubiertos.
 *
 * Usa `v-model` en cada control en lugar de mutar props. Album.vue mantiene el
 * estado y aplica los filtros; aquí solo se presenta y se emite el cambio.
 * «Limpiar filtros» queda deshabilitado cuando no hay nada activo.
 */
defineProps({
    filters: { type: Array, default: () => [] },
    filter: { type: String, default: 'all' },
    search: { type: String, default: '' },
    onlyOwned: { type: Boolean, default: false },
    hasFilters: { type: Boolean, default: false },
});

const emit = defineEmits(['update:filter', 'update:search', 'update:onlyOwned', 'clear']);
</script>

<template>
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
                @click="emit('update:filter', item.key)"
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
                @click="emit('update:onlyOwned', !onlyOwned)"
            >
                Solo descubiertos
            </button>

            <label class="relative block w-full sm:w-64">
                <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-bone-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M16.5 16.5L21 21" stroke-linecap="round" />
                </svg>
                <input
                    :value="search"
                    type="search"
                    placeholder="Buscar por nombre, rol o época…"
                    aria-label="Buscar figuras por nombre, rol o época"
                    class="w-full rounded-full border line-soft surface-2 py-2 pl-9 pr-3 text-xs text-bone-100 placeholder:text-bone-400/70 focus:border-gold-400/50 focus:outline-none focus:ring-1 focus:ring-gold-400/40"
                    @input="emit('update:search', $event.target.value)"
                />
            </label>

            <button
                type="button"
                class="btn-ghost px-3 py-1.5 text-[11px]"
                :disabled="!hasFilters"
                @click="emit('clear')"
            >
                Limpiar
            </button>
        </div>
    </div>
</template>
