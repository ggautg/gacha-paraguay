<script setup>
import { computed } from 'vue';
import { rarityMeta } from '../lib/rarity';

const props = defineProps({
    rarity: { type: Number, required: true },
    size: { type: String, default: 'sm' },
});

const meta = computed(() => rarityMeta(props.rarity));
const dimension = computed(() => (props.size === 'lg' ? 'h-4 w-4' : 'h-3 w-3'));
</script>

<template>
    <span class="inline-flex items-center gap-0.5" :title="meta.label" :style="{ color: meta.color }">
        <svg
            v-for="star in rarity"
            :key="star"
            :class="dimension"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
        >
            <path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.4l6.5-.9z" />
        </svg>
    </span>
</template>
