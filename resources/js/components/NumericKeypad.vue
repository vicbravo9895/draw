<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        modelValue?: number;
    }>(),
    {
        title: 'Cantidad',
        modelValue: 0,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:modelValue': [value: number];
    confirm: [value: number];
}>();

const display = ref('0');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            display.value = props.modelValue > 0 ? String(props.modelValue) : '0';
        }
    },
);

function pressDigit(digit: string) {
    if (display.value === '0') {
        display.value = digit;
    } else {
        display.value += digit;
    }
}

function pressBackspace() {
    if (display.value.length <= 1) {
        display.value = '0';
    } else {
        display.value = display.value.slice(0, -1);
    }
}

function pressClear() {
    display.value = '0';
}

function pressQuickAdd(amount: number) {
    const current = parseInt(display.value, 10) || 0;
    display.value = String(current + amount);
}

function pressConfirm() {
    const value = parseInt(display.value, 10) || 0;
    emit('update:modelValue', value);
    emit('confirm', value);
    emit('update:open', false);
}

function handleOpenChange(open: boolean) {
    emit('update:open', open);
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            :show-close-button="false"
            class="sm:max-w-sm p-0 gap-0 select-none"
        >
            <DialogHeader class="px-4 pt-4 pb-2">
                <DialogTitle class="text-lg font-bold tracking-tight">{{ title }}</DialogTitle>
                <DialogDescription class="sr-only">Introduce la cantidad usando el teclado numérico</DialogDescription>
            </DialogHeader>

            <!-- Display -->
            <div class="mx-4 mb-3 flex items-center justify-between rounded-lg border-2 border-zinc-300 bg-zinc-50 px-4 dark:border-zinc-600 dark:bg-zinc-800">
                <span
                    class="flex-1 py-3 text-right font-mono text-4xl font-bold tracking-wide text-zinc-900 dark:text-zinc-100"
                >
                    {{ display }}
                </span>
            </div>

            <!-- Quick add buttons -->
            <div class="mx-4 mb-2 grid grid-cols-3 gap-2">
                <button
                    v-for="amount in [10, 50, 100]"
                    :key="amount"
                    type="button"
                    class="flex h-11 items-center justify-center rounded-lg bg-blue-50 text-sm font-semibold text-blue-700 active:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:active:bg-blue-900/50"
                    @click="pressQuickAdd(amount)"
                >
                    +{{ amount }}
                </button>
            </div>

            <!-- Keypad grid -->
            <div class="grid grid-cols-3 gap-px bg-zinc-200 dark:bg-zinc-700">
                <button
                    v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                    :key="digit"
                    type="button"
                    class="flex h-16 items-center justify-center bg-white text-2xl font-semibold text-zinc-900 active:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-100 dark:active:bg-zinc-700"
                    @click="pressDigit(digit)"
                >
                    {{ digit }}
                </button>
                <button
                    type="button"
                    class="flex h-16 items-center justify-center bg-white text-sm font-semibold text-zinc-500 active:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-400 dark:active:bg-zinc-700"
                    @click="pressClear"
                >
                    C
                </button>
                <button
                    type="button"
                    class="flex h-16 items-center justify-center bg-white text-2xl font-semibold text-zinc-900 active:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-100 dark:active:bg-zinc-700"
                    @click="pressDigit('0')"
                >
                    0
                </button>
                <button
                    type="button"
                    class="flex h-16 items-center justify-center bg-white text-xl font-semibold text-zinc-500 active:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-400 dark:active:bg-zinc-700"
                    @click="pressBackspace"
                >
                    &#x232B;
                </button>
            </div>

            <!-- Confirm -->
            <button
                type="button"
                class="flex h-16 w-full items-center justify-center rounded-b-lg bg-emerald-600 text-xl font-bold text-white active:bg-emerald-700"
                @click="pressConfirm"
            >
                Confirmar
            </button>
        </DialogContent>
    </Dialog>
</template>
