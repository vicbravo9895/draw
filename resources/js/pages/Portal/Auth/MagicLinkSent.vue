<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MailCheck, Copy, Check, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    email: string;
    magic_link_url: string | null;
}>();

const copied = ref(false);

async function copyLink() {
    if (!props.magic_link_url) return;
    try {
        await navigator.clipboard.writeText(props.magic_link_url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // fallback: select the input so user can Cmd+C
        const input = document.getElementById('magic-link-input') as HTMLInputElement;
        input?.select();
    }
}
</script>

<template>
    <Head title="Enlace enviado" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-lg">
            <Card>
                <CardHeader class="text-center">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                        <MailCheck class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <CardTitle class="text-2xl">Enlace de acceso generado</CardTitle>
                    <CardDescription>
                        <span v-if="email">
                            Hemos enviado también un enlace a
                            <strong class="text-foreground">{{ email }}</strong>.
                        </span>
                        <span v-else>
                            Usa el enlace de abajo para acceder al portal.
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Mostrar el enlace en pantalla -->
                    <div v-if="magic_link_url" class="space-y-2">
                        <p class="text-center text-sm font-medium text-muted-foreground">
                            Enlace de acceso (válido 15 minutos):
                        </p>
                        <div class="flex gap-2">
                            <Input
                                id="magic-link-input"
                                :model-value="magic_link_url"
                                type="text"
                                readonly
                                class="font-mono text-xs select-all"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="shrink-0"
                                title="Copiar enlace"
                                @click="copyLink"
                            >
                                <Check v-if="copied" class="h-4 w-4 text-green-600" />
                                <Copy v-else class="h-4 w-4" />
                            </Button>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2">
                            <Button as-child>
                                <a :href="magic_link_url" target="_blank" rel="noopener noreferrer">
                                    <ExternalLink class="mr-2 h-4 w-4" />
                                    Abrir portal
                                </a>
                            </Button>
                            <Button variant="outline" as-child>
                                <Link href="/portal/login">Regresar al login</Link>
                            </Button>
                        </div>
                    </div>

                    <p v-else class="text-center text-sm text-muted-foreground">
                        El enlace expira en 15 minutos. Revisa tu bandeja de entrada
                        y haz clic en el enlace para acceder al portal.
                    </p>

                    <div v-if="!magic_link_url" class="flex justify-center">
                        <Link href="/portal/login">
                            <Button variant="outline">Regresar al login</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
