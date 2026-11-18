import { onBeforeUnmount, onMounted, watch } from 'vue';

/**
 * Comportamiento compartido por los overlays de la aplicación.
 *
 * ¿Por qué un composable? Tres componentes distintos (revelado de tirada,
 * detalle de personaje y selector de temas) necesitaban cerrarse con Escape
 * y bloquear el scroll de fondo. Repetir los listeners hacía que, si el modal
 * se cerraba a mitad de una petición, quedara el scroll trabado o un listener
 * colgado. Centralizar el ciclo de vida en un solo lugar evita ese error.
 *
 * @param {object} options
 * @param {() => boolean} options.active - Getter reactivo: solo actúa cuando el overlay está abierto.
 * @param {() => void} options.onEscape - Qué hacer al pulsar Escape.
 * @param {boolean} [options.lockScroll=true] - Bloquear el scroll del documento mientras está abierto.
 */
export function useDismissableOverlay({ active, onEscape, lockScroll = true } = {}) {
    let overflowAnterior = '';

    function sincronizarScroll(estaAbierto) {
        if (!lockScroll || typeof document === 'undefined') {
            return;
        }

        if (estaAbierto) {
            // Guardamos el valor previo para no pisar un estilo ajeno.
            if (document.body.style.overflow !== 'hidden') {
                overflowAnterior = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
            }
        } else {
            document.body.style.overflow = overflowAnterior;
        }
    }

    function alPulsarTecla(event) {
        if (event.key === 'Escape' && active?.()) {
            onEscape?.();
        }
    }

    // `watch` con un getter detecta cambios en las props que lee `active`.
    watch(active, sincronizarScroll, { immediate: true });

    onMounted(() => {
        window.addEventListener('keydown', alPulsarTecla);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', alPulsarTecla);
        sincronizarScroll(false);
    });
}
