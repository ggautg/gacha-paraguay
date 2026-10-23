<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    owned: { type: Boolean, default: false },
});

const initials = computed(() =>
    props.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <div class="relative flex h-full w-full items-end justify-center overflow-hidden bg-gradient-to-b from-night-700/80 via-night-800 to-night-950">
        <div class="paper-noise absolute inset-0 opacity-70" />
        <svg class="h-[82%] w-auto text-white/[0.09]" viewBox="0 0 200 240" fill="currentColor" aria-hidden="true">
            <circle cx="100" cy="82" r="40" />
            <path d="M30 240c0-42 31-72 70-72s70 30 70 72z" />
        </svg>
        <span class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center font-display text-2xl tracking-widest text-white/20">
            {{ owned ? initials : '?' }}
        </span>
    </div>
</template>
