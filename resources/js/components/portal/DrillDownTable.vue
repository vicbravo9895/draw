<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useDashboardStore, type RecentInspection } from '@/stores/dashboard';
import { formatPercent, formatPPM, formatInteger } from '@/composables/useNumberFormat';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Search, ArrowUpDown, X } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
    filterPartNumber?: string;
}>();

const emit = defineEmits<{
    'update:open': [val: boolean];
}>();

const store = useDashboardStore();

const searchQuery = ref('');
const sortKey = ref<string>('date');
const sortAsc = ref(false);

watch(() => props.filterPartNumber, (val) => {
    if (val) {
        searchQuery.value = val;
    }
});

interface FlatRow {
    inspectionId: number;
    referenceCode: string;
    date: string;
    shift: string;
    project: string;
    areaLine: string;
    inspector: string | null;
    partNumber: string;
    serialNumber: string;
    lotDate: string;
    good: number;
    defective: number;
    total: number;
    quality: number | null;
    ppm: number | null;
}

const flatRows = computed<FlatRow[]>(() => {
    const rows: FlatRow[] = [];
    for (const ins of store.recentInspections) {
        for (const part of ins.parts) {
            for (const item of part.items) {
                const total = item.good_qty + item.defects_qty;
                const quality = total > 0 ? Math.round(((item.good_qty / total) * 100) * 10) / 10 : null;
                const ppm = total > 0 ? Math.round((item.defects_qty / total) * 1_000_000) : null;
                rows.push({
                    inspectionId: ins.id,
                    referenceCode: ins.reference_code,
                    date: ins.date,
                    shift: ins.shift ?? '',
                    project: ins.project ?? '',
                    areaLine: ins.area_line ?? '',
                    inspector: ins.inspector,
                    partNumber: part.part_number,
                    serialNumber: item.serial_number ?? '',
                    lotDate: item.lot_date ?? '',
                    good: item.good_qty,
                    defective: item.defects_qty,
                    total,
                    quality,
                    ppm,
                });
            }
        }
    }
    return rows;
});

const filteredRows = computed(() => {
    let rows = flatRows.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        rows = rows.filter(r =>
            r.partNumber.toLowerCase().includes(q) ||
            r.serialNumber.toLowerCase().includes(q) ||
            r.lotDate.toLowerCase().includes(q) ||
            r.referenceCode.toLowerCase().includes(q) ||
            r.project.toLowerCase().includes(q) ||
            (r.inspector ?? '').toLowerCase().includes(q)
        );
    }

    rows.sort((a, b) => {
        let aVal: any = (a as any)[sortKey.value];
        let bVal: any = (b as any)[sortKey.value];
        if (typeof aVal === 'string') aVal = aVal.toLowerCase();
        if (typeof bVal === 'string') bVal = bVal.toLowerCase();
        if (aVal === null || aVal === undefined) aVal = sortAsc.value ? Infinity : -Infinity;
        if (bVal === null || bVal === undefined) bVal = sortAsc.value ? Infinity : -Infinity;
        if (aVal < bVal) return sortAsc.value ? -1 : 1;
        if (aVal > bVal) return sortAsc.value ? 1 : -1;
        return 0;
    });

    return rows;
});

function toggleSort(key: string) {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = key === 'partNumber' || key === 'date';
    }
}

function qualityColor(quality: number | null): string {
    if (quality === null) return 'text-muted-foreground';
    if (quality >= 95) return 'text-emerald-600 dark:text-emerald-400';
    if (quality >= 90) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}

function clearSearch() {
    searchQuery.value = '';
}

const columns = [
    { key: 'partNumber', label: 'Parte' },
    { key: 'serialNumber', label: 'Serie' },
    { key: 'lotDate', label: 'Lote' },
    { key: 'good', label: 'Buenas' },
    { key: 'defective', label: 'Defectos' },
    { key: 'quality', label: 'Calidad %' },
    { key: 'ppm', label: 'PPM' },
    { key: 'inspector', label: 'Inspector' },
    { key: 'date', label: 'Fecha' },
];
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            class="!w-full !max-w-5xl overflow-hidden"
        >
            <SheetHeader class="shrink-0 border-b pb-4">
                <SheetTitle class="text-lg font-bold">
                    Detalle de Inspección
                </SheetTitle>
                <SheetDescription>
                    Vista detallada de ítems inspeccionados
                    <span v-if="filterPartNumber"> — filtrado por <strong>{{ filterPartNumber }}</strong></span>
                </SheetDescription>
            </SheetHeader>

            <div class="mt-4 shrink-0 px-1">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar parte, serie, lote, inspector..."
                        class="pl-9 pr-9"
                    />
                    <button
                        v-if="searchQuery"
                        @click="clearSearch"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ filteredRows.length }} ítems
                    <span v-if="searchQuery"> (filtrado)</span>
                </p>
            </div>

            <div class="mt-3 flex-1 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-background">
                        <tr class="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="cursor-pointer whitespace-nowrap px-3 py-2.5 font-medium hover:text-foreground"
                                :class="['good', 'defective', 'quality', 'ppm'].includes(col.key) ? 'text-right' : ''"
                                @click="toggleSort(col.key)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ col.label }}
                                    <ArrowUpDown v-if="sortKey === col.key" class="h-3 w-3" />
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, idx) in filteredRows"
                            :key="`${row.inspectionId}-${row.partNumber}-${row.serialNumber}-${idx}`"
                            class="border-b transition-colors last:border-b-0 hover:bg-accent/30"
                        >
                            <td class="whitespace-nowrap px-3 py-3 font-medium text-foreground">
                                {{ row.partNumber }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-muted-foreground">
                                {{ row.serialNumber || '--' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-muted-foreground">
                                {{ row.lotDate || '--' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono tabular-nums text-muted-foreground">
                                {{ formatInteger(row.good) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono tabular-nums" :class="row.defective > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                                {{ formatInteger(row.defective) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono font-semibold tabular-nums" :class="qualityColor(row.quality)">
                                {{ formatPercent(row.quality) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono tabular-nums text-muted-foreground">
                                {{ formatPPM(row.ppm) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-muted-foreground">
                                {{ row.inspector || '--' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-muted-foreground">
                                {{ row.date }}
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="9" class="px-3 py-8 text-center text-muted-foreground">
                                No se encontraron ítems con tu búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </SheetContent>
    </Sheet>
</template>
