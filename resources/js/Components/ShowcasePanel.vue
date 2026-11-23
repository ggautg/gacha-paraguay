<script setup>
/**
 * Vitrina de las últimas figuras descubiertas y saldo del jugador.
 *
 * Extraída de Home.vue para que la portada solo coordine datos. La tarjeta
 * vuelve a usar toCard() del servidor, así que las figuras sin descubrir nunca
 * llegan a este componente y no hay que repetir la regla anti-spoiler.
 */
import { Link } from '@inertiajs/vue3';
import CharacterPortrait from './CharacterPortrait.vue';
import CoinChip from './CoinChip.vue';
import EmptyState from './EmptyState.vue';
import PanelHeader from './PanelHeader.vue';
import { rarityMeta } from '../lib/rarity';

defineProps({
    characters: { type: Array, default: () => [] },
    coins: { type: Number, default: 0 },
});
</script>

<template>
    <div class="panel p-5">
        <PanelHeader title="Tu vitrina">
            <template #action>
                <Link href="/coleccion" class="text-[10px] font-semibold uppercase tracking-wider text-gold-300 hover:text-gold-200">
                    Ver todo
                </Link>
            </template>
        </PanelHeader>

        <div v-if="characters.length" class="mt-4 grid grid-cols-3 gap-2">
            <div
                v-for="character in characters"
                :key="character.id"
                class="relative aspect-[3/4] overflow-hidden rounded-lg border bg-night-850"
                :style="{ borderColor: rarityMeta(character.rarity).border }"
            >
                <CharacterPortrait :image="character.image" :name="character.name" owned />
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-night-950 to-transparent px-1.5 pb-1 pt-4">
                    <p class="truncate text-[9px] font-semibold text-bone-100">{{ character.name }}</p>
                </div>
                <span
                    class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full"
                    :style="{ backgroundColor: rarityMeta(character.rarity).color }"
                />
            </div>
        </div>

        <EmptyState
            v-else
            class="mt-4"
            title="Tu vitrina está vacía"
            description="Tu primera tirada ya puede revelar a un prócer."
        />

        <div class="mt-4">
            <CoinChip :coins="coins" />
        </div>
    </div>
</template>
