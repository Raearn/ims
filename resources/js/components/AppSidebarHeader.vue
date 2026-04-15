<script setup lang="ts">
import NotificationBell from '@/components/NotificationBell.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Plus, Ticket, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<SharedData>();
const canManageUsers = computed(() => page.props.auth.user?.role === 'admin');
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/60 bg-background/80 px-6 shadow-sm shadow-black/[0.02] backdrop-blur-md transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 supports-[backdrop-filter]:bg-background/70 dark:border-sidebar-border/50 dark:shadow-black/20 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink as-child>
                                        <Link :href="item.href">
                                            {{ item.title }}
                                        </Link>
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>
        <div class="flex items-center gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="relative h-9 w-9 cursor-pointer hover:bg-muted focus-visible:ring-1">
                        <Plus class="size-5" />
                        <span class="sr-only">Quick Actions</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuItem as-child class="cursor-pointer">
                        <Link :href="route('tickets', { create: 'true' })" class="flex w-full items-center">
                            <Ticket class="mr-2 h-4 w-4" />
                            <span>Add New Ticket</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="canManageUsers" as-child class="cursor-pointer">
                        <Link :href="route('users', { create: 'true' })" class="flex w-full items-center">
                            <UserPlus class="mr-2 h-4 w-4" />
                            <span>Add New User</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <NotificationBell />
            <ThemeToggle />
        </div>
    </header>
</template>
