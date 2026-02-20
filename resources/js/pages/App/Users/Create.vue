<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Combobox } from '@/components/ui/combobox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { computed } from 'vue';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuarios', href: '/app/users' },
    { title: 'Nuevo', href: '/app/users/create' },
];

const props = defineProps<{
    companies: Array<{ id: number; name: string }>;
    roles: string[];
    defaultCompanyId: number | null;
}>();

const companyOptions = computed(() =>
    props.companies.map((c) => ({ value: c.id, label: c.name })),
);

const roleOptions = computed(() =>
    props.roles.map((r) => ({ value: r, label: r })),
);

const form = useForm({
    name: '',
    email: '',
    password: '',
    company_id: props.defaultCompanyId ?? ('' as string | number),
    employee_number: '',
    phone: '',
    status: 'active',
    role: props.roles[0] ?? '',
});

function submit() {
    form.post('/app/users', {
        onError: () => useFlash().show({ error: 'Error al crear el usuario. Revisa los campos.' }),
    });
}
</script>

<template>
    <Head title="Nuevo Usuario" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Nuevo Usuario</h1>
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Datos del Usuario</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input v-model="form.name" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Email</Label>
                            <Input v-model="form.email" type="email" />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Contraseña</Label>
                            <Input v-model="form.password" type="password" />
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Empresa</Label>
                            <Combobox
                                v-model="form.company_id"
                                :options="companyOptions"
                                placeholder="Seleccionar empresa..."
                                search-placeholder="Buscar empresa..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>No. Empleado</Label>
                            <Input v-model="form.employee_number" />
                        </div>
                        <div class="space-y-2">
                            <Label>Teléfono</Label>
                            <Input v-model="form.phone" />
                        </div>
                        <div class="space-y-2">
                            <Label>Rol</Label>
                            <Combobox
                                v-model="form.role"
                                :options="roleOptions"
                                placeholder="Seleccionar rol..."
                                search-placeholder="Buscar rol..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Estatus</Label>
                            <Select v-model="form.status">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Seleccionar estatus" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="inactive">Inactivo</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex justify-end sm:col-span-2">
                            <Button type="submit" :disabled="form.processing">Crear Usuario</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
