<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, FileDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useCompanyChannel, type InspectionEvent } from '@/composables/useCompanyChannel';

defineOptions({ layout: PortalLayout });

const page = usePage();
const companyId = (page.props.auth as { viewer?: { company_id: number } })?.viewer?.company_id;

const isRefreshing = ref(false);

function refreshInspections() {
    isRefreshing.value = true;
    router.reload({
        only: ['inspections'],
        onFinish: () => { isRefreshing.value = false; },
    });
}

if (companyId) {
    const { onInspectionUpdated, onInspectionCompleted } = useCompanyChannel(companyId, 'portal.company');
    onInspectionUpdated((payload: InspectionEvent) => {
        applyEventToRow(payload);
        refreshInspections();
    });
    onInspectionCompleted((payload: InspectionEvent) => {
        applyEventToRow(payload);
        refreshInspections();
    });
}

const props = defineProps<{
    inspections: {
        data: Array<{
            id: number;
            reference_code: string;
            company_name: string;
            date: string;
            shift: string;
            project: string;
            area_line: string;
            status: string;
            scheduled_by: string | null;
            inspector: string | null;
            total_good: number;
            total_defects: number;
            total: number;
            defect_rate: number;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
    company: { name: string; allow_exports: boolean };
}>();

const displayInspections = ref(JSON.parse(JSON.stringify(props.inspections)));

function applyEventToRow(payload: InspectionEvent) {
    const row = displayInspections.value.data.find((r) => r.id === payload.id);
    if (!row) return;
    if (payload.status !== undefined) row.status = payload.status;
    if (payload.total_good !== undefined) row.total_good = payload.total_good;
    if (payload.total_defects !== undefined) row.total_defects = payload.total_defects;
    if (payload.total !== undefined) row.total = payload.total;
    if (payload.defect_rate !== undefined) row.defect_rate = payload.defect_rate;
}

watch(() => props.inspections, (next) => {
    displayInspections.value = JSON.parse(JSON.stringify(next));
}, { deep: true });

const statusLabels: Record<string, string> = {
    pending: 'Pendiente',
    in_progress: 'En Progreso',
    completed: 'Completada',
};

const statusColors: Record<string, string> = {
    pending: 'secondary',
    in_progress: 'default',
    completed: 'outline',
};

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/portal/inspections', {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
}

watch([search, statusFilter, dateFrom, dateTo], applyFilters);

function formatNumber(n: number): string {
    return n.toLocaleString('es-MX');
}
</script>

<template>
    <Head title="Inspecciones - Portal" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Inspecciones</h1>
                <p class="text-muted-foreground">{{ displayInspections.total }} inspecciones encontradas</p>
            </div>
            <p v-if="isRefreshing" class="shrink-0 text-sm text-muted-foreground">Actualizando…</p>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            placeholder="Buscar referencia, proyecto..."
                            class="pl-9"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="">Todos los estatus</option>
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En Progreso</option>
                        <option value="completed">Completada</option>
                    </select>
                    <Input v-model="dateFrom" type="date" placeholder="Desde" />
                    <Input v-model="dateTo" type="date" placeholder="Hasta" />
                </div>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-3 text-left font-medium">Referencia</th>
                                <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                <th class="px-4 py-3 text-left font-medium">Turno</th>
                                <th class="px-4 py-3 text-left font-medium">Proyecto</th>
                                <th class="px-4 py-3 text-left font-medium">Área</th>
                                <th class="px-4 py-3 text-left font-medium">Estatus</th>
                                <th class="px-4 py-3 text-right font-medium">Buenas</th>
                                <th class="px-4 py-3 text-right font-medium">Malas</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                                <th class="px-4 py-3 text-right font-medium">%</th>
                                <th class="px-4 py-3 text-right font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ins in displayInspections.data"
                                :key="ins.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <Link :href="`/portal/inspections/${ins.id}`" class="text-primary hover:underline">
                                        {{ ins.reference_code }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ ins.date }}</td>
                                <td class="px-4 py-3">{{ ins.shift }}</td>
                                <td class="px-4 py-3">{{ ins.project }}</td>
                                <td class="px-4 py-3">{{ ins.area_line }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="(statusColors[ins.status] as any) ?? 'secondary'">
                                        {{ statusLabels[ins.status] ?? ins.status }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatNumber(ins.total_good) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-destructive">{{ formatNumber(ins.total_defects) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">{{ formatNumber(ins.total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ ins.defect_rate }}%</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/portal/inspections/${ins.id}`">
                                        <Button variant="ghost" size="sm">Ver</Button>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="displayInspections.data.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">
                                    No se encontraron inspecciones.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <div v-if="displayInspections.last_page > 1" class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
                Página {{ displayInspections.current_page }} de {{ displayInspections.last_page }}
            </p>
            <div class="flex gap-1">
                <template v-for="link in displayInspections.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm transition-colors"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                        v-html="link.label"
                        preserve-state
                    />
                    <span
                        v-else
                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-sm text-muted-foreground"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
