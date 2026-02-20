<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboard';
import { formatPercent, formatInteger } from '@/composables/useNumberFormat';
import { Crosshair, Calendar, Clock, MapPin } from 'lucide-vue-next';

const store = useDashboardStore();

const typeConfig: Record<string, { label: string; icon: any; desc: string }> = {
    part: { label: 'Peor Parte', icon: Crosshair, desc: 'Mayor cantidad de defectos por número de parte' },
    lot: { label: 'Peor Lote', icon: Calendar, desc: 'Lote con más defectos' },
    shift: { label: 'Peor Turno', icon: Clock, desc: 'Turno con más defectos' },
    area: { label: 'Peor Área / Línea', icon: MapPin, desc: 'Área con más defectos' },
};
</script>

<template>
    <div class="space-y-3">
        <div class="px-1">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-foreground">
                Principales Ofensores
            </h3>
            <p class="mt-0.5 text-xs text-muted-foreground">Mayores fuentes de defectos este mes</p>
        </div>

        <div v-if="store.topOffenders.length === 0" class="rounded-lg border bg-card p-6 text-center text-sm text-muted-foreground">
            No se detectaron fuentes de defectos este mes.
        </div>

        <div
            v-for="offender in store.topOffenders"
            :key="`${offender.type}-${offender.identifier}`"
            class="rounded-lg border border-l-4 border-l-red-500 bg-card p-4 shadow-sm transition-all duration-500"
        >
            <div class="mb-2 flex items-center gap-2">
                <component
                    :is="typeConfig[offender.type]?.icon"
                    class="h-4 w-4 text-red-500"
                />
                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    {{ typeConfig[offender.type]?.label ?? offender.type }}
                </span>
            </div>

            <p class="text-lg font-bold text-foreground leading-tight">
                {{ offender.identifier }}
            </p>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs text-muted-foreground">Defectos</p>
                    <p class="font-mono text-sm font-semibold tabular-nums text-red-600 dark:text-red-400">
                        {{ formatInteger(offender.defects) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">% del Total</p>
                    <p class="font-mono text-sm font-semibold tabular-nums text-foreground">
                        {{ formatPercent(offender.pct_of_total) }}
                    </p>
                </div>
            </div>

            <div class="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                <span>Tasa Defectos: <strong class="text-foreground">{{ formatPercent(offender.defect_rate) }}</strong></span>
                <span>Total: <strong class="text-foreground">{{ formatInteger(offender.total) }}</strong></span>
            </div>
        </div>
    </div>
</template>
