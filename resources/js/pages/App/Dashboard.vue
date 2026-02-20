<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ClipboardList, ClipboardCheck, AlertTriangle, TrendingUp, Plus } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/app/dashboard' }];

const page = usePage();
const roles = computed(() => (page.props.auth as any)?.roles ?? []);
const isInspector = computed(() => roles.value.includes('inspector'));

defineProps<{
    stats: {
        total_inspections: number;
        active_inspections: number;
        completed_today: number;
        month_good: number;
        month_defects: number;
        month_total: number;
        month_defect_rate: number;
    };
    recentInspections: Array<{
        id: number;
        reference_code: string;
        company_name: string;
        date: string;
        status: string;
        project: string;
        inspector: string | null;
        updated_at: string;
    }>;
}>();

const statusLabels: Record<string, string> = { pending: 'Pendiente', in_progress: 'En Progreso', completed: 'Completada' };
const statusColors: Record<string, string> = { pending: 'secondary', in_progress: 'default', completed: 'outline' };
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
            <Link v-if="!isInspector" href="/app/inspections/create">
                <Button><Plus class="mr-2 h-4 w-4" /> Nueva Inspección</Button>
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Inspecciones</CardTitle>
                    <ClipboardList class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_inspections }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">En Progreso</CardTitle>
                    <ClipboardCheck class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.active_inspections }}</div>
                    <p class="text-xs text-muted-foreground">{{ stats.completed_today }} completadas hoy</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Piezas (Mes)</CardTitle>
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.month_total.toLocaleString('es-MX') }}</div>
                    <p class="text-xs text-muted-foreground">{{ stats.month_good.toLocaleString('es-MX') }} buenas &middot; {{ stats.month_defects.toLocaleString('es-MX') }} malas</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">% Defectos (Mes)</CardTitle>
                    <AlertTriangle class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.month_defect_rate }}%</div>
                    <p class="text-xs text-muted-foreground">{{ stats.month_defects.toLocaleString('es-MX') }} defectos</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Inspecciones Recientes</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-3 text-left font-medium">Referencia</th>
                                <th class="px-4 py-3 text-left font-medium">Empresa</th>
                                <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                <th class="px-4 py-3 text-left font-medium">Proyecto</th>
                                <th class="px-4 py-3 text-left font-medium">Inspector</th>
                                <th class="px-4 py-3 text-left font-medium">Estatus</th>
                                <th class="px-4 py-3 text-left font-medium">Actualizado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ins in recentInspections" :key="ins.id" class="border-b hover:bg-muted/50">
                                <td class="px-4 py-3">
                                    <Link :href="`/app/inspections/${ins.id}`" class="font-medium text-primary hover:underline">{{ ins.reference_code }}</Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ ins.company_name }}</td>
                                <td class="px-4 py-3">{{ ins.date }}</td>
                                <td class="px-4 py-3">{{ ins.project }}</td>
                                <td class="px-4 py-3">{{ ins.inspector ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="(statusColors[ins.status] as any) ?? 'secondary'">{{ statusLabels[ins.status] ?? ins.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ ins.updated_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
    </AppLayout>
</template>
