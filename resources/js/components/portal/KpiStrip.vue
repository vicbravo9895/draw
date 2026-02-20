<script setup lang="ts">
import { computed } from 'vue';
import { useDashboardStore } from '@/stores/dashboard';
import AnimatedNumber from './AnimatedNumber.vue';
import { severityColor, formatPercent, formatPPM, formatInteger } from '@/composables/useNumberFormat';
import { Shield, Target, AlertTriangle, Gauge, PackageX } from 'lucide-vue-next';

const store = useDashboardStore();

const cards = computed(() => [
    {
        label: 'Rendimiento Primera Vez',
        value: store.kpis.month.fpy,
        format: 'percent' as const,
        icon: Shield,
        color: severityColor(store.kpis.month.fpy, { green: 95, amber: 90 }, true),
        sub: `Semana: ${formatPercent(store.kpis.week.fpy)} · Hoy: ${formatPercent(store.kpis.today.fpy)}`,
    },
    {
        label: 'Total Inspeccionado',
        value: store.kpis.month.total_inspected,
        format: 'integer' as const,
        icon: Target,
        color: 'neutral' as const,
        sub: `${store.kpis.month.inspection_count} inspecciones este mes`,
    },
    {
        label: 'Total Defectos',
        value: store.kpis.month.total_defects,
        format: 'integer' as const,
        icon: AlertTriangle,
        color: store.kpis.month.total_defects > 0 ? 'red' as const : 'green' as const,
        sub: `Hoy: ${formatInteger(store.kpis.today.total_defects)} · Semana: ${formatInteger(store.kpis.week.total_defects)}`,
    },
    {
        label: 'PPM',
        value: store.kpis.month.ppm,
        format: 'ppm' as const,
        icon: Gauge,
        color: severityColor(store.kpis.month.ppm, { green: 1000, amber: 5000 }, false),
        sub: `Partes Por Millón defectuosas`,
    },
    {
        label: 'Lotes en Riesgo',
        value: store.lotsAtRisk,
        format: 'integer' as const,
        icon: PackageX,
        color: store.lotsAtRisk === 0 ? 'green' as const : store.lotsAtRisk <= 2 ? 'amber' as const : 'red' as const,
        sub: 'Lotes que exceden umbral de calidad',
    },
]);

function borderColor(c: string) {
    switch (c) {
        case 'green': return 'border-l-emerald-500';
        case 'amber': return 'border-l-amber-500';
        case 'red': return 'border-l-red-500';
        default: return 'border-l-zinc-300 dark:border-l-zinc-600';
    }
}

function textColor(c: string) {
    switch (c) {
        case 'green': return 'text-emerald-600 dark:text-emerald-400';
        case 'amber': return 'text-amber-600 dark:text-amber-400';
        case 'red': return 'text-red-600 dark:text-red-400';
        default: return 'text-foreground';
    }
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-5">
        <div
            v-for="(card, idx) in cards"
            :key="idx"
            class="rounded-lg border-l-4 bg-card p-4 shadow-sm transition-all duration-500"
            :class="borderColor(card.color)"
        >
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    {{ card.label }}
                </span>
                <component
                    :is="card.icon"
                    class="h-4 w-4 text-muted-foreground/60"
                />
            </div>
            <div
                class="font-mono text-3xl font-bold tracking-tight leading-tight"
                :class="textColor(card.color)"
            >
                <AnimatedNumber
                    :value="card.value"
                    :format="card.format"
                    :duration="600"
                />
            </div>
            <p class="mt-1 text-xs text-muted-foreground truncate">
                {{ card.sub }}
            </p>
        </div>
    </div>
</template>
