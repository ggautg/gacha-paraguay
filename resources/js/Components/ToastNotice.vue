<script setup>
/**
 * Aviso global de la aplicación (por ejemplo, la bonificación cobrada).
 *
 * Lee `flash.message` de Inertia por sí mismo: AppLayout ya no necesita conocer
 * el temporizador. El aviso es `role="status"` para que se anuncie solo, se
 * cierra a mano y el temporizador se cancela al desmontar o al cerrar.
 */
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import CloseButton from './CloseButton.vue';

const page = usePage();
const message = ref(null);
let timer = null;

function dismiss() {
    message.value = null;
    clearTimeout(timer);
}

watch(
    () => page.props.flash?.message,
    (value) => {
        if (!value) {
            return;
        }

        message.value = value;
        clearTimeout(timer);
        timer = setTimeout(() => {
            message.value = null;
        }, 4000);
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Transition name="toast">
        <div
            v-if="message"
            class="fixed bottom-20 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-3 rounded-full border border-gold-400/40 bg-night-900/95 py-2 pl-5 pr-2 text-sm font-medium text-gold-200 backdrop-blur sm:bottom-5"
            style="box-shadow: var(--panel-shadow)"
            role="status"
            aria-live="polite"
        >
            <span>{{ message }}</span>
            <CloseButton size="sm" label="Cerrar aviso" @click="dismiss" />
        </div>
    </Transition>
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
