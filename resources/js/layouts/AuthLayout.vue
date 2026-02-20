<script setup lang="ts">
import FlashFeedback from '@/components/FlashFeedback.vue';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <FlashFeedback />
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
