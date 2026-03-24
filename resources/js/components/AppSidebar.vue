<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Folder, LayoutGrid, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const openTicketsCount = computed(() => page.props.openTicketsCount ?? null);

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
            badge: openTicketsCount.value,
        });
        items.push({
            title: 'Users',
            href: route('users'),
            icon: Users,
            name: 'users',
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
        <SidebarHeader class="px-4 py-4">
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

        <SidebarContent class="px-2 py-2">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="px-4 py-4 border-t border-sidebar-border/40">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
