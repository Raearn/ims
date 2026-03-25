<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Folder, LayoutGrid, ScrollText, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const openTicketsCount = computed(() => page.props.openTicketsCount ?? null);

const roleBadgeClasses = computed(() => {
    const map: Record<string, string> = {
        admin:      'border-rose-500/20 bg-rose-500/10 text-rose-500',
        supervisor: 'border-amber-500/20 bg-amber-500/10 text-amber-500',
        technical:  'border-blue-500/20 bg-blue-500/10 text-blue-500',
    };
    return map[user.value?.role ?? ''] ?? 'border-border/50 bg-muted text-muted-foreground';
});

const dashboardRoute = computed(() =>
    user.value?.role === 'supervisor' ? route('supervisor.dashboard') : route('dashboard'),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">

        <!-- ── Header ─────────────────────────────────────────────────── -->
        <SidebarHeader class="border-b border-sidebar-border/40 px-3 py-3">
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

        <!-- ── Content ────────────────────────────────────────────────── -->
        <SidebarContent class="gap-0 px-2 py-3">

            <!-- ┌─ ADMIN ─────────────────────────────────────────────── -->
            <template v-if="user?.role === 'admin'">

                <!-- Overview -->
                <SidebarGroup class="px-2 pb-1">
                    <SidebarGroupLabel class="mb-1 px-1 text-[10px] font-bold uppercase tracking-widest text-sidebar-foreground/35 group-data-[collapsible=icon]:hidden">
                        Overview
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child :is-active="route().current('dashboard')">
                                    <Link :href="route('dashboard')">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-sidebar-accent shadow-sm ring-1 ring-sidebar-border/50 group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7">
                                            <LayoutGrid class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="font-medium">Dashboard</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator class="mx-2 my-0.5 group-data-[collapsible=icon]:hidden" />

                <!-- Helpdesk -->
                <SidebarGroup class="px-2 py-1">
                    <SidebarGroupLabel class="mb-1 px-1 text-[10px] font-bold uppercase tracking-widest text-sidebar-foreground/35 group-data-[collapsible=icon]:hidden">
                        Helpdesk
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child :is-active="route().current('tickets')">
                                    <Link :href="route('tickets')">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-sidebar-accent shadow-sm ring-1 ring-sidebar-border/50 group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7">
                                            <Folder class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="flex-1 font-medium">Tickets</span>
                                        <span
                                            v-if="openTicketsCount && openTicketsCount > 0"
                                            class="ml-auto inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-bold leading-none text-rose-500 tabular-nums group-data-[collapsible=icon]:hidden"
                                        >
                                            <AlertTriangle class="h-2.5 w-2.5 shrink-0" />
                                            {{ openTicketsCount > 99 ? '99+' : openTicketsCount }}
                                        </span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator class="mx-2 my-0.5 group-data-[collapsible=icon]:hidden" />

                <!-- Administration -->
                <SidebarGroup class="px-2 pt-1">
                    <SidebarGroupLabel class="mb-1 px-1 text-[10px] font-bold uppercase tracking-widest text-sidebar-foreground/35 group-data-[collapsible=icon]:hidden">
                        Administration
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child :is-active="route().current('users')">
                                    <Link :href="route('users')">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-sidebar-accent shadow-sm ring-1 ring-sidebar-border/50 group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7">
                                            <Users class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="font-medium">Users</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child :is-active="route().current('audit-log')">
                                    <Link :href="route('audit-log')">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-sidebar-accent shadow-sm ring-1 ring-sidebar-border/50 group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7">
                                            <ScrollText class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="font-medium">Audit Log</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

            </template>
            <!-- └────────────────────────────────────────────────────── -->

            <!-- ┌─ SUPERVISOR ────────────────────────────────────────── -->
            <template v-else-if="user?.role === 'supervisor'">
                <SidebarGroup class="px-2 pb-1">
                    <SidebarGroupLabel class="mb-1 px-1 text-[10px] font-bold uppercase tracking-widest text-sidebar-foreground/35 group-data-[collapsible=icon]:hidden">
                        Overview
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child :is-active="route().current('supervisor.dashboard')">
                                    <Link :href="route('supervisor.dashboard')">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-sidebar-accent shadow-sm ring-1 ring-sidebar-border/50 group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7">
                                            <LayoutGrid class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="font-medium">Dashboard</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>
            <!-- └────────────────────────────────────────────────────── -->

            <!-- ┌─ TECHNICAL / FALLBACK ──────────────────────────────── -->
            <template v-else>
                <SidebarGroup class="px-2 pb-1">
                    <SidebarGroupLabel class="mb-1 px-1 text-[10px] font-bold uppercase tracking-widest text-sidebar-foreground/35 group-data-[collapsible=icon]:hidden">
                        Overview
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child :is-active="route().current('dashboard')">
                                    <Link :href="route('dashboard')">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-sidebar-accent shadow-sm ring-1 ring-sidebar-border/50 group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7">
                                            <LayoutGrid class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="font-medium">Dashboard</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>
            <!-- └────────────────────────────────────────────────────── -->

        </SidebarContent>

        <!-- ── Footer ─────────────────────────────────────────────────── -->
        <SidebarFooter class="border-t border-sidebar-border/40 px-3 py-3">
            <div class="mb-2 px-1 group-data-[collapsible=icon]:hidden">
                <span
                    :class="[
                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                        roleBadgeClasses,
                    ]"
                >
                    {{ user?.role }}
                </span>
            </div>
            <NavUser />
        </SidebarFooter>

    </Sidebar>
    <slot />
</template>
