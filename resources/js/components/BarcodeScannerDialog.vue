<script setup lang="ts">
import { Html5Qrcode } from 'html5-qrcode';
import { ScanLine } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useScanFeedback } from '@/composables/useScanFeedback';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        feedback?: boolean;
    }>(),
    {
        title: 'Escanear código',
        description: 'Apunta la cámara al código QR o de barras. También puedes escribir el valor manualmente.',
        feedback: false,
    },
);

const emit = defineEmits<{
    scan: [value: string];
    'update:open': [value: boolean];
}>();

const { onScanSuccess } = useScanFeedback();

const scannerId = `barcode-scanner-${Math.random().toString(36).slice(2, 9)}`;
const scannerRef = ref<InstanceType<typeof Html5Qrcode> | null>(null);
const errorMessage = ref('');
const isStarting = ref(false);
const scanSuccess = ref(false);

async function startScanner() {
    const el = document.getElementById(scannerId);
    if (!el || scannerRef.value) return;

    errorMessage.value = '';
    isStarting.value = true;
    scanSuccess.value = false;
    const html5QrCode = new Html5Qrcode(scannerId);
    scannerRef.value = html5QrCode;

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 120 },
        aspectRatio: 1.333334,
    };

    try {
        await html5QrCode.start(
            { facingMode: 'environment' },
            config,
            (decodedText) => {
                if (props.feedback) {
                    onScanSuccess();
                    scanSuccess.value = true;
                }
                emit('scan', decodedText.trim());
                emit('update:open', false);
            },
            () => {
                // Ignore scan errors (no code in frame)
            },
        );
    } catch (err) {
        const msg = err instanceof Error ? err.message : 'No se pudo acceder a la cámara';
        errorMessage.value = msg;
        scannerRef.value = null;
    } finally {
        isStarting.value = false;
    }
}

async function stopScanner() {
    if (!scannerRef.value) return;
    try {
        await scannerRef.value.stop();
    } catch {
        // ignore
    }
    scannerRef.value = null;
    errorMessage.value = '';
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            await nextTick();
            await startScanner();
        } else {
            await stopScanner();
        }
    },
);

function handleOpenChange(open: boolean) {
    if (!open) {
        stopScanner();
    }
    emit('update:open', open);
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent :show-close-button="true" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ScanLine class="h-5 w-5" />
                    {{ title }}
                </DialogTitle>
                <DialogDescription>
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-3">
                <div
                    :id="scannerId"
                    class="overflow-hidden rounded-lg border bg-muted/30 min-h-[200px] flex items-center justify-center"
                >
                    <span v-if="isStarting" class="text-sm text-muted-foreground">
                        Iniciando cámara...
                    </span>
                    <span v-else-if="errorMessage" class="px-4 text-center text-sm text-destructive">
                        {{ errorMessage }}
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">
                    Acepta los permisos de cámara si el navegador lo solicita. Funciona con QR y códigos de barras (Code 128, EAN, etc.).
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
