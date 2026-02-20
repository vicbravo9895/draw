<script setup lang="ts">
import { computed } from 'vue';
import { useDashboardStore } from '@/stores/dashboard';
import { TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';
import { formatPercent, formatInteger } from '@/composables/useNumberFormat';

const store = useDashboardStore();

const daily = computed(() => store.trend.daily);
const direction = computed(() => store.trend.direction);

const directionConfig = computed(() => {
    switch (direction.value) {
        case 'improving':
            return { label: 'Mejorando', icon: TrendingUp, color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-100 dark:bg-emerald-900/30' };
        case 'declining':
            return { label: 'Declinando', icon: TrendingDown, color: 'text-red-600 dark:text-red-400', bg: 'bg-red-100 dark:bg-red-900/30' };
        default:
            return { label: 'Estable', icon: Minus, color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-100 dark:bg-amber-900/30' };
    }
});

const sparklinePath = computed(() => {
    const points = daily.value.filter(d => d.quality !== null);
    if (points.length < 2) return '';

    const qualities = points.map(d => d.quality as number);
    const minQ = Math.min(...qualities);
    const maxQ = Math.max(...qualities);
    const range = maxQ - minQ || 1;

    const width = 280;
    const height = 60;
    const padding = 4;
    const usableW = width - padding * 2;
    const usableH = height - padding * 2;

    const coords = qualities.map((q, i) => {
        const x = padding + (i / (qualities.length - 1)) * usableW;
        const y = padding + usableH - ((q - minQ) / range) * usableH;
        return `${x},${y}`;
    });

    return coords.join(' ');
});

const sparklineStrokeColor = computed(() => {
    switch (direction.value) {
        case 'improving': return '#10b981';
        case 'declining': return '#ef4444';
        default: return '#f59e0b';
    }
});

const latestQuality = computed(() => {
    const points = daily.value.filter(d => d.quality !== null);
    return points.length > 0 ? points[points.length - 1].quality : null;
});

const totalPeriod = computed(() => {
    return daily.value.reduce((sum, d) => sum + d.total, 0);
});
</script>

<template>
    <div class="rounded-lg border bg-card p-5 shadow-sm">
        <div class="mb-4 flex items-start justify-between">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-foreground">
                    Tendencia de Calidad
                </h3>
                <p class="mt-0.5 text-xs text-muted-foreground">Últimos 30 días</p>
            </div>
            <div
                class="flex items-center gap-1.5 rounded-full px-2.5 py-1"
                :class="directionConfig.bg"
            >
                <component
                    :is="directionConfig.icon"
                    class="h-3.5 w-3.5"
                    :class="directionConfig.color"
                />
                <span class="text-xs font-semibold" :class="directionConfig.color">
                    {{ directionConfig.label }}
                </span>
            </div>
        </div>

        <div v-if="daily.length < 2" class="flex h-20 items-center justify-center text-sm text-muted-foreground">
            Datos insuficientes para análisis de tendencia.
        </div>

        <div v-else>
            <svg viewBox="0 0 280 60" class="w-full" preserveAspectRatio="none">
                <polyline
                    :points="sparklinePath"
                    fill="none"
                    :stroke="sparklineStrokeColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                <span>{{ daily.length }} días · {{ formatInteger(totalPeriod) }} piezas</span>
                <span v-if="latestQuality !== null">
                    Último: <strong class="text-foreground">{{ formatPercent(latestQuality) }}</strong>
                </span>
            </div>
        </div>
    </div>
</template>
