<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, ClipboardList, LogOut } from 'lucide-vue-next';
import FlashFeedback from '@/components/FlashFeedback.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();

const navigation = [
    { name: 'Centro de Control', href: '/portal/dashboard', icon: Activity },
    { name: 'Inspecciones', href: '/portal/inspections', icon: ClipboardList },
];

function isActive(href: string): boolean {
    const url = page.url;
    if (href === '/portal/dashboard') return url === '/portal/dashboard';
    return url.startsWith(href);
}

function logout() {
    router.post('/portal/logout');
}
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <FlashFeedback />
        <!-- Header -->
        <header class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-14 max-w-[1440px] items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <AppLogoIcon class="h-7 w-7" />
                        <div class="flex flex-col leading-none">
                            <span class="text-sm font-bold">PLUS Services</span>
                            <span class="text-[10px] font-medium text-muted-foreground">Portal</span>
                        </div>
                    </div>
                    <nav class="hidden items-center gap-1 md:flex">
                        <Link
                            v-for="item in navigation"
                            :key="item.href"
                            :href="item.href"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="isActive(item.href)
                                ? 'bg-primary/10 text-primary'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                            "
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.name }}
                        </Link>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-muted-foreground sm:inline">
                        {{ (page.props as any).auth?.viewer?.company?.name ?? '' }}
                    </span>
                    <button
                        @click="logout"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        <LogOut class="h-4 w-4" />
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Mobile nav -->
        <div class="border-b md:hidden">
            <nav class="mx-auto flex max-w-[1440px] items-center gap-1 overflow-x-auto px-4 py-2">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="inline-flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                >
                    <component :is="item.icon" class="h-4 w-4" />
                    {{ item.name }}
                </Link>
            </nav>
        </div>

        <!-- Main content -->
        <main class="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="mt-auto border-t py-4">
            <div class="mx-auto max-w-[1440px] px-4 text-center text-xs text-muted-foreground sm:px-6 lg:px-8">
                PLUS Services &mdash; Portal de Inteligencia de Calidad
            </div>
        </footer>
    </div>
</template>
