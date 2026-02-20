<script setup lang="ts">
import { computed, ref } from 'vue';
import { useDashboardStore, type PartQuality } from '@/stores/dashboard';
import { formatPercent, formatPPM, formatInteger } from '@/composables/useNumberFormat';

const emit = defineEmits<{
    drillDown: [partNumber: string];
}>();

const store = useDashboardStore();

type SortKey = 'part_number' | 'quality' | 'ppm' | 'total_inspected' | 'total_defects';
const sortKey = ref<SortKey>('quality');
const sortAsc = ref(false);

const sortedParts = computed(() => {
    const parts = [...store.qualityByPart];
    parts.sort((a, b) => {
        const aVal = a[sortKey.value] ?? 0;
        const bVal = b[sortKey.value] ?? 0;
        if (typeof aVal === 'string' && typeof bVal === 'string') {
            return sortAsc.value ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        }
        return sortAsc.value
            ? (aVal as number) - (bVal as number)
            : (bVal as number) - (aVal as number);
    });
    return parts;
});

function toggleSort(key: SortKey) {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = key === 'part_number';
    }
}

function sortIndicator(key: SortKey): string {
    if (sortKey.value !== key) return '';
    return sortAsc.value ? ' \u2191' : ' \u2193';
}

function rowIndicator(part: PartQuality): string {
    if (part.quality === null) return 'bg-zinc-100 dark:bg-zinc-800';
    if (part.quality >= 95) return 'border-l-emerald-500';
    if (part.quality >= 90) return 'border-l-amber-500';
    return 'border-l-red-500';
}

function qualityTextColor(quality: number | null): string {
    if (quality === null) return 'text-muted-foreground';
    if (quality >= 95) return 'text-emerald-600 dark:text-emerald-400';
    if (quality >= 90) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}
</script>

<template>
    <div class="rounded-lg border bg-card shadow-sm">
        <div class="border-b px-5 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-foreground">
                Calidad por Número de Parte
            </h3>
            <p class="mt-0.5 text-xs text-muted-foreground">Mes actual. Haz clic en una fila para ver detalles.</p>
        </div>

        <div v-if="sortedParts.length === 0" class="flex h-32 items-center justify-center text-sm text-muted-foreground">
            Sin datos de inspección este mes.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                        <th
                            class="cursor-pointer px-5 py-3 font-medium hover:text-foreground"
                            @click="toggleSort('part_number')"
                        >
                            No. Parte{{ sortIndicator('part_number') }}
                        </th>
                        <th
                            class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                            @click="toggleSort('quality')"
                        >
                            Calidad %{{ sortIndicator('quality') }}
                        </th>
                        <th
                            class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                            @click="toggleSort('ppm')"
                        >
                            PPM{{ sortIndicator('ppm') }}
                        </th>
                        <th
                            class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                            @click="toggleSort('total_inspected')"
                        >
                            Total Inspeccionado{{ sortIndicator('total_inspected') }}
                        </th>
                        <th
                            class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                            @click="toggleSort('total_defects')"
                        >
                            Defectos{{ sortIndicator('total_defects') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="part in sortedParts"
                        :key="part.part_number"
                        class="cursor-pointer border-b border-l-4 transition-colors duration-300 last:border-b-0 hover:bg-accent/50"
                        :class="rowIndicator(part)"
                        @click="emit('drillDown', part.part_number)"
                    >
                        <td class="px-5 py-3.5 font-medium text-foreground">
                            {{ part.part_number }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono font-semibold tabular-nums" :class="qualityTextColor(part.quality)">
                            {{ formatPercent(part.quality) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono tabular-nums text-muted-foreground">
                            {{ formatPPM(part.ppm) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono tabular-nums text-muted-foreground">
                            {{ formatInteger(part.total_inspected) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono tabular-nums" :class="part.total_defects > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                            {{ formatInteger(part.total_defects) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
