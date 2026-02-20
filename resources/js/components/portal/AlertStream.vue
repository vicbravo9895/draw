<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboard';
import { formatPercent } from '@/composables/useNumberFormat';
import { AlertOctagon, AlertTriangle, ChevronRight } from 'lucide-vue-next';

const store = useDashboardStore();

function severityBorder(s: string) {
    return s === 'critical' ? 'border-l-red-500' : 'border-l-amber-500';
}

function severityBg(s: string) {
    return s === 'critical' ? 'bg-red-50 dark:bg-red-950/30' : 'bg-amber-50 dark:bg-amber-950/30';
}

function severityBadge(s: string) {
    return s === 'critical'
        ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'
        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
}

function typeLabel(type: string) {
    switch (type) {
        case 'part': return 'Parte';
        case 'lot': return 'Lote';
        case 'shift': return 'Turno';
        case 'area': return 'Área';
        default: return type;
    }
}

function timeAgo(timestamp: string): string {
    const diff = Date.now() - new Date(timestamp).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'Ahora';
    if (mins < 60) return `hace ${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `hace ${hrs}h`;
    return `hace ${Math.floor(hrs / 24)}d`;
}
</script>

<template>
    <div class="space-y-3">
        <div class="px-1">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-foreground">
                Alertas en Vivo
            </h3>
            <p class="mt-0.5 text-xs text-muted-foreground">Violaciones de umbrales de calidad</p>
        </div>

        <div v-if="store.alerts.length === 0" class="rounded-lg border bg-card p-6 text-center">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <p class="text-sm font-medium text-foreground">Sin Alertas</p>
            <p class="text-xs text-muted-foreground">No hay alertas de calidad en este momento</p>
        </div>

        <TransitionGroup
            name="alert"
            tag="div"
            class="max-h-[480px] space-y-2 overflow-y-auto"
        >
            <div
                v-for="(alert, idx) in store.alerts"
                :key="`${alert.type}-${alert.identifier}-${idx}`"
                class="rounded-lg border border-l-4 p-4 transition-all duration-500"
                :class="[severityBorder(alert.severity), severityBg(alert.severity)]"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <component
                            :is="alert.severity === 'critical' ? AlertOctagon : AlertTriangle"
                            class="h-4 w-4 shrink-0"
                            :class="alert.severity === 'critical' ? 'text-red-500' : 'text-amber-500'"
                        />
                        <span
                            class="rounded-sm px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                            :class="severityBadge(alert.severity)"
                        >
                            {{ alert.severity }}
                        </span>
                        <span class="text-[10px] uppercase tracking-wider text-muted-foreground">
                            {{ typeLabel(alert.type) }}
                        </span>
                    </div>
                    <span class="shrink-0 text-[10px] text-muted-foreground">
                        {{ timeAgo(alert.timestamp) }}
                    </span>
                </div>

                <p class="text-sm font-bold text-foreground leading-snug">
                    {{ alert.identifier }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Tasa de Defectos: <strong>{{ formatPercent(alert.defect_rate) }}</strong>
                    <span v-if="alert.reference_code"> &middot; {{ alert.reference_code }}</span>
                </p>

                <div v-if="alert.recommended_actions.length > 0" class="mt-3 space-y-1">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Acciones Recomendadas
                    </p>
                    <div
                        v-for="action in alert.recommended_actions"
                        :key="action"
                        class="flex items-start gap-1.5 text-xs text-foreground/80"
                    >
                        <ChevronRight class="mt-0.5 h-3 w-3 shrink-0 text-muted-foreground" />
                        <span>{{ action }}</span>
                    </div>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.alert-enter-active {
    transition: all 0.4s ease-out;
}
.alert-leave-active {
    transition: all 0.3s ease-in;
}
.alert-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}
.alert-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
.alert-move {
    transition: transform 0.3s ease;
}
</style>
