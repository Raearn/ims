<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supervisor Dashboard',
        href: '/supervisor/dashboard',
    },
];
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium uppercase tracking-wider text-muted-foreground">Team Performance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">85%</div>
                        <p class="text-xs text-muted-foreground mt-1">Completion rate this month</p>
                    </CardContent>
                </Card>
            </div>
            
            <div class="flex-1 flex items-center justify-center border-2 border-dashed border-muted rounded-xl bg-muted/20">
                <div class="text-center px-6">
                    <h3 class="text-lg font-semibold mb-2 text-foreground">Welcome Supervisor</h3>
                    <p class="text-muted-foreground max-w-sm">This is your dedicated dashboard. Here you will see team-specific metrics and reported incidents for your department.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
