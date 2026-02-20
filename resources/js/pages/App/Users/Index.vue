<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Plus, Search, Pencil, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usuarios', href: '/app/users' }];

const props = defineProps<{
    users: { data: Array<{ id: number; name: string; email: string; employee_number: string; status: string; company_name: string; roles: string[]; last_login_at: string | null }>; total: number; current_page: number; last_page: number; links: Array<{ url: string | null; label: string; active: boolean }> };
    filters: Record<string, string>;
}>();

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { router.get('/app/users', { search: search.value || undefined }, { preserveState: true }); }, 300); });

const confirmDeleteOpen = ref(false);
const deletingId = ref<number | null>(null);

function deleteUser(id: number) {
    deletingId.value = id;
    confirmDeleteOpen.value = true;
}

function confirmDelete() {
    if (deletingId.value === null) return;
    router.delete(`/app/users/${deletingId.value}`, {
        onError: () => useFlash().show({ error: 'Error al eliminar el usuario.' }),
    });
    confirmDeleteOpen.value = false;
    deletingId.value = null;
}
</script>

<template>
    <Head title="Usuarios" />
    <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold tracking-tight">Usuarios</h1>
            <Link href="/app/users/create"><Button><Plus class="mr-2 h-4 w-4" /> Nuevo</Button></Link>
        </div>
        <Card><CardContent class="pt-6"><div class="relative"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input v-model="search" placeholder="Buscar usuario..." class="pl-9" /></div></CardContent></Card>
        <Card><CardContent class="p-0"><table class="w-full text-sm">
            <thead><tr class="border-b bg-muted/50"><th class="px-4 py-3 text-left font-medium">Nombre</th><th class="px-4 py-3 text-left font-medium">Email</th><th class="px-4 py-3 text-left font-medium">Empresa</th><th class="px-4 py-3 text-left font-medium">Rol</th><th class="px-4 py-3 text-left font-medium">Estatus</th><th class="px-4 py-3 text-right font-medium"></th></tr></thead>
            <tbody><tr v-for="u in users.data" :key="u.id" class="border-b hover:bg-muted/50">
                <td class="px-4 py-3 font-medium">{{ u.name }}</td><td class="px-4 py-3 text-muted-foreground">{{ u.email }}</td><td class="px-4 py-3">{{ u.company_name ?? '-' }}</td>
                <td class="px-4 py-3"><Badge v-for="r in u.roles" :key="r" variant="secondary" class="mr-1">{{ r }}</Badge></td>
                <td class="px-4 py-3"><Badge :variant="u.status === 'active' ? 'default' : 'secondary'">{{ u.status }}</Badge></td>
                <td class="px-4 py-3 text-right"><div class="flex justify-end gap-1"><Link :href="`/app/users/${u.id}/edit`"><Button variant="ghost" size="sm"><Pencil class="h-4 w-4" /></Button></Link><Button variant="ghost" size="sm" @click="deleteUser(u.id)"><Trash2 class="h-4 w-4 text-destructive" /></Button></div></td>
            </tr></tbody>
        </table></CardContent></Card>
    </div>
    </AppLayout>

    <ConfirmDialog
        :open="confirmDeleteOpen"
        title="¿Eliminar usuario?"
        description="Esta acción eliminará al usuario de forma permanente. No se puede deshacer."
        confirm-label="Eliminar"
        variant="destructive"
        @confirm="confirmDelete"
        @cancel="confirmDeleteOpen = false"
    />
</template>
