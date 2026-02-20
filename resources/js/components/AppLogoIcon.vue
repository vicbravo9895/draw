<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 36 36"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Vertical bar (blue) -->
        <rect x="12" y="2" width="12" height="32" rx="4" fill="#3b7cc4" />
        <!-- Horizontal bar (green) -->
        <rect x="2" y="12" width="32" height="12" rx="4" fill="#3da66a" />
        <!-- Center overlap blend -->
        <rect x="12" y="12" width="12" height="12" rx="2" fill="#2e8a6e" />
    </svg>
</template>
