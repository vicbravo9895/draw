<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
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
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Plus, Trash2, Copy, Save, CheckCircle, ScanLine } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useFlash } from '@/composables/useFlash';
import BarcodeScannerDialog from '@/components/BarcodeScannerDialog.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inspecciones', href: '/app/inspections' },
    { title: 'Editar', href: '#' },
];

const props = defineProps<{
    inspection: {
        id: number;
        company_id: number;
        company_name: string;
        reference_code: string;
        date: string;
        shift: string;
        project: string;
        area_line: string;
        status: string;
        start_time: string | null;
        end_time: string | null;
        comment_general: string | null;
        assigned_inspector_id: number | null;
        assigned_inspector_ids: number[];
        parts: Array<{
            id: number;
            part_number: string;
            comment_part: string | null;
            order: number;
            items: Array<{
                id: number;
                serial_number: string | null;
                lot_date: string | null;
                good_qty: number;
                defects_qty: number;
            }>;
        }>;
    };
    inspectors: Array<{ id: number; name: string }>;
    isInspector?: boolean;
    canComplete?: boolean;
}>();

const headerDisabled = computed(() => props.isInspector === true);

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
    date: props.inspection.date,
    shift: props.inspection.shift ?? '',
    project: props.inspection.project ?? '',
    area_line: props.inspection.area_line ?? '',
    assigned_inspector_ids: props.inspection.assigned_inspector_ids ?? (props.inspection.assigned_inspector_id ? [props.inspection.assigned_inspector_id] : []),
    start_time: props.inspection.start_time ?? '',
    end_time: props.inspection.end_time ?? '',
    comment_general: props.inspection.comment_general ?? '',
    parts: props.inspection.parts.map((p) => ({
        id: p.id,
        part_number: p.part_number,
        comment_part: p.comment_part ?? '',
        items: p.items.map((i) => ({
            id: i.id,
            serial_number: i.serial_number ?? '',
            lot_date: i.lot_date ?? '',
            good_qty: i.good_qty,
            defects_qty: i.defects_qty,
        })),
    })),
});

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
        id: undefined as any,
        part_number: '',
        comment_part: '',
        items: [{ id: undefined as any, serial_number: '', lot_date: '', good_qty: 0, defects_qty: 0 }],
    });
}

function removePart(idx: number) {
    if (form.parts.length > 1) form.parts.splice(idx, 1);
}

function addItem(pIdx: number) {
    const last = form.parts[pIdx].items.at(-1);
    form.parts[pIdx].items.push({
        id: undefined as any,
        serial_number: '',
        lot_date: last?.lot_date ?? '',
        good_qty: 0,
        defects_qty: 0,
    });
    nextTick(() => {
        const el = document.querySelector(
            `[data-item="${pIdx}-${form.parts[pIdx].items.length - 1}-sn"]`,
        ) as HTMLInputElement;
        el?.focus();
    });
}

function removeItem(pIdx: number, iIdx: number) {
    if (form.parts[pIdx].items.length > 1) form.parts[pIdx].items.splice(iIdx, 1);
}

function repeatLotDate(pIdx: number, iIdx: number) {
    if (iIdx > 0) form.parts[pIdx].items[iIdx].lot_date = form.parts[pIdx].items[iIdx - 1].lot_date;
}

function handleItemKeydown(e: KeyboardEvent, pIdx: number, iIdx: number) {
    if (e.key === 'Enter' && iIdx === form.parts[pIdx].items.length - 1) {
        e.preventDefault();
        addItem(pIdx);
    }
}

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

const validationErrorMessages = computed(() => {
    const err = form.errors;
    if (!err || Object.keys(err).length === 0) return [];
    return Object.entries(err).map(([key, msg]) => ({ key, msg: Array.isArray(msg) ? msg[0] : msg }));
});

function save() {
    form.put(`/app/inspections/${props.inspection.id}`, {
        preserveScroll: true,
        onError: () => useFlash().show({ error: 'Error al guardar la inspección. Revisa los campos.' }),
    });
}

// Complete inspection dialog
const confirmCompleteOpen = ref(false);

function completeInspection() {
    confirmCompleteOpen.value = true;
}

function confirmComplete() {
    router.post(`/app/inspections/${props.inspection.id}/complete`, {}, {
        onError: () => useFlash().show({ error: 'Error al completar la inspección.' }),
    });
    confirmCompleteOpen.value = false;
}

const statusLabels: Record<string, string> = { pending: 'Pendiente', in_progress: 'En Progreso', completed: 'Completada' };
</script>

<template>
    <Head :title="`Editar ${inspection.reference_code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Editar: {{ inspection.reference_code }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Empresa: {{ inspection.company_name }}
                        <span class="ml-2 inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium">
                            {{ statusLabels[inspection.status] ?? inspection.status }}
                        </span>
                    </p>
                </div>
                <Link v-if="inspection.status === 'in_progress'" :href="`/app/inspections/${inspection.id}/capture`">
                    <Button variant="default" size="sm" class="bg-blue-600 hover:bg-blue-700">
                        <ScanLine class="mr-2 h-4 w-4" /> Captura en Planta
                    </Button>
                </Link>
            </div>

            <div v-if="validationErrorMessages.length" class="rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <p class="font-medium">Revisa los siguientes campos:</p>
                <ul class="mt-1 list-inside list-disc">
                    <li v-for="e in validationErrorMessages" :key="e.key">{{ e.msg }}</li>
                </ul>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Datos Generales</CardTitle>
                    <p v-if="headerDisabled" class="text-xs text-muted-foreground">Solo lectura — los inspectores no pueden modificar la cabecera.</p>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-2"><Label>Fecha</Label><Input v-model="form.date" type="date" :disabled="headerDisabled" /></div>
                        <div class="space-y-2">
                            <Label>Turno</Label>
                            <Combobox v-model="form.shift" :options="shiftOptions" placeholder="Seleccionar turno..." search-placeholder="Buscar turno..." :disabled="headerDisabled" />
                        </div>
                        <div class="space-y-2"><Label>Proyecto</Label><Input v-model="form.project" :disabled="headerDisabled" /></div>
                        <div class="space-y-2"><Label>Área / Línea</Label><Input v-model="form.area_line" :disabled="headerDisabled" /></div>
                        <div class="space-y-2">
                            <Label>Inspectores</Label>
                            <MultiCombobox
                                v-model="form.assigned_inspector_ids"
                                :options="inspectorOptions"
                                placeholder="Seleccionar uno o más inspectores..."
                                search-placeholder="Buscar inspector..."
                                :disabled="headerDisabled"
                            />
                        </div>
                        <div class="space-y-2"><Label>Hora Inicio</Label><TimePicker v-model="form.start_time" placeholder="Seleccionar hora..." :disabled="headerDisabled" /></div>
                        <div class="space-y-2"><Label>Hora Fin</Label><TimePicker v-model="form.end_time" placeholder="Seleccionar hora..." :disabled="headerDisabled" /></div>
                    </div>
                </CardContent>
            </Card>

            <div v-for="(part, pIdx) in form.parts" :key="pIdx">
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-2">
                                <CardTitle class="shrink-0 text-base">Parte #{{ pIdx + 1 }}</CardTitle>
                                <Input v-model="part.part_number" placeholder="Número de parte" class="h-8 w-full sm:w-48" />
                                <Button type="button" variant="outline" size="sm" class="h-8 shrink-0" title="Escanear número de parte" @click="openScanPartNumber(pIdx)">
                                    <ScanLine class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground sm:text-sm">
                                    Buenas: <strong>{{ partTotals[pIdx]?.good }}</strong>
                                    &middot; Malas: <strong class="text-destructive">{{ partTotals[pIdx]?.defects }}</strong>
                                    &middot; Total: <strong>{{ (partTotals[pIdx]?.good ?? 0) + (partTotals[pIdx]?.defects ?? 0) }}</strong>
                                </span>
                                <Button v-if="form.parts.length > 1" variant="ghost" size="sm" @click="removePart(pIdx)">
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>
                        <Input v-model="part.comment_part" placeholder="Comentario de parte" class="mt-2 h-8" />
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="hidden overflow-x-auto sm:block">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50">
                                        <th class="w-10 px-3 py-2 text-center font-medium">#</th>
                                        <th class="px-3 py-2 text-left font-medium">S/N</th>
                                        <th class="px-3 py-2 text-left font-medium">Lote</th>
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
                                                <Input v-model="item.serial_number" :data-item="`${pIdx}-${iIdx}-sn`" placeholder="S/N" class="h-8" @keydown="handleItemKeydown($event, pIdx, iIdx)" />
                                                <Button type="button" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" title="Escanear S/N" @click="openScanSn(pIdx, iIdx)">
                                                    <ScanLine class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-1">
                                            <div class="flex gap-1">
                                                <Input v-model="item.lot_date" class="h-8" @keydown="handleItemKeydown($event, pIdx, iIdx)" />
                                                <Button v-if="iIdx > 0" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" @click="repeatLotDate(pIdx, iIdx)"><Copy class="h-3 w-3" /></Button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-1"><Input v-model.number="item.good_qty" type="number" min="0" class="h-8 text-right" @keydown="handleItemKeydown($event, pIdx, iIdx)" /></td>
                                        <td class="px-3 py-1"><Input v-model.number="item.defects_qty" type="number" min="0" class="h-8 text-right" @keydown="handleItemKeydown($event, pIdx, iIdx)" /></td>
                                        <td class="px-3 py-1 text-center">
                                            <Button v-if="part.items.length > 1" variant="ghost" size="sm" class="h-8 w-8 p-0" @click="removeItem(pIdx, iIdx)"><Trash2 class="h-3 w-3 text-destructive" /></Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3 p-3 sm:hidden">
                            <div v-for="(item, iIdx) in part.items" :key="iIdx" class="rounded-lg border bg-card p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">Fila {{ iIdx + 1 }}</span>
                                    <Button v-if="part.items.length > 1" variant="ghost" size="sm" class="h-7 w-7 p-0" @click="removeItem(pIdx, iIdx)"><Trash2 class="h-3 w-3 text-destructive" /></Button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs">S/N</Label>
                                        <div class="flex gap-1">
                                            <Input v-model="item.serial_number" :data-item="`${pIdx}-${iIdx}-sn`" placeholder="S/N" class="h-8" @keydown="handleItemKeydown($event, pIdx, iIdx)" />
                                            <Button type="button" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" title="Escanear S/N" @click="openScanSn(pIdx, iIdx)">
                                                <ScanLine class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="space-y-1"><Label class="text-xs">Lote</Label>
                                        <div class="flex gap-1"><Input v-model="item.lot_date" placeholder="Lote" class="h-8" @keydown="handleItemKeydown($event, pIdx, iIdx)" /><Button v-if="iIdx > 0" variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" @click="repeatLotDate(pIdx, iIdx)"><Copy class="h-3 w-3" /></Button></div>
                                    </div>
                                    <div class="space-y-1"><Label class="text-xs">Buenas</Label><Input v-model.number="item.good_qty" type="number" min="0" class="h-8" @keydown="handleItemKeydown($event, pIdx, iIdx)" /></div>
                                    <div class="space-y-1"><Label class="text-xs">Malas</Label><Input v-model.number="item.defects_qty" type="number" min="0" class="h-8" @keydown="handleItemKeydown($event, pIdx, iIdx)" /></div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t px-4 py-2"><Button variant="ghost" size="sm" @click="addItem(pIdx)"><Plus class="mr-1 h-3 w-3" /> Fila</Button></div>
                    </CardContent>
                </Card>
            </div>

            <Button variant="outline" @click="addPart"><Plus class="mr-2 h-4 w-4" /> Agregar Parte</Button>

            <Card>
                <CardContent class="space-y-4 pt-6">
                    <div class="space-y-2">
                        <Label>Comentario General</Label>
                        <textarea v-model="form.comment_general" rows="3" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                    </div>
                    <Separator />
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                            <span>Buenas: <strong>{{ grandTotal.good.toLocaleString() }}</strong></span>
                            <span>Malas: <strong class="text-destructive">{{ grandTotal.defects.toLocaleString() }}</strong></span>
                            <span>Total: <strong>{{ (grandTotal.good + grandTotal.defects).toLocaleString() }}</strong></span>
                            <span>% Def: <strong>{{ defectRate(grandTotal.good, grandTotal.defects) }}%</strong></span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <!-- Save button: always visible, keeps current status -->
                            <Button class="flex-1 sm:flex-none" :disabled="form.processing" @click="save">
                                <Save class="mr-2 h-4 w-4" /> Guardar
                            </Button>

                            <!-- Complete button: only for admin/supervisor when in_progress -->
                            <Button
                                v-if="canComplete"
                                class="flex-1 bg-green-600 hover:bg-green-700 sm:flex-none"
                                :disabled="form.processing"
                                @click="completeInspection"
                            >
                                <CheckCircle class="mr-2 h-4 w-4" /> Completar
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>

    <!-- Complete Inspection Confirmation -->
    <ConfirmDialog
        :open="confirmCompleteOpen"
        title="¿Completar esta inspección?"
        description="Una vez completada, la inspección no se podrá editar. Se registrará la hora de fin."
        confirm-label="Completar inspección"
        variant="destructive"
        @confirm="confirmComplete"
        @cancel="confirmCompleteOpen = false"
    />

    <BarcodeScannerDialog
        v-model:open="scannerOpen"
        :title="scanTarget?.type === 'part' ? 'Escanear número de parte' : 'Escanear S/N'"
        @scan="onScan"
    />
</template>
