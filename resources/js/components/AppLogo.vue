<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-3 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-bold leading-none tracking-tight text-foreground uppercase">EastWest IMS</span>
        <span class="truncate text-[10px] text-muted-foreground font-medium uppercase tracking-widest">Management System</span>
    </div>
</template>
