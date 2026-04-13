<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download, Plus } from 'lucide-vue-next';

defineProps<{
    openCount: number;
    primaryQueueLabel: string;
    isExporting: boolean;
    exportDisabled: boolean;
}>();

defineEmits<{
    export: [];
    create: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Incidents</h2>
                <span
                    v-if="openCount > 0"
                    class="inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-500"
                >
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute h-full w-full animate-ping rounded-full bg-rose-400 opacity-75" />
                        <span class="relative h-1.5 w-1.5 rounded-full bg-rose-500" />
                    </span>
                    {{ openCount }} {{ primaryQueueLabel.toLowerCase() }}
                </span>
            </div>
            <p class="text-sm text-muted-foreground">Manage and track all incidents.</p>
        </div>

        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
            <button
                type="button"
                :disabled="exportDisabled"
                class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 text-xs font-semibold text-muted-foreground shadow-sm backdrop-blur-sm transition-colors hover:border-primary/30 hover:bg-background/80 hover:text-foreground disabled:pointer-events-none disabled:opacity-40 sm:flex-initial"
                title="Export current view to Excel"
                @click="$emit('export')"
            >
                <span v-if="isExporting" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                <Download v-else class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                <span>Export</span>
            </button>

            <Button class="h-9 shrink-0 gap-1.5 px-3 shadow-sm shadow-primary/10 sm:px-4" @click="$emit('create')">
                <Plus class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span class="hidden text-xs font-bold uppercase tracking-wide sm:inline">New Incident</span>
                <span class="text-xs font-bold uppercase tracking-wide sm:hidden">New</span>
            </Button>
        </div>
    </div>
</template>
