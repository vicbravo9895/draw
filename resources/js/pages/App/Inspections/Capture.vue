<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BarcodeScannerDialog from '@/components/BarcodeScannerDialog.vue';
import NumericKeypad from '@/components/NumericKeypad.vue';
import { ArrowLeft, ScanLine, Zap, ZapOff, Check, Pencil, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import { useCaptureStore } from '@/stores/capture';
import { useScanFeedback } from '@/composables/useScanFeedback';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inspecciones', href: '/app/inspections' },
    { title: 'Captura', href: '#' },
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
        total_good: number;
        total_defects: number;
        total: number;
        defect_rate: number;
        parts: Array<{
            id: number;
            part_number: string;
            total_good: number;
            total_defects: number;
            total: number;
            defect_rate: number;
            items: Array<{
                id: number;
                serial_number: string | null;
                lot_date: string | null;
                good_qty: number;
                defects_qty: number;
                total_qty: number;
            }>;
        }>;
    };
    qualityThresholds: {
        green: number;
        amber: number;
    };
}>();

const store = useCaptureStore();
const { onScanSuccess, onSaveSuccess, onError } = useScanFeedback();

// --- Form state ---
const partNumber = ref('');
const serialNumber = ref('');
const lotDate = ref('');
const goodQty = ref(0);
const defectsQty = ref(0);

// --- UI state ---
const scannerOpen = ref(false);
const scanTarget = ref<'part' | 'serial' | 'lot'>('part');
const keypadOpen = ref(false);
const keypadTarget = ref<'good' | 'defects'>('good');
const saving = ref(false);
const saveSuccess = ref(false);
const formErrors = ref<Record<string, string>>({});
const showLotPrompt = ref(false);
const editingItemId = ref<number | null>(null);
const deleting = ref<number | null>(null);
const confirmDeleteId = ref<number | null>(null);

// --- Refs for focus management ---
const partInput = ref<InstanceType<typeof Input> | null>(null);
const serialInput = ref<InstanceType<typeof Input> | null>(null);
const lotInput = ref<InstanceType<typeof Input> | null>(null);

// --- Live totals (computed from props — auto-updates when Inertia refreshes page data) ---
const liveTotals = computed(() => {
    const totalGood = props.inspection.total_good;
    const totalDefects = props.inspection.total_defects;
    const total = totalGood + totalDefects;
    return {
        total_good: totalGood,
        total_defects: totalDefects,
        total,
        quality_pct: total > 0 ? Math.round((totalGood / total) * 10000) / 100 : 0,
    };
});

// --- Computed ---
const draftQualityPct = computed(() => {
    const total = goodQty.value + defectsQty.value;
    if (total === 0) return null;
    return Math.round((goodQty.value / total) * 10000) / 100;
});

const draftQualityStatus = computed(() => {
    if (draftQualityPct.value === null) return null;
    if (draftQualityPct.value >= props.qualityThresholds.green) return 'acceptable';
    if (draftQualityPct.value >= props.qualityThresholds.amber) return 'warning';
    return 'critical';
});

const qualityColor = computed(() => {
    const pct = liveTotals.value.quality_pct;
    if (liveTotals.value.total === 0) return 'text-zinc-400';
    if (pct >= props.qualityThresholds.green) return 'text-emerald-600';
    if (pct >= props.qualityThresholds.amber) return 'text-amber-500';
    return 'text-red-600';
});

const draftQualityColor = computed(() => {
    if (!draftQualityStatus.value) return 'text-zinc-400';
    if (draftQualityStatus.value === 'acceptable') return 'text-emerald-600';
    if (draftQualityStatus.value === 'warning') return 'text-amber-500';
    return 'text-red-600';
});

const draftStatusLabel = computed(() => {
    if (!draftQualityStatus.value) return '—';
    const labels: Record<string, string> = {
        acceptable: 'ACEPTABLE',
        warning: 'ADVERTENCIA',
        critical: 'CRÍTICO',
    };
    return labels[draftQualityStatus.value];
});

// --- Recent items (flat list, most recent first) ---
const recentItems = computed(() => {
    const items: Array<{
        id: number;
        part_number: string;
        serial_number: string | null;
        lot_date: string | null;
        good_qty: number;
        defects_qty: number;
        total_qty: number;
        quality_pct: number | null;
    }> = [];

    for (const part of props.inspection.parts) {
        for (const item of part.items) {
            const total = item.good_qty + item.defects_qty;
            items.push({
                id: item.id,
                part_number: part.part_number,
                serial_number: item.serial_number,
                lot_date: item.lot_date,
                good_qty: item.good_qty,
                defects_qty: item.defects_qty,
                total_qty: total,
                quality_pct: total > 0 ? Math.round((item.good_qty / total) * 10000) / 100 : null,
            });
        }
    }

    // Newest first (higher id = more recent)
    items.sort((a, b) => b.id - a.id);
    return items;
});

// --- Edit / Delete ---
function editItem(itemId: number) {
    const item = recentItems.value.find((i) => i.id === itemId);
    if (!item) return;

    editingItemId.value = itemId;
    partNumber.value = item.part_number;
    serialNumber.value = item.serial_number ?? '';
    lotDate.value = item.lot_date ?? '';
    goodQty.value = item.good_qty;
    defectsQty.value = item.defects_qty;
    formErrors.value = {};

    // Scroll to capture card
    nextTick(() => {
        document.getElementById('capture-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function cancelEdit() {
    editingItemId.value = null;
    resetForm();
    partNumber.value = '';
    lotDate.value = store.useSameLot && store.lastLot ? store.lastLot : '';
}

function deleteItem(itemId: number) {
    if (confirmDeleteId.value !== itemId) {
        confirmDeleteId.value = itemId;
        return;
    }

    deleting.value = itemId;
    confirmDeleteId.value = null;

    router.delete(`/app/inspections/${props.inspection.id}/items/${itemId}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            onSaveSuccess();
            // If we were editing the deleted item, cancel edit
            if (editingItemId.value === itemId) {
                cancelEdit();
            }
        },
        onError: () => {
            onError();
        },
        onFinish: () => {
            deleting.value = null;
        },
    });
}

// --- Scanner ---
function openScanner(target: 'part' | 'serial' | 'lot') {
    scanTarget.value = target;
    scannerOpen.value = true;
}

function handleScan(value: string) {
    onScanSuccess();
    const target = scanTarget.value;

    if (target === 'part') {
        partNumber.value = value;
        flashField('part');
        nextTick(() => focusField('serial'));
    } else if (target === 'serial') {
        serialNumber.value = value;
        flashField('serial');
        nextTick(() => focusField('lot'));
    } else if (target === 'lot') {
        lotDate.value = value;
        flashField('lot');
    }
}

// --- Hardware scanner (keyboard wedge) support ---
function handleKeydown(e: KeyboardEvent, field: 'part' | 'serial' | 'lot') {
    if (e.key === 'Enter') {
        e.preventDefault();
        const val = field === 'part' ? partNumber.value : field === 'serial' ? serialNumber.value : lotDate.value;
        if (val.trim()) {
            onScanSuccess();
            flashField(field);
            if (field === 'part') nextTick(() => focusField('serial'));
            else if (field === 'serial') nextTick(() => focusField('lot'));
        }
    }
}

// --- Green flash ---
const flashFields = ref<Record<string, boolean>>({ part: false, serial: false, lot: false });

function flashField(field: string) {
    flashFields.value[field] = true;
    setTimeout(() => {
        flashFields.value[field] = false;
    }, 600);
}

// --- Focus ---
function focusField(field: 'part' | 'serial' | 'lot') {
    const refMap: Record<string, any> = { part: partInput, serial: serialInput, lot: lotInput };
    const el = refMap[field]?.value?.$el ?? refMap[field]?.value;
    if (el) el.focus?.();
}

// --- Numeric Keypad ---
function openKeypad(target: 'good' | 'defects') {
    keypadTarget.value = target;
    keypadOpen.value = true;
}

function handleKeypadConfirm(value: number) {
    if (keypadTarget.value === 'good') {
        goodQty.value = value;
    } else {
        defectsQty.value = value;
    }
}

// --- Validation ---
function validate(): boolean {
    const e: Record<string, string> = {};

    if (!partNumber.value.trim()) {
        e.part_number = 'Número de parte es requerido.';
    }
    if (!serialNumber.value.trim()) {
        e.serial_number = 'Número de serie es requerido.';
    }
    if (goodQty.value < 0) {
        e.good_qty = 'Cantidad buena no puede ser negativa.';
    }
    if (defectsQty.value < 0) {
        e.defects_qty = 'Cantidad defectuosa no puede ser negativa.';
    }
    if (goodQty.value === 0 && defectsQty.value === 0) {
        e.good_qty = 'Debe ingresar al menos una cantidad.';
    }

    formErrors.value = e;
    return Object.keys(e).length === 0;
}

// --- Save (create or update) ---
function saveRecord() {
    if (!validate()) {
        onError();
        return;
    }

    const currentLot = lotDate.value.trim();
    saving.value = true;
    formErrors.value = {};

    const isEditing = editingItemId.value !== null;

    const payload = isEditing
        ? {
              serial_number: serialNumber.value.trim(),
              lot_date: currentLot || null,
              good_qty: goodQty.value,
              defects_qty: defectsQty.value,
          }
        : {
              part_number: partNumber.value.trim(),
              serial_number: serialNumber.value.trim(),
              lot_date: currentLot || null,
              good_qty: goodQty.value,
              defects_qty: defectsQty.value,
          };

    // If offline and NOT editing, queue directly
    if (!store.isOnline && !isEditing) {
        store.addPendingRecord({ ...payload, part_number: partNumber.value.trim(), _inspectionId: props.inspection.id });
        onSaveSuccess();
        saveSuccess.value = true;
        store.incrementRecordCount();
        saving.value = false;

        setTimeout(() => {
            saveSuccess.value = false;
            resetForm();
            nextTick(() => focusField('part'));
        }, 800);
        return;
    }

    const url = isEditing
        ? `/app/inspections/${props.inspection.id}/items/${editingItemId.value}`
        : `/app/inspections/${props.inspection.id}/items`;

    const method = isEditing ? 'put' : 'post';

    router[method](url, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            onSaveSuccess();
            saveSuccess.value = true;

            if (isEditing) {
                editingItemId.value = null;
            } else {
                store.incrementRecordCount();
            }

            // Auto-lot logic (only on new records)
            if (!isEditing && currentLot) {
                store.setLastLot(currentLot);
                if (store.recordCount === 1) {
                    showLotPrompt.value = true;
                }
            }

            setTimeout(() => {
                saveSuccess.value = false;
                resetForm();
                partNumber.value = '';

                if (!isEditing && store.rapidMode) {
                    nextTick(() => openScanner('serial'));
                } else {
                    nextTick(() => focusField('part'));
                }

                // Auto-fill lot
                if (store.useSameLot && store.lastLot) {
                    lotDate.value = store.lastLot;
                }
            }, 800);
        },
        onError: (serverErrors) => {
            const mapped: Record<string, string> = {};
            for (const [key, msg] of Object.entries(serverErrors)) {
                mapped[key] = String(msg);
            }
            formErrors.value = mapped;
            onError();
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function resetForm() {
    serialNumber.value = '';
    goodQty.value = 0;
    defectsQty.value = 0;
    formErrors.value = {};

    // Keep part number in rapid mode, or clear
    if (!store.rapidMode) {
        partNumber.value = '';
    }

    // Auto-fill lot from previous if user opted in
    if (store.useSameLot && store.lastLot) {
        lotDate.value = store.lastLot;
    } else if (store.useSameLot === false) {
        lotDate.value = '';
    }
}

function handleLotPromptResponse(useSame: boolean) {
    store.useSameLot = useSame;
    showLotPrompt.value = false;
    if (useSame && store.lastLot) {
        lotDate.value = store.lastLot;
    }
}

// Auto-fill lot on mount if returning to capture
onMounted(() => {
    if (store.useSameLot && store.lastLot) {
        lotDate.value = store.lastLot;
    }
});
</script>

<template>
    <Head title="Captura de Inspección" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-3 lg:p-4">
            <!-- Top bar -->
            <div class="flex items-center justify-between">
                <Link
                    :href="`/app/inspections/${inspection.id}`"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ inspection.reference_code }}
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition-colors"
                    :class="store.rapidMode
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'"
                    @click="store.toggleRapidMode()"
                >
                    <Zap v-if="store.rapidMode" class="h-4 w-4" />
                    <ZapOff v-else class="h-4 w-4" />
                    Modo Rápido
                </button>
            </div>

            <!-- Tablet: two-column layout at 900px+ -->
            <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[1fr_1.2fr] lg:items-start">

                <!-- HEADER CARD — Live Totals -->
                <Card class="border-2 border-zinc-200 dark:border-zinc-700">
                    <CardContent class="p-4">
                        <div class="mb-3 flex items-baseline justify-between">
                            <span class="text-xs font-bold uppercase tracking-widest text-zinc-400">Totales en Vivo</span>
                            <span class="rounded bg-zinc-100 px-2 py-0.5 text-xs font-mono text-zinc-500 dark:bg-zinc-800">
                                {{ store.recordCount }} registros
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <span class="block text-xs font-medium text-zinc-400">Buenas</span>
                                <span class="text-2xl font-bold text-emerald-600">{{ liveTotals.total_good }}</span>
                            </div>
                            <div>
                                <span class="block text-xs font-medium text-zinc-400">Defectos</span>
                                <span class="text-2xl font-bold text-red-500">{{ liveTotals.total_defects }}</span>
                            </div>
                            <div>
                                <span class="block text-xs font-medium text-zinc-400">Total</span>
                                <span class="text-2xl font-bold text-zinc-700 dark:text-zinc-200">{{ liveTotals.total }}</span>
                            </div>
                            <div>
                                <span class="block text-xs font-medium text-zinc-400">Calidad</span>
                                <span class="text-2xl font-bold" :class="qualityColor">
                                    {{ liveTotals.total > 0 ? liveTotals.quality_pct + '%' : '—' }}
                                </span>
                            </div>
                        </div>

                        <!-- Network & sync indicators -->
                        <div v-if="!store.isOnline" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                            Sin conexión — los registros se guardarán localmente
                        </div>
                        <div v-if="store.pendingRecords.length > 0" class="mt-3 flex items-center justify-between rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            <span>{{ store.pendingRecords.length }} registro(s) pendiente(s) de sincronización</span>
                            <button
                                v-if="store.isOnline && !store.isSyncing"
                                type="button"
                                class="ml-2 rounded bg-amber-200 px-2 py-1 text-xs font-bold hover:bg-amber-300 dark:bg-amber-800 dark:hover:bg-amber-700"
                                @click="store.syncPendingRecords()"
                            >
                                Sincronizar
                            </button>
                            <span v-if="store.isSyncing" class="ml-2 text-xs">Sincronizando...</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- CAPTURE CARD -->
                <Card
                    id="capture-card"
                    class="relative overflow-hidden border-2 transition-all duration-300"
                    :class="[
                        saveSuccess
                            ? 'border-emerald-400 bg-emerald-50/50 dark:border-emerald-600 dark:bg-emerald-900/20'
                            : editingItemId
                                ? 'border-blue-400 dark:border-blue-600'
                                : 'border-zinc-200 dark:border-zinc-700',
                    ]"
                >
                    <!-- Success overlay -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-75"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-75"
                    >
                        <div
                            v-if="saveSuccess"
                            class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-emerald-50/90 dark:bg-emerald-900/80"
                        >
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500">
                                <Check class="h-8 w-8 text-white" />
                            </div>
                            <span class="mt-2 text-lg font-bold text-emerald-700 dark:text-emerald-200">Guardado</span>
                        </div>
                    </Transition>

                    <CardContent class="space-y-4 p-4">
                        <!-- Editing banner -->
                        <div v-if="editingItemId" class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-800 dark:bg-blue-900/30">
                            <span class="text-sm font-bold text-blue-700 dark:text-blue-300">
                                <Pencil class="mr-1 inline h-4 w-4" />
                                Editando registro #{{ editingItemId }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-100 dark:text-blue-300 dark:hover:bg-blue-800"
                                @click="cancelEdit"
                            >
                                <X class="h-3.5 w-3.5" /> Cancelar
                            </button>
                        </div>

                        <!-- General error -->
                        <div v-if="formErrors.general" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                            {{ formErrors.general }}
                        </div>

                        <!-- PART NUMBER -->
                        <div>
                            <Label class="mb-1 block text-xs font-bold uppercase tracking-widest text-zinc-400">Número de Parte</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        ref="partInput"
                                        v-model="partNumber"
                                        placeholder="Escanear o escribir..."
                                        :disabled="!!editingItemId"
                                        class="h-14 text-lg font-semibold transition-colors"
                                        :class="[
                                            flashFields.part ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/30' : '',
                                            formErrors.part_number ? 'border-red-400' : '',
                                            editingItemId ? 'opacity-60' : '',
                                        ]"
                                        inputmode="none"
                                        autocomplete="off"
                                        @keydown="handleKeydown($event, 'part')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-14 w-14 shrink-0"
                                    @click="openScanner('part')"
                                >
                                    <ScanLine class="h-6 w-6" />
                                </Button>
                            </div>
                            <p v-if="formErrors.part_number" class="mt-1 text-sm font-medium text-red-600">{{ formErrors.part_number }}</p>
                        </div>

                        <!-- SERIAL NUMBER -->
                        <div>
                            <Label class="mb-1 block text-xs font-bold uppercase tracking-widest text-zinc-400">Número de Serie</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        ref="serialInput"
                                        v-model="serialNumber"
                                        placeholder="Escanear o escribir..."
                                        class="h-14 text-lg font-semibold transition-colors"
                                        :class="[
                                            flashFields.serial ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/30' : '',
                                            formErrors.serial_number ? 'border-red-400' : '',
                                        ]"
                                        inputmode="none"
                                        autocomplete="off"
                                        @keydown="handleKeydown($event, 'serial')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-14 w-14 shrink-0"
                                    @click="openScanner('serial')"
                                >
                                    <ScanLine class="h-6 w-6" />
                                </Button>
                            </div>
                            <p v-if="formErrors.serial_number" class="mt-1 text-sm font-medium text-red-600">{{ formErrors.serial_number }}</p>
                        </div>

                        <!-- LOT -->
                        <div>
                            <Label class="mb-1 block text-xs font-bold uppercase tracking-widest text-zinc-400">Lote</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        ref="lotInput"
                                        v-model="lotDate"
                                        placeholder="Opcional — escanear o escribir"
                                        class="h-14 text-lg font-semibold transition-colors"
                                        :class="flashFields.lot ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/30' : ''"
                                        autocomplete="off"
                                        @keydown="handleKeydown($event, 'lot')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-14 w-14 shrink-0"
                                    @click="openScanner('lot')"
                                >
                                    <ScanLine class="h-6 w-6" />
                                </Button>
                            </div>
                        </div>

                        <!-- Lot prompt -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="showLotPrompt" class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/30">
                                <p class="mb-2 text-sm font-medium text-blue-800 dark:text-blue-200">
                                    ¿Usar el mismo lote para los siguientes items?
                                </p>
                                <div class="flex gap-2">
                                    <Button size="sm" class="h-10 flex-1" @click="handleLotPromptResponse(true)">Sí</Button>
                                    <Button size="sm" variant="outline" class="h-10 flex-1" @click="handleLotPromptResponse(false)">No</Button>
                                </div>
                            </div>
                        </Transition>

                        <!-- QUANTITIES -->
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Good Qty -->
                            <div>
                                <Label class="mb-1 block text-xs font-bold uppercase tracking-widest text-zinc-400">Buenas</Label>
                                <button
                                    type="button"
                                    class="flex h-14 w-full items-center justify-center rounded-lg border-2 text-2xl font-bold transition-colors"
                                    :class="[
                                        formErrors.good_qty
                                            ? 'border-red-400 bg-red-50 text-red-700 dark:border-red-600 dark:bg-red-900/30'
                                            : 'border-zinc-200 bg-white text-emerald-700 hover:border-emerald-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-emerald-400',
                                    ]"
                                    @click="openKeypad('good')"
                                >
                                    {{ goodQty }}
                                </button>
                                <p v-if="formErrors.good_qty" class="mt-1 text-sm font-medium text-red-600">{{ formErrors.good_qty }}</p>
                            </div>

                            <!-- Defects Qty -->
                            <div>
                                <Label class="mb-1 block text-xs font-bold uppercase tracking-widest text-zinc-400">Defectos</Label>
                                <button
                                    type="button"
                                    class="flex h-14 w-full items-center justify-center rounded-lg border-2 text-2xl font-bold transition-colors"
                                    :class="[
                                        formErrors.defects_qty
                                            ? 'border-red-400 bg-red-50 text-red-700 dark:border-red-600 dark:bg-red-900/30'
                                            : 'border-zinc-200 bg-white text-red-600 hover:border-red-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-red-400',
                                    ]"
                                    @click="openKeypad('defects')"
                                >
                                    {{ defectsQty }}
                                </button>
                                <p v-if="formErrors.defects_qty" class="mt-1 text-sm font-medium text-red-600">{{ formErrors.defects_qty }}</p>
                            </div>
                        </div>

                        <!-- QUALITY RESULT -->
                        <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800/60">
                            <div>
                                <span class="block text-xs font-bold uppercase tracking-widest text-zinc-400">Calidad</span>
                                <span class="text-xl font-bold" :class="draftQualityColor">
                                    {{ draftQualityPct !== null ? draftQualityPct + '%' : '—' }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span
                                    class="inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300': draftQualityStatus === 'acceptable',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': draftQualityStatus === 'warning',
                                        'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300': draftQualityStatus === 'critical',
                                        'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400': !draftQualityStatus,
                                    }"
                                >
                                    {{ draftStatusLabel }}
                                </span>
                            </div>
                        </div>

                        <!-- SAVE / UPDATE BUTTONS -->
                        <div class="flex flex-col gap-2">
                            <Button
                                type="button"
                                class="h-16 w-full text-lg font-bold"
                                :class="editingItemId ? 'bg-blue-600 hover:bg-blue-700' : ''"
                                :disabled="saving"
                                @click="saveRecord"
                            >
                                <template v-if="saving">
                                    {{ editingItemId ? 'Actualizando...' : 'Guardando...' }}
                                </template>
                                <template v-else>
                                    {{ editingItemId ? 'Actualizar Registro' : 'Guardar Registro' }}
                                </template>
                            </Button>
                            <Button
                                v-if="editingItemId"
                                type="button"
                                variant="outline"
                                class="h-12 w-full text-base font-semibold"
                                @click="cancelEdit"
                            >
                                <X class="mr-1.5 h-4 w-4" />
                                Cancelar Edición
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- RECENT RECORDS -->
            <div v-if="recentItems.length > 0" class="mt-2">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-zinc-400">
                    Registros Recientes ({{ recentItems.length }})
                </h3>
                <div class="space-y-2">
                    <TransitionGroup
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-for="item in recentItems"
                            :key="item.id"
                            class="group flex items-center gap-3 rounded-xl border bg-white p-3 transition-colors dark:border-zinc-700 dark:bg-zinc-900"
                            :class="[
                                editingItemId === item.id ? 'border-blue-400 ring-2 ring-blue-200 dark:border-blue-600 dark:ring-blue-900' : 'border-zinc-200',
                                deleting === item.id ? 'opacity-50' : '',
                            ]"
                        >
                            <!-- Main info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2">
                                    <span class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">
                                        {{ item.part_number }}
                                    </span>
                                    <span class="truncate text-xs text-zinc-500">
                                        {{ item.serial_number ?? '—' }}
                                    </span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-3 text-xs text-zinc-400">
                                    <span v-if="item.lot_date">Lote: {{ item.lot_date }}</span>
                                    <span class="text-emerald-600">{{ item.good_qty }}B</span>
                                    <span class="text-red-500">{{ item.defects_qty }}D</span>
                                    <span
                                        class="font-semibold"
                                        :class="
                                            item.quality_pct !== null
                                                ? item.quality_pct >= qualityThresholds.green
                                                    ? 'text-emerald-600'
                                                    : item.quality_pct >= qualityThresholds.amber
                                                        ? 'text-amber-500'
                                                        : 'text-red-600'
                                                : 'text-zinc-400'
                                        "
                                    >
                                        {{ item.quality_pct !== null ? item.quality_pct + '%' : '—' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="flex shrink-0 items-center gap-1">
                                <!-- Confirm-delete prompt -->
                                <Transition
                                    enter-active-class="transition-all duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-90"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-100 ease-in"
                                    leave-from-class="opacity-100"
                                    leave-to-class="opacity-0 scale-90"
                                >
                                    <span v-if="confirmDeleteId === item.id" class="mr-1 text-xs font-semibold text-red-600 dark:text-red-400">
                                        ¿Seguro?
                                    </span>
                                </Transition>

                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-blue-600 dark:hover:bg-zinc-800 dark:hover:text-blue-400"
                                    :disabled="!!deleting"
                                    title="Editar"
                                    @click="editItem(item.id)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg transition-colors"
                                    :class="
                                        confirmDeleteId === item.id
                                            ? 'bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-400'
                                            : 'text-zinc-400 hover:bg-zinc-100 hover:text-red-600 dark:hover:bg-zinc-800 dark:hover:text-red-400'
                                    "
                                    :disabled="deleting === item.id"
                                    title="Eliminar"
                                    @click="deleteItem(item.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>
            </div>
        </div>

        <!-- Scanner Dialog -->
        <BarcodeScannerDialog
            :open="scannerOpen"
            :feedback="true"
            :title="scanTarget === 'part' ? 'Escanear Número de Parte' : scanTarget === 'serial' ? 'Escanear Número de Serie' : 'Escanear Lote'"
            @scan="handleScan"
            @update:open="scannerOpen = $event"
        />

        <!-- Numeric Keypad -->
        <NumericKeypad
            :open="keypadOpen"
            :title="keypadTarget === 'good' ? 'Cantidad Buena' : 'Cantidad Defectuosa'"
            :model-value="keypadTarget === 'good' ? goodQty : defectsQty"
            @confirm="handleKeypadConfirm"
            @update:open="keypadOpen = $event"
        />
    </AppLayout>
</template>
