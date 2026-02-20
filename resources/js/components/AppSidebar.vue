<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ClipboardList, Building2, Users, Tags, BookOpen, Folder } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage();
const permissions = computed(() => (page.props.auth as any)?.permissions ?? []);

function can(permission: string): boolean {
    return permissions.value.includes(permission);
}

const allNavItems: (NavItem & { permission?: string })[] = [
    {
        title: 'Dashboard',
        href: '/app/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Inspecciones',
        href: '/app/inspections',
        icon: ClipboardList,
        permission: 'inspections.view',
    },
    {
        title: 'Empresas',
        href: '/app/companies',
        icon: Building2,
        permission: 'companies.view',
    },
    {
        title: 'Usuarios',
        href: '/app/users',
        icon: Users,
        permission: 'users.view',
    },
    {
        title: 'Etiquetas',
        href: '/app/defect-tags',
        icon: Tags,
        permission: 'defect_tags.manage',
    },
];

const mainNavItems = computed<NavItem[]>(() =>
    allNavItems.filter((item) => !item.permission || can(item.permission)),
);

const footerNavItems: NavItem[] = [
    {
        title: 'Portal Empresa',
        href: '/portal/login',
        icon: Building2,
    },
    {
        title: 'Documentación',
        href: 'https://laravel.com/docs',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/app/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
