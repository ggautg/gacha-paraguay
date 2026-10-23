<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CoinChip from '../Components/CoinChip.vue';

const page = usePage();
const player = computed(() => page.props.player);
const url = computed(() => page.url);

const toast = ref(null);
let toastTimer = null;

watch(
    () => page.props.flash?.message,
    (message) => {
        if (!message) {
            return;
        }

        toast.value = message;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.value = null;
        }, 4000);
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(toastTimer));

function isActive(href) {
    return href === '/' ? url.value === '/' : url.value.startsWith(href);
}
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <div class="h-[3px] w-full bg-gradient-to-r from-flag-red via-bone-100 to-flag-blue" />

        <header class="sticky top-0 z-40 border-b border-white/[0.08] bg-night-950/80 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                <Link href="/" class="group flex items-center gap-2.5">
                    <span class="relative grid h-10 w-10 place-items-center rounded-full bg-night-900 ring-1 ring-gold-400/40 transition group-hover:ring-gold-300/70">
                        <span class="absolute inset-[3px] rounded-full bg-gradient-to-b from-flag-red via-bone-100 to-flag-blue opacity-90" />
                        <span class="relative grid h-5 w-5 place-items-center rounded-full bg-night-950/95 font-display text-[10px] font-bold text-gold-300">
                            ★
                        </span>
                    </span>
                    <span class="leading-none">
                        <span class="block font-display text-base font-semibold tracking-wide text-bone-50 sm:text-lg">Gacha Guaraní</span>
                        <span class="mt-1 hidden text-[9px] uppercase tracking-[0.3em] text-gold-400/80 sm:block">Historia del Paraguay</span>
                    </span>
                </Link>

                <nav class="flex items-center gap-1 rounded-full border border-white/[0.08] bg-white/[0.03] p-1">
                    <Link
                        href="/"
                        class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition sm:px-4 sm:text-xs"
                        :class="isActive('/') ? 'bg-gold-400 text-night-950 shadow-[0_6px_20px_-8px_rgba(238,194,95,0.9)]' : 'text-bone-300 hover:text-gold-300'"
                    >
                        Máquina
                    </Link>
                    <Link
                        href="/coleccion"
                        class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition sm:px-4 sm:text-xs"
                        :class="isActive('/coleccion') ? 'bg-gold-400 text-night-950 shadow-[0_6px_20px_-8px_rgba(238,194,95,0.9)]' : 'text-bone-300 hover:text-gold-300'"
                    >
                        Colección
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <div v-if="player" class="hidden items-center gap-2 lg:flex">
                        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-gold-500 to-gold-300"
                                :style="{ width: `${player.stats.completion}%` }"
                            />
                        </div>
                        <span class="text-[10px] font-semibold tabular-nums text-bone-400">
                            {{ player.stats.owned }}/{{ player.stats.total }}
                        </span>
                    </div>
                    <CoinChip v-if="player" :coins="player.coins" compact />
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-white/[0.07] bg-night-950/60">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-6 text-center sm:flex-row sm:px-6 sm:text-left">
                <p class="text-[11px] leading-relaxed text-bone-400">
                    Retratos y biografías tomados de
                    <a href="https://es.wikipedia.org" target="_blank" rel="noopener noreferrer" class="font-medium text-bone-200 underline decoration-gold-500/40 underline-offset-2 hover:text-gold-300">
                        Wikipedia en español
                    </a>
                    · Contenido bajo licencia CC BY-SA.
                </p>
                <p class="text-[10px] uppercase tracking-[0.25em] text-bone-400/60">Hecho en Paraguay · Edición Patria</p>
            </div>
        </footer>

        <Transition name="toast">
            <div
                v-if="toast"
                class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-full border border-gold-400/40 bg-night-900/95 px-5 py-2.5 text-sm font-medium text-gold-200 shadow-[0_20px_60px_-20px_rgba(0,0,0,0.9)] backdrop-blur"
            >
                {{ toast }}
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translate(-50%, 12px);
}
</style>
