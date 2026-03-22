<script setup lang="ts">
import { useDark, useToggle } from '@vueuse/core';
import { Moon, Sun } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const isDark = useDark();
const toggleDark = useToggle(isDark);
</script>

<template>
    <TooltipProvider :delay-duration="100">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button variant="ghost" size="icon" class="h-9 w-9" @click="toggleDark()">
                    <Sun v-if="isDark" class="h-[1.2rem] w-[1.2rem] transition-all" />
                    <Moon v-else class="h-[1.2rem] w-[1.2rem] transition-all" />
                    <span class="sr-only">Toggle theme</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>Toggle theme</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
