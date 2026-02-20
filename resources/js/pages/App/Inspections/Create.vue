<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Combobox } from '@/components/ui/combobox';
import MultiCombobox from '@/components/ui/combobox/MultiCombobox.vue';
import { TimePicker } from '@/components/ui/time-picker';
import { Plus, Trash2, Copy, Save, ScanLine } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useFlash } from '@/composables/useFlash';
import BarcodeScannerDialog from '@/components/BarcodeScannerDialog.vue';


const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inspecciones', href: '/app/inspections' },
    { title: 'Nueva', href: '/app/inspections/create' },
];

interface ItemForm {
    serial_number: string;
    lot_date: string;
    good_qty: number;
    defects_qty: number;
}

interface PartForm {
    part_number: string;
    comment_part: string;
    items: ItemForm[];
}

const props = defineProps<{
    companies: Array<{ id: number; name: string }>;
    inspectors: Array<{ id: number; name: string }>;
    defaultCompanyId: number | null;
}>();

const companyOptions = computed(() =>
    props.companies.map((c) => ({ value: c.id, label: c.name })),
);

const inspectorOptions = computed(() =>
    props.inspectors.map((i) => ({ value: i.id, label: i.name })),
);

const shiftOptions = [
    { value: '', label: 'Sin turno' },
    { value: '1st', label: '1er Turno' },
    { value: '2nd', label: '2do Turno' },
    { value: '3rd', label: '3er Turno' },
];

const form = useForm({
    company_id: props.defaultCompanyId ?? (props.companies[0]?.id ?? ''),
    date: new Date().toISOString().slice(0, 10),
    shift: '',
    project: '',
    area_line: '',
    assigned_inspector_ids: [] as number[],
    start_time: '',
    end_time: '',
    comment_general: '',
    status: 'pending',
    parts: [
        {
            part_number: '',
            comment_part: '',
            items: [{ serial_number: '', lot_date: '', good_qty: 0, defects_qty: 0 }],
        },
    ] as PartForm[],
});

// Computed totals
const partTotals = computed(() =>
    form.parts.map((part) => ({
        good: part.items.reduce((s, i) => s + (Number(i.good_qty) || 0), 0),
        defects: part.items.reduce((s, i) => s + (Number(i.defects_qty) || 0), 0),
    })),
);

const grandTotal = computed(() => ({
    good: partTotals.value.reduce((s, t) => s + t.good, 0),
    defects: partTotals.value.reduce((s, t) => s + t.defects, 0),
}));

function defectRate(good: number, defects: number): string {
    const total = good + defects;
    if (total === 0) return '0.00';
    return ((defects / total) * 100).toFixed(2);
}

function addPart() {
    form.parts.push({
        part_number: '',
        comment_part: '',
        items: [{ serial_number: '', lot_date: '', good_qty: 0, defects_qty: 0 }],
    });
}

function removePart(idx: number) {
    if (form.parts.length > 1) {
        form.parts.splice(idx, 1);
    }
}

function addItem(partIdx: number) {
    const lastItem = form.parts[partIdx].items.at(-1);
    form.parts[partIdx].items.push({
        serial_number: '',
        lot_date: lastItem?.lot_date ?? '',
        good_qty: 0,
        defects_qty: 0,
    });
    nextTick(() => {
        const input = document.querySelector(
            `[data-item="${partIdx}-${form.parts[partIdx].items.length - 1}-sn"]`,
        ) as HTMLInputElement;
        input?.focus();
    });
}

function removeItem(partIdx: number, itemIdx: number) {
    if (form.parts[partIdx].items.length > 1) {
        form.parts[partIdx].items.splice(itemIdx, 1);
    }
}

function repeatLotDate(partIdx: number, itemIdx: number) {
    if (itemIdx > 0) {
        form.parts[partIdx].items[itemIdx].lot_date =
            form.parts[partIdx].items[itemIdx - 1].lot_date;
    }
}

function handleItemKeydown(e: KeyboardEvent, partIdx: number, itemIdx: number) {
    if (e.key === 'Enter' && itemIdx === form.parts[partIdx].items.length - 1) {
        e.preventDefault();
        addItem(partIdx);
    }
}

// Barcode/QR scanner: target = 'part' | 'sn', partIdx, itemIdx (only for 'sn')
const scannerOpen = ref(false);
const scanTarget = ref<{ type: 'part'; partIdx: number } | { type: 'sn'; partIdx: number; itemIdx: number } | null>(null);

function openScanPartNumber(partIdx: number) {
    scanTarget.value = { type: 'part', partIdx };
    scannerOpen.value = true;
}

function openScanSn(partIdx: number, itemIdx: number) {
    scanTarget.value = { type: 'sn', partIdx, itemIdx };
    scannerOpen.value = true;
}

function onScan(value: string) {
    if (!scanTarget.value) return;
    if (scanTarget.value.type === 'part') {
        form.parts[scanTarget.value.partIdx].part_number = value;
    } else {
        form.parts[scanTarget.value.partIdx].items[scanTarget.value.itemIdx].serial_number = value;
    }
    scanTarget.value = null;
}

function submit() {
    form.post('/app/inspections', {
        onError: () => useFlash().show({ error: 'Error al crear la inspección. Revisa los campos.' }),
    });
}
</script>

<template>
    <Head title="Nueva Inspección" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-5">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Nueva Inspección</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">Captura de reporte de sorteo y retrabajo</p>
            </div>

            <!-- Header -->
            <Card class="border-2 border-border shadow-sm">
                <CardHeader>
                    <CardTitle class="text-base font-semibold">Datos Generales</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-2">
                            <Label>Empresa</Label>
                            <Combobox
                                v-model="form.company_id"
                                :options="companyOptions"
                                placeholder="Seleccionar empresa..."
                                search-placeholder="Buscar empresa..."
                            />
                            <p v-if="form.errors.company_id" class="text-xs font-medium text-quality-critical">
                                {{ form.errors.company_id }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Fecha</Label>
                            <Input v-model="form.date" type="date" />
                        </div>
                        <div class="space-y-2">
                            <Label>Turno</Label>
                            <Combobox
                                v-model="form.shift"
                                :options="shiftOptions"
                                placeholder="Seleccionar turno..."
                                search-placeholder="Buscar turno..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Proyecto</Label>
                            <Input v-model="form.project" placeholder="Proyecto" />
                        </div>
                        <div class="space-y-2">
                            <Label>Área / Línea</Label>
                            <Input v-model="form.area_line" placeholder="Área o línea" />
                        </div>
                        <div class="space-y-2">
                            <Label>Inspectores Asignados</Label>
                            <MultiCombobox
                                v-model="form.assigned_inspector_ids"
                                :options="inspectorOptions"
                                placeholder="Seleccionar uno o más inspectores..."
                                search-placeholder="Buscar inspector..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Hora Inicio</Label>
                            <TimePicker v-model="form.start_time" placeholder="Seleccionar hora..." />
                        </div>
                        <div class="space-y-2">
                            <Label>Hora Fin</Label>
                            <TimePicker v-model="form.end_time" placeholder="Seleccionar hora..." />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Parts -->
            <div v-for="(part, pIdx) in form.parts" :key="pIdx" class="space-y-2">
                <Card class="border-2 border-border shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-2">
                                <CardTitle class="shrink-0 text-base font-semibold">Parte #{{ pIdx + 1 }}</CardTitle>
                                <Input v-model="part.part_number" placeholder="Número de parte" class="h-9 w-full rounded-lg sm:w-48" />
                                <Button type="button" variant="outline" size="sm" class="h-9 shrink-0 rounded-lg" title="Escanear número de parte" @click="openScanPartNumber(pIdx)">
                                    <ScanLine class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground sm:text-sm">
                                    Buenas: <strong class="text-quality-ok">{{ partTotals[pIdx]?.good ?? 0 }}</strong>
                                    &middot; Malas: <strong class="text-quality-critical">{{ partTotals[pIdx]?.defects ?? 0 }}</strong>
                                    &middot; Total: <strong>{{ (partTotals[pIdx]?.good ?? 0) + (partTotals[pIdx]?.defects ?? 0) }}</strong>
                                </span>
                                <Button v-if="form.parts.length > 1" variant="ghost" size="sm" @click="removePart(pIdx)">
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>
                        <Input v-model="part.comment_part" placeholder="Comentario de parte (opcional)" class="mt-2 h-8" />
                    </CardHeader>
                    <CardContent class="p-0">
                        <!-- Desktop table view -->
                        <div class="hidden overflow-x-auto sm:block">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50">
                                        <th class="w-10 px-3 py-2 text-center font-medium">#</th>
                                        <th class="px-3 py-2 text-left font-medium">S/N</th>
                                        <th class="px-3 py-2 text-left font-medium">Lote / Fecha</th>
                                        <th class="w-28 px-3 py-2 text-right font-medium">Buenas</th>
                                        <th class="w-28 px-3 py-2 text-right font-medium">Malas</th>
                                        <th class="w-16 px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, iIdx) in part.items" :key="iIdx" class="border-b last:border-0">
                                        <td class="px-3 py-1 text-center text-muted-foreground">{{ iIdx + 1 }}</td>
                                        <td class="px-3 py-1">
                                            <div class="flex gap-1 items-center">
                                                <Input
                                                    v-model="item.serial_number"
                                                    :data-item="`${pIdx}-${iIdx}-sn`"
                                                    placeholder="S/N"
                                                    class="h-8"
                                                    @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                                />
                                                <Button type="button" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" title="Escanear S/N" @click="openScanSn(pIdx, iIdx)">
                                                    <ScanLine class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-1">
                                            <div class="flex gap-1">
                                                <Input
                                                    v-model="item.lot_date"
                                                    placeholder="Lote"
                                                    class="h-8"
                                                    @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                                />
                                                <Button v-if="iIdx > 0" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" @click="repeatLotDate(pIdx, iIdx)" title="Repetir lote">
                                                    <Copy class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-1">
                                            <Input
                                                v-model.number="item.good_qty"
                                                type="number"
                                                min="0"
                                                class="h-8 text-right"
                                                @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                            />
                                        </td>
                                        <td class="px-3 py-1">
                                            <Input
                                                v-model.number="item.defects_qty"
                                                type="number"
                                                min="0"
                                                class="h-8 text-right"
                                                @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                            />
                                        </td>
                                        <td class="px-3 py-1 text-center">
                                            <Button v-if="part.items.length > 1" variant="ghost" size="sm" class="h-8 w-8 p-0" @click="removeItem(pIdx, iIdx)">
                                                <Trash2 class="h-3 w-3 text-destructive" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile card view -->
                        <div class="space-y-3 p-3 sm:hidden">
                            <div v-for="(item, iIdx) in part.items" :key="iIdx" class="rounded-lg border bg-card p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">Fila {{ iIdx + 1 }}</span>
                                    <Button v-if="part.items.length > 1" variant="ghost" size="sm" class="h-7 w-7 p-0" @click="removeItem(pIdx, iIdx)">
                                        <Trash2 class="h-3 w-3 text-destructive" />
                                    </Button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs">S/N</Label>
                                        <div class="flex gap-1">
                                            <Input
                                                v-model="item.serial_number"
                                                :data-item="`${pIdx}-${iIdx}-sn`"
                                                placeholder="S/N"
                                                class="h-8"
                                                @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                            />
                                            <Button type="button" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" title="Escanear S/N" @click="openScanSn(pIdx, iIdx)">
                                                <ScanLine class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Lote / Fecha</Label>
                                        <div class="flex gap-1">
                                            <Input
                                                v-model="item.lot_date"
                                                placeholder="Lote"
                                                class="h-8"
                                                @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                            />
                                            <Button v-if="iIdx > 0" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" @click="repeatLotDate(pIdx, iIdx)" title="Repetir lote">
                                                <Copy class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Buenas</Label>
                                        <Input
                                            v-model.number="item.good_qty"
                                            type="number"
                                            min="0"
                                            class="h-8"
                                            @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Malas</Label>
                                        <Input
                                            v-model.number="item.defects_qty"
                                            type="number"
                                            min="0"
                                            class="h-8"
                                            @keydown="handleItemKeydown($event, pIdx, iIdx)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t px-4 py-2">
                            <Button variant="ghost" size="sm" @click="addItem(pIdx)">
                                <Plus class="mr-1 h-3 w-3" /> Agregar fila
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Button variant="outline" @click="addPart">
                <Plus class="mr-2 h-4 w-4" /> Agregar Parte
            </Button>

            <!-- Comment & Totals -->
            <Card>
                <CardContent class="space-y-4 pt-6">
                    <div class="space-y-2">
                        <Label>Comentario General</Label>
                        <textarea
                            v-model="form.comment_general"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Observaciones generales..."
                        />
                    </div>
                    <Separator />
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                            <span>Buenas: <strong>{{ grandTotal.good.toLocaleString() }}</strong></span>
                            <span>Malas: <strong class="text-destructive">{{ grandTotal.defects.toLocaleString() }}</strong></span>
                            <span>Total: <strong>{{ (grandTotal.good + grandTotal.defects).toLocaleString() }}</strong></span>
                            <span>% Def: <strong>{{ defectRate(grandTotal.good, grandTotal.defects) }}%</strong></span>
                        </div>
                        <div class="flex gap-2">
                            <Button class="flex-1 sm:flex-none" :disabled="form.processing" @click="submit()">
                                <Save class="mr-2 h-4 w-4" /> Crear Inspección
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <BarcodeScannerDialog
            v-model:open="scannerOpen"
            :title="scanTarget?.type === 'part' ? 'Escanear número de parte' : 'Escanear S/N'"
            @scan="onScan"
        />
    </AppLayout>
</template>
