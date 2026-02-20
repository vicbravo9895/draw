<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch, ref, onMounted } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useFlash } from '@/composables/useFlash';
import { CheckCircle, XCircle, X } from 'lucide-vue-next';

const page = usePage();
const { message, clear } = useFlash();

const visible = ref(false);
let hideTimeout: ReturnType<typeof setTimeout> | null = null;

const DURATION_MS = 5000;

function scheduleHide() {
    if (hideTimeout) clearTimeout(hideTimeout);
    hideTimeout = setTimeout(() => {
        visible.value = false;
        setTimeout(() => clear(), 200);
        hideTimeout = null;
    }, DURATION_MS);
}

function showMsg(type: 'success' | 'error', text: string) {
    if (!text) return;
    clear();
    message.value = { type, message: text };
}

// Cualquier cambio en message -> mostrar y programar cierre
watch(
    message,
    (msg) => {
        if (msg) {
            visible.value = true;
            scheduleHide();
        }
    },
    { deep: true },
);

// Flash desde el backend (redirect con session flash)
watch(
    () => (page.props as Record<string, unknown>).flash as { success?: string; error?: string } | undefined,
    (flash) => {
        if (flash?.success) showMsg('success', flash.success);
        if (flash?.error) showMsg('error', flash.error);
    },
    { deep: true, immediate: true },
);

function dismiss() {
    if (hideTimeout) clearTimeout(hideTimeout);
    hideTimeout = null;
    visible.value = false;
    setTimeout(() => clear(), 200);
}

onMounted(() => {
    const flash = (page.props as Record<string, unknown>).flash as { success?: string; error?: string } | undefined;
    if (flash?.success) showMsg('success', flash.success);
    if (flash?.error) showMsg('error', flash.error);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="message && visible"
            class="fixed top-4 right-4 z-[100] w-full max-w-md animate-in fade-in slide-in-from-top-2 duration-200"
        >
            <Alert
                :variant="message.type === 'error' ? 'destructive' : 'default'"
                :class="[
                    'relative shadow-lg',
                    message.type === 'success' && 'border-green-500/50 bg-green-50 text-green-900 dark:border-green-500/30 dark:bg-green-950/80 dark:text-green-100',
                ]"
            >
                <div class="flex items-start gap-3 pr-8">
                    <CheckCircle
                        v-if="message.type === 'success'"
                        class="size-5 shrink-0 text-green-600 dark:text-green-400"
                        aria-hidden
                    />
                    <XCircle
                        v-else
                        class="size-5 shrink-0 text-destructive"
                        aria-hidden
                    />
                    <AlertDescription class="flex-1 pt-0.5">
                        {{ message.message }}
                    </AlertDescription>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="absolute right-2 top-2 h-7 w-7 rounded-md opacity-70 hover:opacity-100"
                    aria-label="Cerrar"
                    @click="dismiss"
                >
                    <X class="size-4" />
                </Button>
            </Alert>
        </div>
    </Teleport>
</template>
