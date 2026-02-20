<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Empresas', href: '/app/companies' },
    { title: 'Editar', href: '#' },
];

const props = defineProps<{
    company: {
        id: number; name: string; public_code: string; status: string;
        timezone: string; contact_email: string;
        allowed_domains: string[] | null; allowed_emails: string[] | null;
        notes: string; allow_exports: boolean;
    };
    readonly?: boolean;
}>();

const form = useForm({
    name: props.company.name,
    public_code: props.company.public_code,
    status: props.company.status,
    timezone: props.company.timezone,
    contact_email: props.company.contact_email ?? '',
    allowed_domains: props.company.allowed_domains?.join(', ') ?? '',
    allowed_emails: props.company.allowed_emails?.join(', ') ?? '',
    notes: props.company.notes ?? '',
    allow_exports: props.company.allow_exports,
});

function submit() {
    form.transform((data) => ({
        ...data,
        allowed_domains: data.allowed_domains
            ? data.allowed_domains.split(',').map((s: string) => s.trim())
            : null,
        allowed_emails: data.allowed_emails
            ? data.allowed_emails.split(',').map((s: string) => s.trim())
            : null,
    })).put(`/app/companies/${props.company.id}`, {
        onError: () => useFlash().show({ error: 'Error al actualizar la empresa. Revisa los campos.' }),
    });
}
</script>

<template>
    <Head :title="`Editar ${company.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ readonly ? 'Ver' : 'Editar' }}: {{ company.name }}</h1>
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Datos de la Empresa</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input v-model="form.name" :disabled="readonly" />
                        </div>
                        <div class="space-y-2">
                            <Label>Código</Label>
                            <Input v-model="form.public_code" :disabled="readonly" />
                        </div>
                        <div class="space-y-2">
                            <Label>Email</Label>
                            <Input v-model="form.contact_email" :disabled="readonly" />
                        </div>
                        <div class="space-y-2">
                            <Label>Zona Horaria</Label>
                            <Input v-model="form.timezone" :disabled="readonly" />
                        </div>
                        <div class="space-y-2">
                            <Label>Estatus</Label>
                            <Select v-model="form.status" :disabled="readonly">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Seleccionar estatus" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Activa</SelectItem>
                                    <SelectItem value="inactive">Inactiva</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Dominios</Label>
                            <Input v-model="form.allowed_domains" :disabled="readonly" />
                        </div>
                        <div class="space-y-2">
                            <Label>Emails</Label>
                            <Input v-model="form.allowed_emails" :disabled="readonly" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label>Notas</Label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                :disabled="readonly"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                            />
                        </div>
                        <div v-if="!readonly" class="flex justify-end sm:col-span-2">
                            <Button type="submit" :disabled="form.processing">Guardar</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
