<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { formatInteger, formatPercent, formatPPM, formatCurrency } from '@/composables/useNumberFormat';

const props = withDefaults(defineProps<{
    value: number | null;
    format?: 'integer' | 'percent' | 'ppm' | 'currency';
    duration?: number;
    suffix?: string;
}>(), {
    format: 'integer',
    duration: 600,
    suffix: '',
});

const displayValue = ref(props.value ?? 0);
const isUpdating = ref(false);
let animationFrame: number | null = null;

function formatValue(n: number | null): string {
    if (n === null || n === undefined) return '--';

    switch (props.format) {
        case 'percent':
            return formatPercent(n);
        case 'ppm':
            return formatPPM(n);
        case 'currency':
            return formatCurrency(n);
        case 'integer':
        default:
            return formatInteger(n);
    }
}

function animateTo(target: number) {
    if (animationFrame) {
        cancelAnimationFrame(animationFrame);
    }

    const start = displayValue.value;
    const diff = target - start;

    if (Math.abs(diff) < 0.01) {
        displayValue.value = target;
        return;
    }

    isUpdating.value = true;
    const startTime = performance.now();
    const duration = props.duration;

    function step(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Ease-out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        displayValue.value = start + diff * eased;

        if (progress < 1) {
            animationFrame = requestAnimationFrame(step);
        } else {
            displayValue.value = target;
            animationFrame = null;

            setTimeout(() => {
                isUpdating.value = false;
            }, 300);
        }
    }

    animationFrame = requestAnimationFrame(step);
}

const formatted = ref(formatValue(props.value));

watch(() => displayValue.value, (val) => {
    formatted.value = formatValue(val);
});

watch(() => props.value, (newVal, oldVal) => {
    if (newVal === null) {
        displayValue.value = 0;
        formatted.value = '--';
        return;
    }
    if (oldVal === null || oldVal === undefined) {
        displayValue.value = newVal;
        formatted.value = formatValue(newVal);
        return;
    }
    animateTo(newVal);
}, { flush: 'post' });

onMounted(() => {
    if (props.value !== null) {
        displayValue.value = props.value;
        formatted.value = formatValue(props.value);
    }
});
</script>

<template>
    <span
        class="inline-block tabular-nums transition-all duration-300"
        :class="{ 'ring-2 ring-amber-400/40 rounded-sm': isUpdating }"
    >{{ formatted }}{{ suffix }}</span>
</template>
