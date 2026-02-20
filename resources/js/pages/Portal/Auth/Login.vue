<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FlashFeedback from '@/components/FlashFeedback.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFlash } from '@/composables/useFlash';

const form = useForm({
    email: '',
    company_code: '',
});

function submit() {
    form.post('/portal/magic-link', {
        onError: () => useFlash().show({ error: 'No se pudo enviar el enlace. Verifica tus datos.' }),
    });
}
</script>

<template>
    <Head title="Portal Empresa - Acceso" />
    <FlashFeedback />
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-md">
            <Card>
                <CardHeader class="text-center">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-lg">
                        <AppLogoIcon class="h-10 w-10" />
                    </div>
                    <CardTitle class="text-2xl">PLUS Services</CardTitle>
                    <p class="text-xs font-medium text-primary">Portal Empresa</p>
                    <CardDescription>
                        Ingresa tu correo corporativo para recibir un enlace de acceso seguro.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="tu@empresa.com"
                                required
                                autofocus
                            />
                            <p v-if="form.errors.email" class="text-sm text-destructive">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="company_code">Código de empresa (opcional)</Label>
                            <Input
                                id="company_code"
                                v-model="form.company_code"
                                type="text"
                                placeholder="Ej: ACME-001"
                            />
                            <p class="text-xs text-muted-foreground">
                                Si conoces el código de tu empresa, ingrésalo para mayor seguridad.
                            </p>
                        </div>

                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Enviando...</span>
                            <span v-else>Enviar enlace de acceso</span>
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
