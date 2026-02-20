<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Pencil, Wifi, WifiOff } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useReverbStatus } from '@/composables/useReverbStatus';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Empresas', href: '/app/companies' }];
const { isOnline } = useReverbStatus();

const props = defineProps<{
    companies: { data: Array<{ id: number; name: string; public_code: string; status: string; contact_email: string; timezone: string }>; current_page: number; last_page: number; total: number; links: Array<{ url: string | null; label: string; active: boolean }> };
    filters: Record<string, string>;
}>();

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { router.get('/app/companies', { search: search.value || undefined }, { preserveState: true }); }, 300); });
</script>

<template>
    <Head title="Empresas" />
    <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight">Empresas</h1>
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <span
                                class="inline-flex items-center justify-center rounded-full p-1.5 transition-colors"
                                :class="isOnline ? 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-400' : 'text-muted-foreground bg-muted'"
                            >
                                <Wifi v-if="isOnline" class="h-4 w-4" />
                                <WifiOff v-else class="h-4 w-4" />
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" class="max-w-xs">
                            <p>{{ isOnline ? 'WebSocket (Reverb): en línea. Actualizaciones en tiempo real activas.' : 'WebSocket (Reverb): sin conexión. Las actualizaciones en tiempo real no están disponibles.' }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
            <Link href="/app/companies/create"><Button><Plus class="mr-2 h-4 w-4" /> Nueva</Button></Link>
        </div>
        <Card><CardContent class="pt-6"><div class="relative"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input v-model="search" placeholder="Buscar empresa..." class="pl-9" /></div></CardContent></Card>
        <Card><CardContent class="p-0"><table class="w-full text-sm">
            <thead><tr class="border-b bg-muted/50"><th class="px-4 py-3 text-left font-medium">Nombre</th><th class="px-4 py-3 text-left font-medium">Código</th><th class="px-4 py-3 text-left font-medium">Email</th><th class="px-4 py-3 text-left font-medium">Estatus</th><th class="px-4 py-3 text-right font-medium"></th></tr></thead>
            <tbody><tr v-for="c in companies.data" :key="c.id" class="border-b hover:bg-muted/50">
                <td class="px-4 py-3 font-medium">{{ c.name }}</td><td class="px-4 py-3">{{ c.public_code }}</td><td class="px-4 py-3 text-muted-foreground">{{ c.contact_email }}</td>
                <td class="px-4 py-3"><Badge :variant="c.status === 'active' ? 'default' : 'secondary'">{{ c.status }}</Badge></td>
                <td class="px-4 py-3 text-right"><Link :href="`/app/companies/${c.id}/edit`"><Button variant="ghost" size="sm"><Pencil class="h-4 w-4" /></Button></Link></td>
            </tr></tbody>
        </table></CardContent></Card>
    </div>
    </AppLayout>
</template>
