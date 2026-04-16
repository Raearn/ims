<script setup lang="ts">
import { cn } from '@/lib/utils';
import logo from '../../../public/logo.webp';
import logoText from '../../../public/eastwestbpo-logo.png';

interface Props {
    class?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <!-- Collapsed logo -->
    <div :class="cn('hidden group-data-[collapsible=icon]:flex w-full items-center justify-center', props.class)">
        <img :src="logo" alt="Logo" class="size-10 object-contain" />
    </div>
    
    <!-- Expanded logo -->
    <div :class="cn('flex w-full items-center justify-center group-data-[collapsible=icon]:hidden px-1', props.class)">
        <img :src="logoText" alt="EastWest BPO" class="h-auto w-full max-h-16 object-contain py-1" />
    </div>
</template>
