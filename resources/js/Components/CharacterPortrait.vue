<script setup>
/**
 * Retrato con recuperación automática ante imágenes rotas.
 *
 * ¿Por qué existe? Las fotos vienen de Wikipedia y en ocasiones la URL
 * desaparece o Wikimedia responde un error. Antes cada página repetía el
 * patrón `img v-if / PortraitFallback v-else`, pero eso solo cubría el caso
 * de que no hubiera imagen: si la URL fallaba, quedaba un hueco en blanco.
 * Escuchamos `@error` para caer a la figura tipográfica y mantener la tarjeta
 * legible. El `watch` reinicia el intento cuando cambia el personaje, para
 * que un fallback de la tarjeta anterior no se quede "pegado" en un modal.
 */
import { computed, ref, watch } from 'vue';
import PortraitFallback from './PortraitFallback.vue';

const props = defineProps({
    image: { type: String, default: '' },
    name: { type: String, required: true },
    owned: { type: Boolean, default: true },
    lazy: { type: Boolean, default: false },
});

const failed = ref(false);

watch(
    () => props.image,
    () => {
        failed.value = false;
    },
);

const showImage = computed(() => Boolean(props.image) && !failed.value);
</script>

<template>
    <img
        v-if="showImage"
        :src="image"
        :alt="`Retrato de ${name}`"
        :loading="lazy ? 'lazy' : 'eager'"
        class="h-full w-full object-cover object-top"
        @error="failed = true"
    />
    <PortraitFallback v-else :name="name" :owned="owned" />
</template>
