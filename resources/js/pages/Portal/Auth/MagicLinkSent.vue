<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    email: string;
}>();
</script>

<template>
    <Head title="Enlace enviado" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-md">
            <Card>
                <CardHeader class="text-center">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                        <MailCheck class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <CardTitle class="text-2xl">Revisa tu correo</CardTitle>
                    <CardDescription>
                        Hemos enviado un enlace de acceso a
                        <strong class="text-foreground">{{ email }}</strong>.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-center text-sm text-muted-foreground">
                        El enlace expira en 15 minutos. Revisa tu bandeja de entrada
                        y haz clic en el enlace para acceder al portal.
                    </p>
                    <div class="flex justify-center">
                        <Link href="/portal/login">
                            <Button variant="outline">Regresar al login</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
