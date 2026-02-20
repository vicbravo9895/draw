<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Pencil, FileDown, FileSpreadsheet, Play, CheckCircle, ScanLine } from 'lucide-vue-next';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { computed, ref } from 'vue';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inspecciones', href: '/app/inspections' },
    { title: 'Detalle', href: '#' },
];

const page = usePage();
const permissions = computed(() => (page.props.auth as any)?.permissions ?? []);
const canExport = computed(() => permissions.value.includes('exports.pdf') || permissions.value.includes('exports.csv'));
const canEdit = computed(() => permissions.value.includes('inspections.edit'));

const props = defineProps<{
    inspection: {
        id: number; company_id: number; company_name: string; reference_code: string;
        date: string; shift: string; project: string; area_line: string;
        status: string; start_time: string | null; end_time: string | null;
        comment_general: string | null; scheduled_by: string | null;
        assigned_inspector_id: number | null; inspector: string | null;
        total_good: number; total_defects: number; total: number; defect_rate: number;
        parts: Array<{
            id: number; part_number: string; comment_part: string | null;
            total_good: number; total_defects: number; total: number; defect_rate: number;
            items: Array<{ id: number; serial_number: string | null; lot_date: string | null; good_qty: number; defects_qty: number; total_qty: number }>;
        }>;
    };
    canStart?: boolean;
    canComplete?: boolean;
}>();

const statusLabels: Record<string, string> = { pending: 'Pendiente', in_progress: 'En Progreso', completed: 'Completada' };
const statusColors: Record<string, string> = { pending: 'secondary', in_progress: 'default', completed: 'outline' };

// Start inspection dialog
const confirmStartOpen = ref(false);

function startInspection() {
    confirmStartOpen.value = true;
}

function confirmStart() {
    router.post(`/app/inspections/${props.inspection.id}/start`, {}, {
        onError: () => useFlash().show({ error: 'Error al iniciar la inspección.' }),
    });
    confirmStartOpen.value = false;
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
</script>

<template>
    <Head :title="inspection.reference_code" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link href="/app/inspections" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="h-4 w-4" /> Inspecciones</Link>
                <div class="flex flex-wrap gap-2">
                    <template v-if="canExport">
                        <a :href="`/app/inspections/${inspection.id}/export-pdf`"><Button variant="outline" size="sm"><FileDown class="mr-2 h-4 w-4" /> PDF</Button></a>
                        <a :href="`/app/inspections/${inspection.id}/export-csv`"><Button variant="outline" size="sm"><FileSpreadsheet class="mr-2 h-4 w-4" /> CSV</Button></a>
                    </template>

                    <!-- Start button: visible when pending and user can start -->
                    <Button v-if="canStart" size="sm" @click="startInspection">
                        <Play class="mr-2 h-4 w-4" /> Iniciar Inspección
                    </Button>

                    <!-- Edit button: visible when in_progress or pending (admin/supervisor) and not completed -->
                    <Link v-if="inspection.status !== 'completed' && !canStart && canEdit" :href="`/app/inspections/${inspection.id}/edit`">
                        <Button size="sm"><Pencil class="mr-2 h-4 w-4" /> Editar</Button>
                    </Link>

                    <!-- Factory capture button: visible when in_progress and user can edit -->
                    <Link v-if="inspection.status === 'in_progress' && canEdit" :href="`/app/inspections/${inspection.id}/capture`">
                        <Button size="sm" variant="default" class="bg-blue-600 hover:bg-blue-700">
                            <ScanLine class="mr-2 h-4 w-4" /> Captura en Planta
                        </Button>
                    </Link>

                    <!-- Complete button: visible when in_progress and user can complete (admin/supervisor) -->
                    <Button v-if="canComplete" variant="default" size="sm" class="bg-green-600 hover:bg-green-700" @click="completeInspection">
                        <CheckCircle class="mr-2 h-4 w-4" /> Completar
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div><CardTitle class="text-xl">{{ inspection.reference_code }}</CardTitle><p class="text-muted-foreground">{{ inspection.company_name }}</p></div>
                        <Badge :variant="(statusColors[inspection.status] as any)">{{ statusLabels[inspection.status] }}</Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Fecha</p><p class="text-sm">{{ inspection.date }}</p></div>
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Turno</p><p class="text-sm">{{ inspection.shift ?? 'N/A' }}</p></div>
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Proyecto</p><p class="text-sm">{{ inspection.project ?? 'N/A' }}</p></div>
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Área</p><p class="text-sm">{{ inspection.area_line ?? 'N/A' }}</p></div>
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Inspector</p><p class="text-sm">{{ inspection.inspector ?? 'N/A' }}</p></div>
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Inicio</p><p class="text-sm">{{ inspection.start_time ?? 'N/A' }}</p></div>
                        <div><p class="text-xs font-medium uppercase text-muted-foreground">Fin</p><p class="text-sm">{{ inspection.end_time ?? 'N/A' }}</p></div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 sm:grid-cols-4">
                <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">Piezas Buenas</p><p class="text-3xl font-bold">{{ inspection.total_good.toLocaleString() }}</p></CardContent></Card>
                <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">Piezas Malas</p><p class="text-3xl font-bold text-destructive">{{ inspection.total_defects.toLocaleString() }}</p></CardContent></Card>
                <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">Total Piezas</p><p class="text-3xl font-bold">{{ inspection.total.toLocaleString() }}</p></CardContent></Card>
                <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">% Defectos</p><p class="text-3xl font-bold">{{ inspection.defect_rate }}%</p></CardContent></Card>
            </div>

            <!-- Empty state when pending and no parts -->
            <Card v-if="inspection.status === 'pending' && inspection.parts.length === 0">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-lg font-medium text-muted-foreground">Esta inspección aún no ha sido iniciada</p>
                    <p class="mt-1 text-sm text-muted-foreground">El inspector asignado debe iniciar la inspección para comenzar a capturar items.</p>
                </CardContent>
            </Card>

            <div v-for="part in inspection.parts" :key="part.id">
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle class="text-base">{{ part.part_number }}</CardTitle>
                            <span class="text-xs text-muted-foreground sm:text-sm">
                                Buenas: <strong>{{ part.total_good.toLocaleString() }}</strong>
                                &middot; Malas: <strong class="text-destructive">{{ part.total_defects.toLocaleString() }}</strong>
                                &middot; Total: <strong>{{ part.total.toLocaleString() }}</strong>
                                &middot; %: <strong>{{ part.defect_rate }}%</strong>
                            </span>
                        </div>
                        <p v-if="part.comment_part" class="text-sm text-muted-foreground">{{ part.comment_part }}</p>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="hidden overflow-x-auto sm:block">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50">
                                        <th class="px-4 py-2 text-left font-medium">#</th>
                                        <th class="px-4 py-2 text-left font-medium">S/N</th>
                                        <th class="px-4 py-2 text-left font-medium">Lote</th>
                                        <th class="px-4 py-2 text-right font-medium">Buenas</th>
                                        <th class="px-4 py-2 text-right font-medium">Malas</th>
                                        <th class="px-4 py-2 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in part.items" :key="item.id" class="border-b last:border-0">
                                        <td class="px-4 py-2 text-muted-foreground">{{ idx + 1 }}</td>
                                        <td class="px-4 py-2">{{ item.serial_number ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ item.lot_date ?? '-' }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums">{{ item.good_qty.toLocaleString() }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-destructive">{{ item.defects_qty }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium">{{ item.total_qty.toLocaleString() }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y sm:hidden">
                            <div v-for="(item, idx) in part.items" :key="item.id" class="grid grid-cols-3 gap-x-3 gap-y-1 px-4 py-3 text-sm">
                                <div class="col-span-3 flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">#{{ idx + 1 }}</span>
                                    <span class="text-xs text-muted-foreground">{{ item.serial_number ?? '-' }} &middot; {{ item.lot_date ?? '-' }}</span>
                                </div>
                                <div class="text-center"><p class="text-[10px] uppercase text-muted-foreground">Buenas</p><p class="tabular-nums font-medium">{{ item.good_qty.toLocaleString() }}</p></div>
                                <div class="text-center"><p class="text-[10px] uppercase text-muted-foreground">Malas</p><p class="tabular-nums font-medium text-destructive">{{ item.defects_qty }}</p></div>
                                <div class="text-center"><p class="text-[10px] uppercase text-muted-foreground">Total</p><p class="tabular-nums font-medium">{{ item.total_qty.toLocaleString() }}</p></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="inspection.comment_general"><CardHeader><CardTitle class="text-base">Comentario General</CardTitle></CardHeader><CardContent><p class="whitespace-pre-wrap text-sm">{{ inspection.comment_general }}</p></CardContent></Card>
        </div>
    </AppLayout>

    <!-- Start Inspection Confirmation -->
    <ConfirmDialog
        :open="confirmStartOpen"
        title="¿Iniciar esta inspección?"
        description="Al iniciar, la inspección pasará a 'En Progreso' y podrás comenzar a capturar items. Se registrará la hora de inicio."
        confirm-label="Iniciar inspección"
        @confirm="confirmStart"
        @cancel="confirmStartOpen = false"
    />

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
</template>
