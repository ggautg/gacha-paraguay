<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { THEMES, applyTheme, getStoredTheme } from '../lib/themes';

const open = ref(false);
const current = ref(getStoredTheme());
const root = ref(null);

const active = computed(() => THEMES.find((theme) => theme.id === current.value) ?? THEMES[0]);

function toggle() {
    open.value = !open.value;
}

function select(id) {
    current.value = applyTheme(id);
    open.value = false;
}

function onDocumentPointerDown(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="fixed bottom-5 right-4 z-50 sm:right-6">
        <Transition name="theme-pop">
            <div
                v-if="open"
                class="absolute bottom-full right-0 mb-3 w-[19rem] overflow-hidden rounded-2xl border border-white/10 bg-night-900/95 shadow-[0_28px_80px_-30px_rgba(0,0,0,0.95)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between gap-3 border-b border-white/[0.07] px-4 py-3">
                    <div>
                        <p class="eyebrow">Paleta</p>
                        <p class="mt-0.5 font-display text-sm font-semibold text-bone-50">Temas de la casa</p>
                    </div>
                    <button
                        type="button"
                        class="grid h-7 w-7 place-items-center rounded-full border border-white/10 text-bone-300 transition hover:border-gold-400/50 hover:text-gold-300"
                        aria-label="Cerrar selector de temas"
                        @click="open = false"
                    >
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="grid max-h-[60vh] grid-cols-2 gap-1.5 overflow-y-auto p-3">
                    <button
                        v-for="theme in THEMES"
                        :key="theme.id"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border px-2 py-2 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-400/60"
                        :class="
                            current === theme.id
                                ? 'border-gold-400/60 bg-gold-400/10'
                                : 'border-white/10 bg-white/[0.03] hover:border-white/25 hover:bg-white/[0.06]'
                        "
                        :title="theme.description"
                        :aria-pressed="current === theme.id"
                        @click="select(theme.id)"
                    >
                        <span class="flex shrink-0 -space-x-1">
                            <span
                                v-for="color in theme.colors"
                                :key="color"
                                class="h-3.5 w-3.5 rounded-full ring-1 ring-black/30"
                                :style="{ backgroundColor: color }"
                            />
                        </span>
                        <span class="min-w-0 flex-1 truncate text-[11px] font-semibold leading-tight" :class="current === theme.id ? 'text-gold-200' : 'text-bone-200'">
                            {{ theme.name }}
                        </span>
                        <svg
                            v-if="current === theme.id"
                            viewBox="0 0 24 24"
                            class="h-3.5 w-3.5 shrink-0 text-gold-300"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.6"
                            aria-hidden="true"
                        >
                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <p class="border-t border-white/[0.07] px-4 py-2 text-[10px] leading-relaxed text-bone-400">
                    Se guarda en este navegador para tu próxima visita.
                </p>
            </div>
        </Transition>

        <button
            type="button"
            class="group relative grid h-12 w-12 place-items-center rounded-full border border-gold-400/40 bg-night-900/90 text-gold-300 shadow-[0_18px_40px_-18px_rgba(0,0,0,0.9)] backdrop-blur transition hover:-translate-y-0.5 hover:border-gold-400/70 hover:text-gold-200"
            :aria-expanded="open"
            aria-haspopup="true"
            :aria-label="`Elegir tema · actual: ${active.name}`"
            :title="`Tema actual: ${active.name}`"
            @click="toggle"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path
                    d="M12 3a9 9 0 100 18h1.6a2.2 2.2 0 001.7-3.6 1.6 1.6 0 011.2-2.6H18a3 3 0 003-3A9 9 0 0012 3z"
                    stroke-linejoin="round"
                />
                <circle cx="8.1" cy="10.2" r="1.05" fill="currentColor" stroke="none" />
                <circle cx="11.6" cy="7.6" r="1.05" fill="currentColor" stroke="none" />
                <circle cx="15.4" cy="9" r="1.05" fill="currentColor" stroke="none" />
            </svg>
            <span
                class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-night-900"
                :style="{ backgroundColor: active.colors[1] }"
            />
        </button>
    </div>
</template>

<style scoped>
.theme-pop-enter-active,
.theme-pop-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.theme-pop-enter-from,
.theme-pop-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.97);
}
</style>
