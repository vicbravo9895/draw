<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'default' | 'destructive';
    }>(),
    {
        title: '¿Estás seguro?',
        description: 'Esta acción no se puede deshacer.',
        confirmLabel: 'Confirmar',
        cancelLabel: 'Cancelar',
        variant: 'default',
    },
);

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <AlertDialog :open="open" @update:open="(val: boolean) => { if (!val) emit('cancel'); }">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="emit('cancel')">{{ cancelLabel }}</AlertDialogCancel>
                <AlertDialogAction
                    :class="variant === 'destructive' ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : ''"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
