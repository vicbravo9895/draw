<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Combobox } from '@/components/ui/combobox';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Plus, Pencil, Trash2, Search } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Etiquetas de Defecto', href: '/app/defect-tags' }];

const props = defineProps<{
    tags: { data: Array<{ id: number; name: string; category: string | null; is_active: boolean }>; total: number };
    filters: Record<string, string>;
    canManage: boolean;
    companies?: Array<{ id: number; name: string }>;
}>();

const needsCompany = computed(() => !!props.companies?.length);
const companyOptions = computed(() =>
    (props.companies ?? []).map((c) => ({ value: c.id, label: c.name })),
);

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { router.get('/app/defect-tags', { search: search.value || undefined }, { preserveState: true }); }, 300); });

const showForm = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '', category: '', is_active: true, company_id: '' as string | number });

function startCreate() { editingId.value = null; form.reset(); showForm.value = true; }
function startEdit(tag: { id: number; name: string; category: string | null; is_active: boolean }) {
    editingId.value = tag.id; form.name = tag.name; form.category = tag.category ?? ''; form.is_active = tag.is_active; showForm.value = true;
}
function submitForm() {
    if (editingId.value) {
        form.put(`/app/defect-tags/${editingId.value}`, {
            onSuccess: () => { showForm.value = false; },
            onError: () => useFlash().show({ error: 'Error al actualizar la etiqueta.' }),
        });
    } else {
        form.post('/app/defect-tags', {
            onSuccess: () => { showForm.value = false; form.reset(); },
            onError: () => useFlash().show({ error: 'Error al crear la etiqueta.' }),
        });
    }
}

const confirmDeleteOpen = ref(false);
const deletingId = ref<number | null>(null);

function deleteTag(id: number) {
    deletingId.value = id;
    confirmDeleteOpen.value = true;
}

function confirmDelete() {
    if (deletingId.value === null) return;
    router.delete(`/app/defect-tags/${deletingId.value}`, {
        onError: () => useFlash().show({ error: 'Error al eliminar la etiqueta.' }),
    });
    confirmDeleteOpen.value = false;
    deletingId.value = null;
}
</script>

<template>
    <Head title="Etiquetas de Defecto" />
    <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold tracking-tight">Etiquetas de Defecto</h1>
            <Button v-if="canManage" @click="startCreate"><Plus class="mr-2 h-4 w-4" /> Nueva</Button>
        </div>

        <Card v-if="showForm">
            <CardHeader><CardTitle class="text-base">{{ editingId ? 'Editar' : 'Nueva' }} Etiqueta</CardTitle></CardHeader>
            <CardContent>
                <form @submit.prevent="submitForm" class="flex flex-wrap gap-4 items-end">
                    <div v-if="needsCompany && !editingId" class="space-y-2 flex-1 min-w-[180px]">
                        <Label>Empresa</Label>
                        <Combobox v-model="form.company_id" :options="companyOptions" placeholder="Seleccionar empresa..." search-placeholder="Buscar empresa..." />
                        <p v-if="form.errors.company_id" class="text-xs text-destructive">{{ form.errors.company_id }}</p>
                    </div>
                    <div class="space-y-2 flex-1 min-w-[150px]"><Label>Nombre</Label><Input v-model="form.name" /><p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p></div>
                    <div class="space-y-2 flex-1 min-w-[150px]"><Label>Categoría</Label><Input v-model="form.category" /></div>
                    <Button type="submit" :disabled="form.processing">Guardar</Button>
                    <Button variant="ghost" @click="showForm = false">Cancelar</Button>
                </form>
            </CardContent>
        </Card>

        <Card><CardContent class="pt-6"><div class="relative"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input v-model="search" placeholder="Buscar..." class="pl-9" /></div></CardContent></Card>

        <Card><CardContent class="p-0"><table class="w-full text-sm">
            <thead><tr class="border-b bg-muted/50"><th class="px-4 py-3 text-left font-medium">Nombre</th><th class="px-4 py-3 text-left font-medium">Categoría</th><th class="px-4 py-3 text-left font-medium">Estado</th><th v-if="canManage" class="px-4 py-3 text-right font-medium"></th></tr></thead>
            <tbody><tr v-for="tag in tags.data" :key="tag.id" class="border-b hover:bg-muted/50">
                <td class="px-4 py-3 font-medium">{{ tag.name }}</td><td class="px-4 py-3 text-muted-foreground">{{ tag.category ?? '-' }}</td>
                <td class="px-4 py-3"><Badge :variant="tag.is_active ? 'default' : 'secondary'">{{ tag.is_active ? 'Activa' : 'Inactiva' }}</Badge></td>
                <td v-if="canManage" class="px-4 py-3 text-right"><div class="flex justify-end gap-1"><Button variant="ghost" size="sm" @click="startEdit(tag)"><Pencil class="h-4 w-4" /></Button><Button variant="ghost" size="sm" @click="deleteTag(tag.id)"><Trash2 class="h-4 w-4 text-destructive" /></Button></div></td>
            </tr></tbody>
        </table></CardContent></Card>
    </div>
    </AppLayout>

    <ConfirmDialog
        :open="confirmDeleteOpen"
        title="¿Eliminar etiqueta?"
        description="Esta acción eliminará la etiqueta de defecto de forma permanente."
        confirm-label="Eliminar"
        variant="destructive"
        @confirm="confirmDelete"
        @cancel="confirmDeleteOpen = false"
    />
</template>
