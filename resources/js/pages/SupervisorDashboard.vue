<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supervisor Dashboard',
        href: route('supervisor.dashboard'),
    },
];
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 font-sans">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card class="bg-gradient-to-br from-slate-900 to-slate-950 border-slate-800 shadow-sm transition-all duration-300 hover:shadow-md hover:border-slate-700">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium uppercase tracking-wider text-slate-400 font-mono">Team Performance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-slate-50">85%</div>
                        <p class="text-xs text-emerald-400 mt-1">Completion rate this month</p>
                    </CardContent>
                </Card>
            </div>
            
            <div class="flex-1 flex items-center justify-center border border-slate-800 rounded-xl bg-slate-950/50 backdrop-blur-sm shadow-inner overflow-hidden relative group">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="text-center px-6 relative z-10">
                    <h3 class="text-xl font-semibold mb-3 text-slate-50 tracking-tight">Welcome Supervisor</h3>
                    <p class="text-slate-400 max-w-sm text-sm leading-relaxed">This is your dedicated dashboard. Here you will see team-specific metrics and reported incidents for your department.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&display=swap');
</style>
