<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Cpu } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Diagnostics', href: route('diagnostics') },
    { title: 'PHP Info', href: route('diagnostics.phpinfo') },
];
</script>

<template>
    <Head title="PHP Info" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 pb-20 md:p-6">
            
            <div class="flex items-center gap-4">
                <Link
                    :href="route('diagnostics')"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground">
                        <Cpu class="h-6 w-6 text-primary" />
                        PHP Info
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">Full PHP configuration and environment details.</p>
                </div>
            </div>

            <div class="flex-1 overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm h-[800px]">
                <iframe
                    :src="route('diagnostics.phpinfo.data')"
                    class="w-full h-full border-none"
                    title="PHP Info"
                ></iframe>
            </div>

        </div>
    </AppLayout>
</template>
