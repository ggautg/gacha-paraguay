<script setup>
/**
 * Estructura común de las dos pantallas (máquina y colección).
 *
 * concentra la franja de bandera, la navegación, el saldo, el selector de
 * temas, el pie y los avisos. El toast es `role="status"` para que un lector
 * de pantalla anuncie la bonificación reclamada, pero también se puede cerrar
 * a mano: el temporizador de 4 segundos era demasiado corto para leerlo con
 * calma. Limpiar el timer al cerrar evita que un toast nuevo se apague antes
 * de tiempo.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CoinChip from '../Components/CoinChip.vue';
import CloseButton from '../Components/CloseButton.vue';
import ProgressBar from '../Components/ProgressBar.vue';
import ThemePicker from '../Components/ThemePicker.vue';

const page = usePage();
const player = computed(() => page.props.player);
const url = computed(() => page.url);

const toast = ref(null);
let toastTimer = null;

function dismissToast() {
    toast.value = null;
    clearTimeout(toastTimer);
}

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
        <div class="flag-stripe h-[3px] w-full" />

        <header class="sticky top-0 z-40 border-b line-soft bg-night-950/80 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                <Link href="/" class="group flex items-center gap-2.5" aria-label="Ir a la máquina">
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

                <nav class="flex items-center gap-1 rounded-full border line-soft surface-2 p-1" aria-label="Navegación principal">
                    <Link
                        href="/"
                        class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition sm:px-4 sm:text-xs"
                        :class="isActive('/') ? 'nav-glow bg-gold-400 text-night-950' : 'text-bone-300 hover:text-gold-300'"
                        :aria-current="isActive('/') ? 'page' : undefined"
                    >
                        Máquina
                    </Link>
                    <Link
                        href="/coleccion"
                        class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition sm:px-4 sm:text-xs"
                        :class="isActive('/coleccion') ? 'nav-glow bg-gold-400 text-night-950' : 'text-bone-300 hover:text-gold-300'"
                        :aria-current="isActive('/coleccion') ? 'page' : undefined"
                    >
                        Colección
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <div v-if="player" class="hidden items-center gap-2 lg:flex">
                        <div class="w-20">
                            <ProgressBar :value="player.stats.completion" />
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

        <footer class="border-t line-soft bg-night-950/60">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-6 text-center sm:flex-row sm:px-6 sm:text-left">
                <p class="text-[11px] leading-relaxed text-bone-400">
                    Retratos y biografías tomados de
                    <a href="https://es.wikipedia.org" target="_blank" rel="noopener noreferrer" class="font-medium text-bone-200 underline decoration-gold-500/40 underline-offset-2 hover:text-gold-300">
                        Wikipedia en español
                    </a>
                    y otras fuentes · Contenido de Wikipedia bajo licencia CC BY-SA.
                </p>
                <p class="text-[10px] uppercase tracking-[0.25em] text-bone-400/60">Hecho en Paraguay · Edición Patria</p>
            </div>
        </footer>

        <ThemePicker />

        <Transition name="toast">
            <div
                v-if="toast"
                class="fixed bottom-20 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-3 rounded-full border border-gold-400/40 bg-night-900/95 py-2 pl-5 pr-2 text-sm font-medium text-gold-200 backdrop-blur sm:bottom-5"
                style="box-shadow: var(--panel-shadow)"
                role="status"
                aria-live="polite"
            >
                <span>{{ toast }}</span>
                <CloseButton size="sm" label="Cerrar aviso" @click="dismissToast" />
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
