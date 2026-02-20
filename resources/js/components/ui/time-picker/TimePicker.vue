<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { Clock } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        disabled?: boolean;
        minuteStep?: number;
    }>(),
    {
        placeholder: 'HH:MM',
        disabled: false,
        minuteStep: 5,
    },
);

const model = defineModel<string>({ default: '' });

const open = ref(false);

const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = computed(() =>
    Array.from({ length: Math.floor(60 / props.minuteStep) }, (_, i) =>
        String(i * props.minuteStep).padStart(2, '0'),
    ),
);

const selectedHour = computed(() => {
    if (!model.value) return '';
    return model.value.split(':')[0] ?? '';
});

const selectedMinute = computed(() => {
    if (!model.value) return '';
    return model.value.split(':')[1] ?? '';
});

function selectHour(h: string) {
    const m = selectedMinute.value || '00';
    model.value = `${h}:${m}`;
}

function selectMinute(m: string) {
    const h = selectedHour.value || '08';
    model.value = `${h}:${m}`;
}

const displayValue = computed(() => {
    if (!model.value) return '';
    return model.value;
});

watch(open, (val) => {
    if (val) {
        nextTick(() => {
            // Scroll to selected hour
            const hourEl = document.querySelector(`[data-hour="${selectedHour.value}"]`);
            hourEl?.scrollIntoView({ block: 'center' });
            const minEl = document.querySelector(`[data-minute="${selectedMinute.value}"]`);
            minEl?.scrollIntoView({ block: 'center' });
        });
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                :disabled="disabled"
                class="w-full justify-start font-normal"
                :class="{ 'text-muted-foreground': !displayValue }"
            >
                <Clock class="mr-2 h-4 w-4 shrink-0 opacity-70" />
                <span>{{ displayValue || placeholder }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start">
            <div class="flex divide-x">
                <!-- Hours -->
                <ScrollArea class="h-56 w-20">
                    <div class="p-1">
                        <button
                            v-for="h in hours"
                            :key="h"
                            :data-hour="h"
                            type="button"
                            class="flex w-full items-center justify-center rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90': selectedHour === h,
                            }"
                            @click="selectHour(h)"
                        >
                            {{ h }}
                        </button>
                    </div>
                </ScrollArea>
                <!-- Minutes -->
                <ScrollArea class="h-56 w-20">
                    <div class="p-1">
                        <button
                            v-for="m in minutes"
                            :key="m"
                            :data-minute="m"
                            type="button"
                            class="flex w-full items-center justify-center rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90': selectedMinute === m,
                            }"
                            @click="selectMinute(m)"
                        >
                            {{ m }}
                        </button>
                    </div>
                </ScrollArea>
            </div>
        </PopoverContent>
    </Popover>
</template>
