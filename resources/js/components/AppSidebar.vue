<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    
    if (user.value?.role === 'admin') {
        items.push({
            title: 'Dashboard',
            href: route('dashboard'),
            icon: LayoutGrid,
            name: 'dashboard',
        });
        items.push({
            title: 'Tickets',
            href: route('tickets'),
            icon: Folder,
            name: 'tickets',
        });
    } else if (user.value?.role === 'supervisor') {
        items.push({
            title: 'Supervisor Dashboard',
            href: route('supervisor.dashboard'),
            icon: LayoutGrid,
            name: 'supervisor.dashboard',
        });
    } else {
        // Default for technical or others if needed
        items.push({
            title: 'Dashboard',
            href: route('dashboard'),
            icon: LayoutGrid,
            name: 'dashboard',
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];

const dashboardRoute = computed(() => {
    if (user.value?.role === 'supervisor') return route('supervisor.dashboard');
    return route('dashboard');
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardRoute">
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
