<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex aspect-square size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:rounded-md"
    >
        <AppLogoIcon class="size-5 fill-current group-data-[collapsible=icon]:size-4" />
    </div>
    <div class="ml-3 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
        <span class="mb-0.5 truncate font-bold leading-none tracking-tight text-foreground uppercase">EastWest IMS</span>
        <span class="truncate text-[10px] text-muted-foreground font-medium uppercase tracking-widest">Management System</span>
    </div>
</template>
