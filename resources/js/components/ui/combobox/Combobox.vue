<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';

export interface ComboboxOption {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        options: ComboboxOption[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        disabled?: boolean;
    }>(),
    {
        placeholder: 'Seleccionar...',
        searchPlaceholder: 'Buscar...',
        emptyText: 'Sin resultados.',
        disabled: false,
    },
);

const model = defineModel<string | number | null>();

const open = ref(false);

const selectedLabel = computed(() => {
    if (model.value === null || model.value === undefined || model.value === '') return '';
    const option = props.options.find((o) => String(o.value) === String(model.value));
    return option?.label ?? '';
});

function select(option: ComboboxOption) {
    model.value = option.value;
    open.value = false;
}

function clear() {
    model.value = null;
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :disabled="disabled"
                class="w-full justify-between font-normal"
                :class="{ 'text-muted-foreground': !selectedLabel }"
            >
                <span class="truncate">{{ selectedLabel || placeholder }}</span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
            <Command>
                <CommandInput :placeholder="searchPlaceholder" />
                <CommandList>
                    <CommandEmpty>{{ emptyText }}</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            v-for="option in options"
                            :key="String(option.value)"
                            :value="option.label"
                            @select="select(option)"
                        >
                            <Check
                                :class="cn('mr-2 h-4 w-4', String(model) === String(option.value) ? 'opacity-100' : 'opacity-0')"
                            />
                            {{ option.label }}
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
