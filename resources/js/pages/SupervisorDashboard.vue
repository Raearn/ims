<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

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
                <Card
                    class="border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 shadow-sm transition-all duration-300 hover:border-slate-700 hover:shadow-md"
                >
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="font-mono text-sm font-medium uppercase tracking-wider text-slate-400">Team Performance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-slate-50">85%</div>
                        <p class="mt-1 text-xs text-emerald-400">Completion rate this month</p>
                    </CardContent>
                </Card>
            </div>

            <div
                class="group relative flex flex-1 items-center justify-center overflow-hidden rounded-xl border border-slate-800 bg-slate-950/50 shadow-inner backdrop-blur-sm"
            >
                <div
                    class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                ></div>
                <div class="relative z-10 px-6 text-center">
                    <h3 class="mb-3 text-xl font-semibold tracking-tight text-slate-50">Welcome Supervisor</h3>
                    <p class="max-w-sm text-sm leading-relaxed text-slate-400">
                        This is your dedicated dashboard. Here you will see team-specific metrics and reported incidents for your department.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&display=swap');
</style>
