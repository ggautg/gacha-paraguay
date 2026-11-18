<script setup>
/**
 * Tarjeta de una figura en la grilla de la colección.
 *
 * Antes replicaba el lector de imágenes y la pastilla de rareza que ya viven
 * en `CharacterPortrait` y `RarityBadge`. Ahora solo se encarga de la
 * presentación: marco, anti-spoiler, contador de copias y el evento `select`.
 * Dejar la recuperación de imágenes en un solo componente evita que una URL rota
 * se vea distinto en la tarjeta, el modal y el revelado.
 */
import { computed } from 'vue';
import { rarityMeta } from '../lib/rarity';
import CharacterPortrait from './CharacterPortrait.vue';
import RarityBadge from './RarityBadge.vue';

const props = defineProps({
    character: { type: Object, required: true },
});

defineEmits(['select']);

const meta = computed(() => rarityMeta(props.character.rarity));
</script>

<template>
    <button
        type="button"
        class="group relative w-full overflow-hidden rounded-2xl border bg-night-850 text-left transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_-30px_rgba(0,0,0,0.95)] focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-400/70"
        :style="{
            borderColor: character.owned ? meta.border : 'var(--border-soft)',
            boxShadow: character.owned ? `inset 0 1px 0 var(--border-soft), 0 20px 50px -38px ${meta.color}` : 'none',
        }"
        :aria-label="character.owned ? `Ver figura: ${character.name}` : `Ver figura sin descubrir`"
        @click="$emit('select', character)"
    >
        <div class="relative aspect-[3/4] overflow-hidden">
            <div class="h-full w-full transition duration-500 group-hover:scale-105">
                <CharacterPortrait
                    :image="character.image"
                    :name="character.name"
                    :owned="character.owned"
                    lazy
                />
            </div>

            <div
                v-if="!character.owned"
                class="absolute inset-0 bg-night-950/45 backdrop-blur-[1px]"
            />
            <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-night-950 via-night-950/70 to-transparent" />

            <div class="absolute inset-x-2.5 top-2.5 flex items-start justify-between gap-1">
                <RarityBadge :rarity="character.rarity" />
            </div>

            <span
                v-if="character.quantity > 1"
                class="absolute right-2.5 bottom-2.5 rounded-md border line-strong bg-night-950/80 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-bone-200 backdrop-blur"
            >
                ×{{ character.quantity }}
            </span>

            <div class="absolute inset-x-2.5 bottom-2.5">
                <p class="line-clamp-2 font-display text-[13px] font-semibold leading-tight text-bone-50 sm:text-sm">
                    {{ character.name }}
                </p>
                <p class="mt-0.5 line-clamp-1 text-[10px] text-bone-300/80">
                    {{ character.owned ? character.role : 'Todavía sin descubrir' }}
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2 border-t line-soft px-2.5 py-1.5">
            <span class="truncate text-[9px] font-semibold uppercase tracking-[0.16em] text-bone-400">
                {{ character.category }}
            </span>
            <span class="shrink-0 text-[9px] tabular-nums text-bone-400/70">{{ character.era }}</span>
        </div>
    </button>
</template>
