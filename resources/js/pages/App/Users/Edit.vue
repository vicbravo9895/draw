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
    { title: 'Editar', href: '#' },
];

const props = defineProps<{
    editUser: {
        id: number; name: string; email: string; company_id: number;
        employee_number: string; phone: string; status: string; role: string;
    };
    companies: Array<{ id: number; name: string }>;
    roles: string[];
}>();

const companyOptions = computed(() =>
    props.companies.map((c) => ({ value: c.id, label: c.name })),
);

const roleOptions = computed(() =>
    props.roles.map((r) => ({ value: r, label: r })),
);

const form = useForm({
    name: props.editUser.name,
    email: props.editUser.email,
    password: '',
    company_id: props.editUser.company_id as string | number,
    employee_number: props.editUser.employee_number ?? '',
    phone: props.editUser.phone ?? '',
    status: props.editUser.status,
    role: props.editUser.role ?? '',
});

function submit() {
    form.put(`/app/users/${props.editUser.id}`, {
        onError: () => useFlash().show({ error: 'Error al actualizar el usuario. Revisa los campos.' }),
    });
}
</script>

<template>
    <Head :title="`Editar ${editUser.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Editar: {{ editUser.name }}</h1>
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Datos del Usuario</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input v-model="form.name" />
                        </div>
                        <div class="space-y-2">
                            <Label>Email</Label>
                            <Input v-model="form.email" type="email" />
                        </div>
                        <div class="space-y-2">
                            <Label>Contraseña (dejar vacío para no cambiar)</Label>
                            <Input v-model="form.password" type="password" />
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
                            <Button type="submit" :disabled="form.processing">Guardar</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
