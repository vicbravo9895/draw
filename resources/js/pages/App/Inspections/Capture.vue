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
    if (liveTotals.value.total === 0) return 'text-muted-foreground';
    if (pct >= props.qualityThresholds.green) return 'text-quality-ok';
    if (pct >= props.qualityThresholds.amber) return 'text-quality-warn';
    return 'text-quality-critical';
});

const draftQualityColor = computed(() => {
    if (!draftQualityStatus.value) return 'text-muted-foreground';
    if (draftQualityStatus.value === 'acceptable') return 'text-quality-ok';
    if (draftQualityStatus.value === 'warning') return 'text-quality-warn';
    return 'text-quality-critical';
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
        <div class="flex h-full flex-1 flex-col gap-5 p-3 sm:p-4 lg:p-5">
            <!-- Top bar: back + rapid mode -->
            <div class="flex items-center justify-between animate-capture-reveal">
                <Link
                    :href="`/app/inspections/${inspection.id}`"
                    class="inline-flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <ArrowLeft class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{ inspection.reference_code }}</span>
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border-2 px-4 py-2.5 text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="store.rapidMode
                        ? 'border-amber-400 bg-amber-50 text-amber-800 dark:border-amber-500 dark:bg-amber-950/50 dark:text-amber-300'
                        : 'border-border bg-muted/80 text-muted-foreground hover:border-muted-foreground/30'"
                    @click="store.toggleRapidMode()"
                >
                    <Zap v-if="store.rapidMode" class="h-4 w-4" />
                    <ZapOff v-else class="h-4 w-4" />
                    Modo Rápido
                </button>
            </div>

            <!-- Two-column layout: KPI strip + Capture form -->
            <div class="flex flex-col gap-5 lg:grid lg:grid-cols-[1fr_1.25fr] lg:items-start">

                <!-- KPI card — Totales en vivo -->
                <Card class="overflow-hidden border-2 border-border bg-card shadow-sm animate-capture-reveal animate-capture-reveal-1">
                    <CardContent class="p-4 sm:p-5">
                        <div class="mb-4 flex items-baseline justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground">Totales en vivo</span>
                            <span class="rounded-md bg-muted px-2.5 py-1 text-xs font-semibold tabular-nums text-muted-foreground">
                                {{ store.recordCount }} reg.
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div class="animate-capture-reveal animate-capture-reveal-2">
                                <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Buenas</span>
                                <span class="mt-0.5 block text-2xl font-bold tabular-nums text-quality-ok sm:text-3xl">{{ liveTotals.total_good }}</span>
                            </div>
                            <div class="animate-capture-reveal animate-capture-reveal-3">
                                <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Defectos</span>
                                <span class="mt-0.5 block text-2xl font-bold tabular-nums text-quality-critical sm:text-3xl">{{ liveTotals.total_defects }}</span>
                            </div>
                            <div class="animate-capture-reveal animate-capture-reveal-4">
                                <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Total</span>
                                <span class="mt-0.5 block text-2xl font-bold tabular-nums text-foreground sm:text-3xl">{{ liveTotals.total }}</span>
                            </div>
                            <div class="animate-capture-reveal animate-capture-reveal-5">
                                <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Calidad</span>
                                <span class="mt-0.5 block text-2xl font-bold tabular-nums sm:text-3xl" :class="qualityColor">
                                    {{ liveTotals.total > 0 ? liveTotals.quality_pct + '%' : '—' }}
                                </span>
                            </div>
                        </div>
                        <!-- Network & sync -->
                        <div v-if="!store.isOnline" class="mt-4 rounded-lg border border-quality-critical/30 bg-quality-critical/10 px-3 py-2.5 text-xs font-medium text-quality-critical">
                            Sin conexión — se guardará localmente
                        </div>
                        <div v-else-if="store.pendingRecords.length > 0" class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-quality-warn/40 bg-quality-warn/10 px-3 py-2.5 text-xs font-medium text-quality-warn">
                            <span>{{ store.pendingRecords.length }} pendiente(s)</span>
                            <button
                                v-if="!store.isSyncing"
                                type="button"
                                class="rounded-lg bg-quality-warn/20 px-3 py-1.5 font-bold hover:bg-quality-warn/30 focus-visible:ring-2 focus-visible:ring-quality-warn"
                                @click="store.syncPendingRecords()"
                            >
                                Sincronizar
                            </button>
                            <span v-else class="text-muted-foreground">Sincronizando…</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- CAPTURE CARD -->
                <Card
                    id="capture-card"
                    class="relative overflow-hidden border-2 shadow-md transition-all duration-300 animate-capture-reveal animate-capture-reveal-6"
                    :class="[
                        saveSuccess
                            ? 'border-quality-ok bg-quality-ok/10 dark:bg-quality-ok/20'
                            : editingItemId
                                ? 'border-primary bg-primary/5 dark:bg-primary/10'
                                : 'border-border bg-card',
                    ]"
                >
                    <!-- Success overlay -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="saveSuccess"
                            class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-quality-ok/95 dark:bg-quality-ok/90"
                        >
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-lg">
                                <Check class="h-10 w-10 text-quality-ok" stroke-width="2.5" />
                            </div>
                            <span class="mt-3 text-xl font-bold text-white drop-shadow-sm">Guardado</span>
                        </div>
                    </Transition>

                    <CardContent class="space-y-5 p-4 sm:p-5">
                        <!-- Editing banner -->
                        <div v-if="editingItemId" class="flex items-center justify-between rounded-xl border-2 border-primary/40 bg-primary/10 px-4 py-2.5">
                            <span class="text-sm font-bold text-primary">
                                <Pencil class="mr-1.5 inline h-4 w-4" />
                                Editando #{{ editingItemId }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-primary/30 bg-card px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring"
                                @click="cancelEdit"
                            >
                                <X class="h-3.5 w-3.5" /> Cancelar
                            </button>
                        </div>

                        <!-- General error -->
                        <div v-if="formErrors.general" class="rounded-xl border-2 border-quality-critical/40 bg-quality-critical/10 px-4 py-2.5 text-sm font-semibold text-quality-critical">
                            {{ formErrors.general }}
                        </div>

                        <!-- PART NUMBER -->
                        <div>
                            <Label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Número de Parte</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        ref="partInput"
                                        v-model="partNumber"
                                        placeholder="Escanear o escribir..."
                                        :disabled="!!editingItemId"
                                        class="h-14 rounded-xl text-lg font-semibold transition-colors"
                                        :class="[
                                            flashFields.part ? 'border-quality-ok bg-quality-ok/10 dark:bg-quality-ok/20' : '',
                                            formErrors.part_number ? 'border-quality-critical ring-2 ring-quality-critical/20' : '',
                                            editingItemId ? 'opacity-70' : '',
                                        ]"
                                        inputmode="none"
                                        autocomplete="off"
                                        @keydown="handleKeydown($event, 'part')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-14 w-14 shrink-0 rounded-xl border-2 focus-visible:ring-2 focus-visible:ring-ring"
                                    @click="openScanner('part')"
                                >
                                    <ScanLine class="h-6 w-6" />
                                </Button>
                            </div>
                            <p v-if="formErrors.part_number" class="mt-1.5 text-sm font-semibold text-quality-critical">{{ formErrors.part_number }}</p>
                        </div>

                        <!-- SERIAL NUMBER -->
                        <div>
                            <Label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Número de Serie</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        ref="serialInput"
                                        v-model="serialNumber"
                                        placeholder="Escanear o escribir..."
                                        class="h-14 rounded-xl text-lg font-semibold transition-colors"
                                        :class="[
                                            flashFields.serial ? 'border-quality-ok bg-quality-ok/10 dark:bg-quality-ok/20' : '',
                                            formErrors.serial_number ? 'border-quality-critical ring-2 ring-quality-critical/20' : '',
                                        ]"
                                        inputmode="none"
                                        autocomplete="off"
                                        @keydown="handleKeydown($event, 'serial')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-14 w-14 shrink-0 rounded-xl border-2 focus-visible:ring-2 focus-visible:ring-ring"
                                    @click="openScanner('serial')"
                                >
                                    <ScanLine class="h-6 w-6" />
                                </Button>
                            </div>
                            <p v-if="formErrors.serial_number" class="mt-1.5 text-sm font-semibold text-quality-critical">{{ formErrors.serial_number }}</p>
                        </div>

                        <!-- LOT -->
                        <div>
                            <Label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Lote</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        ref="lotInput"
                                        v-model="lotDate"
                                        placeholder="Opcional — escanear o escribir"
                                        class="h-14 rounded-xl text-lg font-semibold transition-colors"
                                        :class="flashFields.lot ? 'border-quality-ok bg-quality-ok/10 dark:bg-quality-ok/20' : ''"
                                        autocomplete="off"
                                        @keydown="handleKeydown($event, 'lot')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-14 w-14 shrink-0 rounded-xl border-2 focus-visible:ring-2 focus-visible:ring-ring"
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
                            <div v-if="showLotPrompt" class="rounded-xl border-2 border-primary/30 bg-primary/10 p-4">
                                <p class="mb-3 text-sm font-semibold text-foreground">
                                    ¿Usar el mismo lote para los siguientes?
                                </p>
                                <div class="flex gap-3">
                                    <Button size="sm" class="h-11 flex-1 rounded-xl font-bold" @click="handleLotPromptResponse(true)">Sí</Button>
                                    <Button size="sm" variant="outline" class="h-11 flex-1 rounded-xl font-bold" @click="handleLotPromptResponse(false)">No</Button>
                                </div>
                            </div>
                        </Transition>

                        <!-- QUANTITIES -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Buenas</Label>
                                <button
                                    type="button"
                                    class="flex h-16 w-full items-center justify-center rounded-xl border-2 text-2xl font-bold tabular-nums transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    :class="[
                                        formErrors.good_qty
                                            ? 'border-quality-critical bg-quality-critical/10 text-quality-critical'
                                            : 'border-border bg-card text-quality-ok hover:border-quality-ok/50 dark:bg-surface-raised',
                                    ]"
                                    @click="openKeypad('good')"
                                >
                                    {{ goodQty }}
                                </button>
                                <p v-if="formErrors.good_qty" class="mt-1.5 text-sm font-semibold text-quality-critical">{{ formErrors.good_qty }}</p>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Defectos</Label>
                                <button
                                    type="button"
                                    class="flex h-16 w-full items-center justify-center rounded-xl border-2 text-2xl font-bold tabular-nums transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    :class="[
                                        formErrors.defects_qty
                                            ? 'border-quality-critical bg-quality-critical/10 text-quality-critical'
                                            : 'border-border bg-card text-quality-critical hover:border-quality-critical/50 dark:bg-surface-raised',
                                    ]"
                                    @click="openKeypad('defects')"
                                >
                                    {{ defectsQty }}
                                </button>
                                <p v-if="formErrors.defects_qty" class="mt-1.5 text-sm font-semibold text-quality-critical">{{ formErrors.defects_qty }}</p>
                            </div>
                        </div>

                        <!-- QUALITY RESULT -->
                        <div class="flex items-center justify-between rounded-xl border border-border bg-muted/50 px-4 py-3.5">
                            <div>
                                <span class="block text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Calidad</span>
                                <span class="mt-0.5 block text-xl font-bold tabular-nums" :class="draftQualityColor">
                                    {{ draftQualityPct !== null ? draftQualityPct + '%' : '—' }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span
                                    class="inline-block rounded-full px-3.5 py-1.5 text-xs font-bold uppercase tracking-wider"
                                    :class="{
                                        'bg-quality-ok/20 text-quality-ok': draftQualityStatus === 'acceptable',
                                        'bg-quality-warn/20 text-quality-warn': draftQualityStatus === 'warning',
                                        'bg-quality-critical/20 text-quality-critical': draftQualityStatus === 'critical',
                                        'bg-muted text-muted-foreground': !draftQualityStatus,
                                    }"
                                >
                                    {{ draftStatusLabel }}
                                </span>
                            </div>
                        </div>

                        <!-- SAVE / UPDATE BUTTONS -->
                        <div class="flex flex-col gap-3">
                            <Button
                                type="button"
                                class="h-16 w-full rounded-xl text-lg font-bold shadow-sm transition-transform active:scale-[0.99]"
                                :class="editingItemId ? 'bg-primary hover:bg-primary/90' : ''"
                                :disabled="saving"
                                @click="saveRecord"
                            >
                                <template v-if="saving">
                                    {{ editingItemId ? 'Actualizando…' : 'Guardando…' }}
                                </template>
                                <template v-else>
                                    {{ editingItemId ? 'Actualizar Registro' : 'Guardar Registro' }}
                                </template>
                            </Button>
                            <Button
                                v-if="editingItemId"
                                type="button"
                                variant="outline"
                                class="h-12 w-full rounded-xl text-base font-semibold"
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
            <section v-if="recentItems.length > 0" class="animate-capture-reveal animate-capture-reveal-7">
                <h3 class="mb-3 text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                    Registros recientes ({{ recentItems.length }})
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
                            class="capture-list-item group flex items-center gap-3 rounded-xl border-2 bg-card p-3 transition-colors dark:bg-surface-raised"
                            :class="[
                                editingItemId === item.id ? 'border-primary ring-2 ring-primary/20' : 'border-border',
                                deleting === item.id ? 'opacity-50' : '',
                            ]"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2">
                                    <span class="truncate text-sm font-bold text-foreground">
                                        {{ item.part_number }}
                                    </span>
                                    <span class="truncate text-xs text-muted-foreground">
                                        {{ item.serial_number ?? '—' }}
                                    </span>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                    <span v-if="item.lot_date">Lote: {{ item.lot_date }}</span>
                                    <span class="font-semibold text-quality-ok">{{ item.good_qty }}B</span>
                                    <span class="font-semibold text-quality-critical">{{ item.defects_qty }}D</span>
                                    <span
                                        class="font-bold tabular-nums"
                                        :class="
                                            item.quality_pct !== null
                                                ? item.quality_pct >= qualityThresholds.green
                                                    ? 'text-quality-ok'
                                                    : item.quality_pct >= qualityThresholds.amber
                                                        ? 'text-quality-warn'
                                                        : 'text-quality-critical'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ item.quality_pct !== null ? item.quality_pct + '%' : '—' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <Transition
                                    enter-active-class="transition-all duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-90"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-100 ease-in"
                                    leave-from-class="opacity-100"
                                    leave-to-class="opacity-0 scale-90"
                                >
                                    <span v-if="confirmDeleteId === item.id" class="mr-1 text-xs font-bold text-quality-critical">
                                        ¿Seguro?
                                    </span>
                                </Transition>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-primary focus-visible:ring-2 focus-visible:ring-ring"
                                    :disabled="!!deleting"
                                    title="Editar"
                                    @click="editItem(item.id)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-ring"
                                    :class="
                                        confirmDeleteId === item.id
                                            ? 'bg-quality-critical/20 text-quality-critical'
                                            : 'text-muted-foreground hover:bg-muted hover:text-quality-critical'
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
            </section>
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
