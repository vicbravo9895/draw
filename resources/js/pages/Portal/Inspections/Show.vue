<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, FileDown, FileSpreadsheet } from 'lucide-vue-next';
import { ref } from 'vue';
import { useCompanyChannel } from '@/composables/useCompanyChannel';

defineOptions({ layout: PortalLayout });

const page = usePage();
const companyId = (page.props.auth as { viewer?: { company_id: number } })?.viewer?.company_id;
const isRefreshing = ref(false);

if (companyId) {
    const { onInspectionUpdated, onInspectionCompleted } = useCompanyChannel(companyId, 'portal.company');
    onInspectionUpdated((payload) => {
        if (payload.id === props.inspection.id) {
            isRefreshing.value = true;
            router.reload({
                only: ['inspection'],
                onFinish: () => { isRefreshing.value = false; },
            });
        }
    });
    onInspectionCompleted((payload) => {
        if (payload.id === props.inspection.id) {
            isRefreshing.value = true;
            router.reload({
                only: ['inspection'],
                onFinish: () => { isRefreshing.value = false; },
            });
        }
    });
}

const props = defineProps<{
    inspection: {
        id: number; reference_code: string; company_name: string; date: string;
        shift: string; project: string; area_line: string; status: string;
        start_time: string | null; end_time: string | null;
        comment_general: string | null; scheduled_by: string | null; inspector: string | null;
        total_good: number; total_defects: number; total: number; defect_rate: number;
        parts: Array<{
            id: number; part_number: string; comment_part: string | null; order: number;
            total_good: number; total_defects: number; total: number; defect_rate: number;
            items: Array<{ id: number; serial_number: string | null; lot_date: string | null; good_qty: number; defects_qty: number; total_qty: number }>;
        }>;
        created_at: string;
    };
    company: { name: string; allow_exports: boolean };
}>();

const statusLabels: Record<string, string> = { pending: 'Pendiente', in_progress: 'En Progreso', completed: 'Completada' };
const statusColors: Record<string, string> = { pending: 'secondary', in_progress: 'default', completed: 'outline' };

function fmt(n: number): string { return n.toLocaleString('es-MX'); }
</script>

<template>
    <Head :title="`${inspection.reference_code} - Portal`" />
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/portal/inspections" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="h-4 w-4" /> Regresar</Link>
                <p v-if="isRefreshing" class="text-sm text-muted-foreground">Actualizando…</p>
            </div>
            <div v-if="company.allow_exports" class="flex gap-2">
                <a :href="`/portal/inspections/${inspection.id}/export-pdf`"><Button variant="outline" size="sm"><FileDown class="mr-2 h-4 w-4" /> PDF</Button></a>
                <a :href="`/portal/inspections/${inspection.id}/export-csv`"><Button variant="outline" size="sm"><FileSpreadsheet class="mr-2 h-4 w-4" /> CSV</Button></a>
            </div>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-start justify-between">
                    <div><CardTitle class="text-xl">{{ inspection.reference_code }}</CardTitle><p class="text-muted-foreground">{{ inspection.company_name }}</p></div>
                    <Badge :variant="(statusColors[inspection.status] as any) ?? 'secondary'" class="text-sm">{{ statusLabels[inspection.status] ?? inspection.status }}</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Fecha</p><p class="text-sm">{{ inspection.date }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Turno</p><p class="text-sm">{{ inspection.shift ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Proyecto</p><p class="text-sm">{{ inspection.project ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Área / Línea</p><p class="text-sm">{{ inspection.area_line ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Hora Inicio</p><p class="text-sm">{{ inspection.start_time ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Hora Fin</p><p class="text-sm">{{ inspection.end_time ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Programado por</p><p class="text-sm">{{ inspection.scheduled_by ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-muted-foreground">Inspector</p><p class="text-sm">{{ inspection.inspector ?? 'N/A' }}</p></div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 sm:grid-cols-4">
            <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">Piezas Buenas</p><p class="text-3xl font-bold">{{ fmt(inspection.total_good) }}</p></CardContent></Card>
            <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">Piezas Malas</p><p class="text-3xl font-bold text-destructive">{{ fmt(inspection.total_defects) }}</p></CardContent></Card>
            <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">Total Piezas</p><p class="text-3xl font-bold">{{ fmt(inspection.total) }}</p></CardContent></Card>
            <Card><CardContent class="pt-6"><p class="text-sm font-medium text-muted-foreground">% Defectos</p><p class="text-3xl font-bold">{{ inspection.defect_rate }}%</p></CardContent></Card>
        </div>

        <div v-for="part in inspection.parts" :key="part.id">
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle class="text-base">Parte: {{ part.part_number }}</CardTitle>
                        <span class="text-xs text-muted-foreground sm:text-sm">
                            Buenas: <strong>{{ fmt(part.total_good) }}</strong> &middot; Malas: <strong class="text-destructive">{{ fmt(part.total_defects) }}</strong> &middot; Total: <strong>{{ fmt(part.total) }}</strong> &middot; %: <strong>{{ part.defect_rate }}%</strong>
                        </span>
                    </div>
                    <p v-if="part.comment_part" class="text-sm text-muted-foreground">{{ part.comment_part }}</p>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b bg-muted/50"><th class="px-4 py-2 text-left font-medium">#</th><th class="px-4 py-2 text-left font-medium">S/N</th><th class="px-4 py-2 text-left font-medium">Lote</th><th class="px-4 py-2 text-right font-medium">Buenas</th><th class="px-4 py-2 text-right font-medium">Malas</th><th class="px-4 py-2 text-right font-medium">Total</th></tr></thead>
                            <tbody>
                                <tr v-for="(item, idx) in part.items" :key="item.id" class="border-b last:border-0">
                                    <td class="px-4 py-2 text-muted-foreground">{{ idx + 1 }}</td>
                                    <td class="px-4 py-2">{{ item.serial_number ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ item.lot_date ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ fmt(item.good_qty) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-destructive">{{ fmt(item.defects_qty) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums font-medium">{{ fmt(item.total_qty) }}</td>
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
                            <div class="text-center"><p class="text-[10px] uppercase text-muted-foreground">Buenas</p><p class="tabular-nums font-medium">{{ fmt(item.good_qty) }}</p></div>
                            <div class="text-center"><p class="text-[10px] uppercase text-muted-foreground">Malas</p><p class="tabular-nums font-medium text-destructive">{{ fmt(item.defects_qty) }}</p></div>
                            <div class="text-center"><p class="text-[10px] uppercase text-muted-foreground">Total</p><p class="tabular-nums font-medium">{{ fmt(item.total_qty) }}</p></div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-if="inspection.comment_general"><CardHeader><CardTitle class="text-base">Comentario General</CardTitle></CardHeader><CardContent><p class="whitespace-pre-wrap text-sm">{{ inspection.comment_general }}</p></CardContent></Card>
    </div>
</template>
