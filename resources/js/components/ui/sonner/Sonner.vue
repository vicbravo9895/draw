<script setup lang="ts">
import { Toaster as Sonner, type ToasterProps } from 'vue-sonner'
import { useColorMode } from '@vueuse/core'
import { computed } from 'vue'

const props = defineProps<Omit<ToasterProps, 'theme'>>()

const { store: mode } = useColorMode()

const theme = computed(() => {
  if (mode.value === 'auto') return undefined
  return mode.value as ToasterProps['theme']
})
</script>

<template>
  <Sonner
    :theme="theme"
    class="toaster group"
    v-bind="props"
    :toast-options="{
      classes: {
        toast:
          'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg',
        description: 'group-[.toast]:text-muted-foreground',
        actionButton:
          'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
        cancelButton:
          'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
      },
    }"
  />
</template>
