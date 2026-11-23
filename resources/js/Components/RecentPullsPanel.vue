<script setup>
/**
 * Historial breve de tiradas de la portada.
 *
 * La página pasa solo los últimos cuatro eventos; este componente decide cómo
 * mostrarlos y cuándo usar el estado vacío. Los retratos pasan por el mismo
 * componente con recuperación ante imágenes rotas que el resto de la interfaz.
 */
import CharacterPortrait from './CharacterPortrait.vue';
import EmptyState from './EmptyState.vue';
import PanelHeader from './PanelHeader.vue';
import RarityStars from './RarityStars.vue';
import { rarityMeta } from '../lib/rarity';

defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
});
</script>

<template>
    <div class="panel p-5">
        <PanelHeader title="Últimas tiradas" :hint="`${total} en total`" />

        <ul v-if="items.length" class="mt-4 space-y-2">
            <li v-for="item in items" :key="item.id" class="flex items-center gap-2.5">
                <span
                    class="relative h-9 w-9 shrink-0 overflow-hidden rounded-full border"
                    :style="{ borderColor: rarityMeta(item.rarity).border }"
                >
                    <CharacterPortrait :image="item.image" :name="item.name" owned />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[11px] font-semibold text-bone-100">{{ item.name }}</span>
                    <span class="block text-[9px] text-bone-400">{{ item.at }}</span>
                </span>
                <RarityStars :rarity="item.rarity" />
            </li>
        </ul>

        <EmptyState
            v-else
            class="mt-4"
            icon="✦"
            title="Todavía no hay tiradas"
            description="La historia te está esperando."
        />
    </div>
</template>
