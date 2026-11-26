import { nextTick, onBeforeUnmount, onMounted, watch } from 'vue';

/**
 * Comportamiento compartido por los overlays de la aplicación.
 *
 * ¿Por qué un composable? Tres componentes distintos (revelado de tirada,
 * detalle de personaje y selector de temas) necesitaban cerrarse con Escape
 * y bloquear el scroll de fondo. Repetir los listeners hacía que, si el modal
 * se cerraba a mitad de una petición, quedara el scroll trabado o un listener
 * colgado. Centralizar el ciclo de vida en un solo lugar evita ese error.
 *
 * Los dos diálogos `aria-modal` también necesitan confinar el foco: sin eso,
 * el lector de pantalla anuncia un modal mientras Tab sigue recorriendo los
 * botones del fondo. Guardar el elemento que lo abrió permite devolverle el
 * foco al cerrar, en vez de perder al usuario en medio de la página.
 *
 * @param {object} options
 * @param {() => boolean} options.active - Getter reactivo: solo actúa cuando el overlay está abierto.
 * @param {() => void} options.onEscape - Qué hacer al pulsar Escape.
 * @param {import('vue').Ref<HTMLElement | null>} [options.container] - Raíz del diálogo; habilita el focus trap.
 * @param {boolean} [options.lockScroll=true] - Bloquear el scroll del documento mientras está abierto.
 */
export function useDismissableOverlay({ active, onEscape, container, lockScroll = true } = {}) {
    let overflowAnterior = '';
    let focoAnterior = null;

    const selectorFoco = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function focusables() {
        const raiz = container?.value;

        if (!raiz || typeof document === 'undefined') {
            return [];
        }

        // Un botón deshabilitado o aún oculto por la transición no debe recibir Tab.
        return [...raiz.querySelectorAll(selectorFoco)].filter((elemento) => elemento.getClientRects().length > 0);
    }

    function enfocarDialogo() {
        const raiz = container?.value;

        if (raiz) {
            (focusables()[0] ?? raiz).focus?.();
        }
    }

    function sincronizarFoco(estaAbierto) {
        if (typeof document === 'undefined') {
            return;
        }

        if (estaAbierto) {
            if (!focoAnterior) {
                focoAnterior = document.activeElement;
            }

            // El v-if del diálogo se dibuja en este mismo tick: nextTick evita enfocar un nodo ausente.
            nextTick(enfocarDialogo);
        } else if (focoAnterior) {
            focoAnterior.focus?.();
            focoAnterior = null;
        }
    }

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
        if (!active?.()) {
            return;
        }

        if (event.key === 'Escape') {
            onEscape?.();
            return;
        }

        if (event.key !== 'Tab' || !container?.value) {
            return;
        }

        const elementos = focusables();
        const actual = document.activeElement;

        if (!elementos.length) {
            event.preventDefault();
            container.value.focus?.();
            return;
        }

        const primero = elementos[0];
        const ultimo = elementos[elementos.length - 1];

        if (!container.value.contains(actual) || actual === container.value) {
            event.preventDefault();
            (event.shiftKey ? ultimo : primero).focus();
        } else if (event.shiftKey && actual === primero) {
            event.preventDefault();
            ultimo.focus();
        } else if (!event.shiftKey && actual === ultimo) {
            event.preventDefault();
            primero.focus();
        }
    }

    // `watch` con un getter detecta cambios en las props que lee `active`.
    watch(active, (estaAbierto) => {
        sincronizarScroll(estaAbierto);
        sincronizarFoco(estaAbierto);
    }, { immediate: true });

    onMounted(() => {
        window.addEventListener('keydown', alPulsarTecla);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', alPulsarTecla);
        sincronizarScroll(false);
        sincronizarFoco(false);
    });
}
