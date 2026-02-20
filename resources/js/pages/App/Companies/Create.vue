<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFlash } from '@/composables/useFlash';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Empresas', href: '/app/companies' },
    { title: 'Nueva', href: '/app/companies/create' },
];

const form = useForm({
    name: '', public_code: '', status: 'active', timezone: 'America/Mexico_City',
    contact_email: '', allowed_domains: '', allowed_emails: '', notes: '', allow_exports: true,
});

function submit() {
    form.transform((data) => ({
        ...data,
        allowed_domains: data.allowed_domains ? data.allowed_domains.split(',').map((s: string) => s.trim()) : null,
        allowed_emails: data.allowed_emails ? data.allowed_emails.split(',').map((s: string) => s.trim()) : null,
    })).post('/app/companies', {
        onError: () => useFlash().show({ error: 'Error al crear la empresa. Revisa los campos.' }),
    });
}
</script>

<template>
    <Head title="Nueva Empresa" />
    <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <h1 class="text-2xl font-bold tracking-tight">Nueva Empresa</h1>
        <Card><CardHeader><CardTitle class="text-base">Datos de la Empresa</CardTitle></CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2"><Label>Nombre</Label><Input v-model="form.name" /><p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p></div>
                    <div class="space-y-2"><Label>Código Público</Label><Input v-model="form.public_code" placeholder="ACME-001" /><p v-if="form.errors.public_code" class="text-xs text-destructive">{{ form.errors.public_code }}</p></div>
                    <div class="space-y-2"><Label>Email de Contacto</Label><Input v-model="form.contact_email" type="email" /></div>
                    <div class="space-y-2"><Label>Zona Horaria</Label><Input v-model="form.timezone" /></div>
                    <div class="space-y-2"><Label>Dominios Permitidos (coma)</Label><Input v-model="form.allowed_domains" placeholder="empresa.com, empresa.mx" /></div>
                    <div class="space-y-2"><Label>Emails Permitidos (coma)</Label><Input v-model="form.allowed_emails" placeholder="user@empresa.com" /></div>
                    <div class="space-y-2 sm:col-span-2"><Label>Notas</Label><textarea v-model="form.notes" rows="2" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm" /></div>
                    <div class="sm:col-span-2 flex justify-end"><Button type="submit" :disabled="form.processing">Crear Empresa</Button></div>
                </form>
            </CardContent>
        </Card>
    </div>
    </AppLayout>
</template>
