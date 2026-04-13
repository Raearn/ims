<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => props.class);

const sidebarInsetClass = computed(() =>
    cn(
        'bg-gradient-to-b from-muted/20 via-background to-background dark:from-muted/[0.06] dark:via-background',
        className.value,
    ),
);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="sidebarInsetClass">
        <slot />
    </SidebarInset>
    <main v-else class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl" :class="className">
        <slot />
    </main>
</template>
