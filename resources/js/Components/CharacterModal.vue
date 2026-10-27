<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { rarityMeta } from '../lib/rarity';
import PortraitFallback from './PortraitFallback.vue';
import RarityStars from './RarityStars.vue';

const props = defineProps({
    character: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const visible = ref(false);
const meta = computed(() => (props.character ? rarityMeta(props.character.rarity) : rarityMeta(1)));
const linkLabel = computed(() => {
    const url = props.character?.wiki_url ?? '';

    if (!url) {
        return '';
    }

    if (url.includes('wikipedia.org')) {
        return 'Leer el artículo completo en Wikipedia';
    }

    try {
        return `Ver más en ${new URL(url).hostname.replace(/^www\./, '')}`;
    } catch {
        return 'Ver más información';
    }
});

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.character,
    (character) => {
        visible.value = Boolean(character);

        if (typeof document === 'undefined') {
            return;
        }

        document.body.style.overflow = character ? 'hidden' : '';

        if (character) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="visible && character" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-night-950/85 backdrop-blur-md" @click="$emit('close')" />

                <div
                    class="relative z-10 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl border bg-night-900 shadow-[0_40px_120px_-40px_rgba(0,0,0,1)] sm:rounded-3xl md:grid md:grid-cols-[300px_1fr]"
                    :style="{ borderColor: meta.border }"
                >
                    <button
                        type="button"
                        class="absolute right-3 top-3 z-20 grid h-9 w-9 place-items-center rounded-full border border-white/15 bg-night-950/70 text-bone-200 backdrop-blur transition hover:border-gold-400/60 hover:text-gold-300"
                        aria-label="Cerrar"
                        @click="$emit('close')"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>

                    <div class="relative h-64 shrink-0 overflow-hidden md:h-auto">
                        <img
                            v-if="character.image"
                            :src="character.image"
                            :alt="`Retrato de ${character.name}`"
                            class="h-full w-full object-cover object-top"
                        />
                        <PortraitFallback v-else :name="character.name" :owned="character.owned" />

                        <div class="absolute inset-0 bg-gradient-to-t from-night-900 via-transparent to-transparent md:bg-gradient-to-r" />
                        <div
                            class="absolute bottom-4 left-4 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.2em] backdrop-blur"
                            :style="{ color: meta.color, backgroundColor: meta.soft, border: `1px solid ${meta.border}` }"
                        >
                            {{ meta.label }} · {{ character.rarity }}★
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col overflow-y-auto p-5 sm:p-7">
                        <div class="flex items-center gap-2">
                            <RarityStars :rarity="character.rarity" size="lg" />
                            <span class="text-[10px] uppercase tracking-[0.22em] text-bone-400">{{ character.category }}</span>
                        </div>

                        <h2 class="mt-2 font-display text-2xl font-semibold leading-tight text-bone-50 sm:text-3xl">
                            {{ character.name }}
                        </h2>
                        <p class="mt-1 text-sm text-gold-300/90">{{ character.role }}</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="chip">{{ character.era }}</span>
                            <span class="chip">{{ character.quantity }} {{ character.quantity === 1 ? 'copia' : 'copias' }}</span>
                            <span v-if="!character.owned" class="chip border-rare-1/30 text-rare-1">No descubierto</span>
                        </div>

                        <div class="divider my-5" />

                        <template v-if="character.owned">
                            <p class="text-[13.5px] leading-relaxed text-bone-200/90">
                                {{ character.extract || 'Sin resumen disponible.' }}
                            </p>

                            <a
                                v-if="character.wiki_url"
                                :href="character.wiki_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-6 inline-flex items-center gap-2 text-xs font-semibold text-gold-300 transition hover:text-gold-200"
                            >
                                {{ linkLabel }}
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M7 17L17 7M9 7h8v8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </a>
                        </template>

                        <template v-else>
                            <div class="rounded-xl border border-dashed border-white/15 bg-white/[0.02] p-4 text-center">
                                <p class="font-display text-sm text-bone-200">Sello sin descubrir</p>
                                <p class="mt-1 text-xs leading-relaxed text-bone-400">
                                    Este personaje todavía no está en tu colección. Tirá de la máquina para revelar su retrato y su historia.
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.22s ease;
}

.modal-enter-active > div:last-child,
.modal-leave-active > div:last-child {
    transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from > div:last-child,
.modal-leave-to > div:last-child {
    transform: translateY(24px) scale(0.98);
}
</style>
