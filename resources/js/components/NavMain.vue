<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel class="px-2 mb-1 text-[10px] font-bold uppercase tracking-widest text-sidebar-foreground/35">
            Navigation
        </SidebarGroupLabel>
        <SidebarMenu class="space-y-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="item.name ? route().current(item.name) : item.href === page.url"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" v-if="item.icon" />
                        <span class="flex-1 font-medium">{{ item.title }}</span>
                        <!-- Inline badge — flexbox keeps it perfectly centered -->
                        <span
                            v-if="item.badge != null && item.badge > 0"
                            class="ml-auto inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-bold leading-none text-rose-500 tabular-nums group-data-[collapsible=icon]:hidden"
                        >
                            <AlertTriangle class="h-2.5 w-2.5 shrink-0" />
                            {{ item.badge > 99 ? '99+' : item.badge }}
                        </span>
                    </Link>
                </SidebarMenuButton>

            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
