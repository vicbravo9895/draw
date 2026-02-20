<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Plus, Search, Trash2, Pencil, Eye } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inspecciones', href: '/app/inspections' }];

const page = usePage();
const permissions = computed(() => (page.props.auth as any)?.permissions ?? []);
const roles = computed(() => (page.props.auth as any)?.roles ?? []);
const isInspector = computed(() => roles.value.includes('inspector'));
const canCreate = computed(() => permissions.value.includes('inspections.create') && !isInspector.value);
const canDelete = computed(() => permissions.value.includes('inspections.delete'));

const props = defineProps<{
    inspections: {
        data: Array<{
            id: number; reference_code: string; company_name: string;
            date: string; shift: string; project: string; area_line: string; status: string;
            scheduled_by: string | null; inspector: string | null;
            total_good: number; total_defects: number; total: number; defect_rate: number;
        }>;
        current_page: number; last_page: number; total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
    companies: Array<{ id: number; name: string }>;
}>();

const statusLabels: Record<string, string> = { pending: 'Pendiente', in_progress: 'En Progreso', completed: 'Completada' };
const statusColors: Record<string, string> = { pending: 'secondary', in_progress: 'default', completed: 'outline' };

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status || 'all');
let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/app/inspections', {
            search: search.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        }, { preserveState: true, preserveScroll: true });
    }, 300);
}

watch([search, statusFilter], applyFilters);

const confirmDeleteOpen = ref(false);
const deletingId = ref<number | null>(null);

function deleteInspection(id: number) {
    deletingId.value = id;
    confirmDeleteOpen.value = true;
}

function confirmDelete() {
    if (deletingId.value === null) return;
    router.delete(`/app/inspections/${deletingId.value}`, {
        onError: () => useFlash().show({ error: 'Error al eliminar la inspección.' }),
    });
    confirmDeleteOpen.value = false;
    deletingId.value = null;
}
</script>

<template>
    <Head title="Inspecciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Inspecciones</h1>
                <p class="text-muted-foreground">{{ inspections.total }} registros</p>
            </div>
            <Link v-if="canCreate" href="/app/inspections/create">
                <Button><Plus class="mr-2 h-4 w-4" /> Nueva</Button>
            </Link>
        </div>

        <Card>
            <CardContent class="pt-6">
                <div class="flex gap-4">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Buscar..." class="pl-9" />
                    </div>
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="Todos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="pending">Pendiente</SelectItem>
                            <SelectItem value="in_progress">En Progreso</SelectItem>
                            <SelectItem value="completed">Completada</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-3 text-left font-medium">Ref.</th>
                                <th class="px-4 py-3 text-left font-medium">Empresa</th>
                                <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                <th class="px-4 py-3 text-left font-medium">Proyecto</th>
                                <th class="px-4 py-3 text-left font-medium">Estatus</th>
                                <th class="px-4 py-3 text-right font-medium">Buenas</th>
                                <th class="px-4 py-3 text-right font-medium">Malas</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                                <th class="px-4 py-3 text-right font-medium">%</th>
                                <th class="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ins in inspections.data" :key="ins.id" class="border-b hover:bg-muted/50">
                                <td class="px-4 py-3 font-medium">{{ ins.reference_code }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ ins.company_name }}</td>
                                <td class="px-4 py-3">{{ ins.date }}</td>
                                <td class="px-4 py-3">{{ ins.project }}</td>
                                <td class="px-4 py-3"><Badge :variant="(statusColors[ins.status] as any)">{{ statusLabels[ins.status] }}</Badge></td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ ins.total_good.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-destructive">{{ ins.total_defects }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">{{ ins.total.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ ins.defect_rate }}%</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1">
                                        <Link :href="`/app/inspections/${ins.id}`"><Button variant="ghost" size="sm"><Eye class="h-4 w-4" /></Button></Link>
                                        <Link v-if="ins.status !== 'completed'" :href="`/app/inspections/${ins.id}/edit`"><Button variant="ghost" size="sm"><Pencil class="h-4 w-4" /></Button></Link>
                                        <Button v-if="ins.status === 'pending' && canDelete" variant="ghost" size="sm" @click="deleteInspection(ins.id)"><Trash2 class="h-4 w-4 text-destructive" /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="inspections.last_page > 1" class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">Pág. {{ inspections.current_page }} de {{ inspections.last_page }}</p>
            <div class="flex gap-1">
                <template v-for="link in inspections.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm" :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'" v-html="link.label" preserve-state />
                </template>
            </div>
        </div>
    </div>
    </AppLayout>

    <ConfirmDialog
        :open="confirmDeleteOpen"
        title="¿Eliminar inspección?"
        description="Esta acción eliminará la inspección de forma permanente. No se puede deshacer."
        confirm-label="Eliminar"
        variant="destructive"
        @confirm="confirmDelete"
        @cancel="confirmDeleteOpen = false"
    />
</template>
