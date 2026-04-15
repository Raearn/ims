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
import { Activity, AlertTriangle, Folder, LayoutGrid, Lightbulb, ScrollText, Settings, UserRound, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);

/** Match backend roles even if DB/legacy rows differ in casing or whitespace. */
const userRole = computed(() =>
    String(user.value?.role ?? '')
        .trim()
        .toLowerCase(),
);

const isAdminOrSupervisor = computed(() => userRole.value === 'admin' || userRole.value === 'supervisor');

const isAdminOnly = computed(() => userRole.value === 'admin');

const openTicketsCount = computed(() => page.props.openTicketsCount ?? null);

const roleBadgeClasses = computed(() => {
    const map: Record<string, string> = {
        admin: 'border-rose-500/20 bg-rose-500/10 text-rose-500',
        supervisor: 'border-amber-500/20 bg-amber-500/10 text-amber-500',
        technical: 'border-blue-500/20 bg-blue-500/10 text-blue-500',
    };
    return map[userRole.value] ?? 'border-border/50 bg-muted text-muted-foreground';
});

const homeRoute = computed(() => {
    if (isAdminOrSupervisor.value) {
        return route('dashboard');
    }
    if (userRole.value === 'technical') {
        return route('home');
    }

    return route('profile.edit');
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <!-- ── Header ─────────────────────────────────────────────────── -->
        <SidebarHeader class="border-b border-sidebar-border/40 px-3 py-3 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:py-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeRoute">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- ── Content ────────────────────────────────────────────────── -->
        <SidebarContent class="gap-0 px-2 py-3 group-data-[collapsible=icon]:px-0">
            <!-- ┌─ ADMIN + SUPERVISOR (dashboard & incidents) ─────────── -->
            <template v-if="isAdminOrSupervisor">
                <!-- Overview -->
                <SidebarGroup class="px-2 pb-1 group-data-[collapsible=icon]:px-0">
                    <SidebarGroupLabel
                        class="mb-1 px-1 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden"
                    >
                        Overview
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child tooltip="Dashboard" :is-active="route().current('dashboard')">
                                    <Link :href="route('dashboard')" aria-label="Dashboard">
                                        <LayoutGrid />
                                        <span>Dashboard</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator class="mx-2 my-0.5 group-data-[collapsible=icon]:hidden" />

                <!-- Helpdesk -->
                <SidebarGroup class="px-2 py-1 group-data-[collapsible=icon]:px-0">
                    <SidebarGroupLabel
                        class="mb-1 px-1 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden"
                    >
                        Helpdesk
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child tooltip="Incidents" :is-active="route().current('tickets')">
                                    <Link :href="route('tickets')" aria-label="Incidents">
                                        <Folder />
                                        <span class="flex-1">Incidents</span>
                                        <span
                                            v-if="openTicketsCount && openTicketsCount > 0"
                                            class="ml-auto inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-bold tabular-nums leading-none text-rose-500 group-data-[collapsible=icon]:hidden"
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

                <template v-if="isAdminOnly">
                    <SidebarSeparator class="mx-2 my-0.5 group-data-[collapsible=icon]:hidden" />

                    <!-- Administration -->
                    <SidebarGroup class="px-2 pt-1 group-data-[collapsible=icon]:px-0">
                        <SidebarGroupLabel
                            class="mb-1 px-1 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden"
                        >
                            Administration
                        </SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu class="space-y-0.5">
                                <SidebarMenuItem>
                                    <SidebarMenuButton as-child :is-active="route().current('users')">
                                        <Link :href="route('users')">
                                            <Users />
                                            <span>Users</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                                <SidebarMenuItem>
                                    <SidebarMenuButton as-child :is-active="route().current('audit-log')">
                                        <Link :href="route('audit-log')">
                                            <ScrollText />
                                            <span>Audit Log</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                                <SidebarMenuItem>
                                    <SidebarMenuButton as-child :is-active="route().current('admin.solutions')">
                                        <Link :href="route('admin.solutions')">
                                            <Lightbulb />
                                            <span>Solutions</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                                <SidebarMenuItem>
                                    <SidebarMenuButton as-child :is-active="route().current('diagnostics')">
                                        <Link :href="route('diagnostics')">
                                            <Activity />
                                            <span>Diagnostics</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                                <SidebarMenuItem>
                                    <SidebarMenuButton as-child :is-active="route().current('admin.settings')">
                                        <Link :href="route('admin.settings')">
                                            <Settings />
                                            <span>Settings</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                </template>
            </template>
            <!-- └────────────────────────────────────────────────────── -->

            <!-- ┌─ TECHNICAL / FALLBACK ──────────────────────────────── -->
            <template v-else>
                <SidebarGroup class="px-2 pb-1 group-data-[collapsible=icon]:px-0">
                    <SidebarGroupLabel
                        class="mb-1 px-1 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden"
                    >
                        Helpdesk
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child tooltip="Incidents" :is-active="route().current('home')">
                                    <Link :href="route('home')" aria-label="Incidents">
                                        <Folder />
                                        <span class="flex-1">Incidents</span>
                                        <span
                                            v-if="openTicketsCount && openTicketsCount > 0"
                                            class="ml-auto inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-bold tabular-nums leading-none text-rose-500 group-data-[collapsible=icon]:hidden"
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

                <SidebarGroup class="px-2 pb-1 group-data-[collapsible=icon]:px-0">
                    <SidebarGroupLabel
                        class="mb-1 px-1 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden"
                    >
                        Account
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="space-y-0.5">
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child tooltip="Profile" :is-active="route().current('profile.edit')">
                                    <Link :href="route('profile.edit')" aria-label="Profile">
                                        <UserRound />
                                        <span>Profile</span>
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
        <SidebarFooter class="border-t border-sidebar-border/40 px-3 py-3 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:py-2">
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
