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

export interface MultiComboboxOption {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        options: MultiComboboxOption[];
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

const model = defineModel<(string | number)[]>({ default: () => [] });

const open = ref(false);

const selectedLabels = computed(() => {
    if (!model.value?.length) return [];
    return model.value
        .map((v) => props.options.find((o) => String(o.value) === String(v))?.label)
        .filter(Boolean) as string[];
});

const displayText = computed(() =>
    selectedLabels.value.length ? selectedLabels.value.join(', ') : '',
);

function toggle(option: MultiComboboxOption) {
    const val = option.value;
    const ids = [...(model.value ?? [])];
    const idx = ids.findIndex((id) => String(id) === String(val));
    if (idx >= 0) {
        ids.splice(idx, 1);
    } else {
        ids.push(val);
    }
    model.value = ids;
}

function isSelected(option: MultiComboboxOption) {
    return (model.value ?? []).some((id) => String(id) === String(option.value));
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
                :class="{ 'text-muted-foreground': !displayText }"
            >
                <span class="truncate">{{ displayText || placeholder }}</span>
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
                            @select="toggle(option)"
                        >
                            <Check
                                :class="cn('mr-2 h-4 w-4', isSelected(option) ? 'opacity-100' : 'opacity-0')"
                            />
                            {{ option.label }}
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
