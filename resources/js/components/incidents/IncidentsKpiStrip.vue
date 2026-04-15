<script setup lang="ts">
import type { TicketStatRow } from '@/composables/useIncidentsList';
import { cn } from '@/lib/utils';

const props = defineProps<{
    ticketStats: TicketStatRow[];
    currentStatus: string;
    primaryQueueStatusName: string;
}>();

defineEmits<{
    'update:currentStatus': [value: string];
}>();

function statCardActiveAccentStyle(hex: string): Record<string, string> {
    return {
        borderColor: `${hex}80`,
        boxShadow: `0 10px 28px -8px ${hex}45, 0 0 0 1px ${hex}40`,
    };
}

function shareOfTotal(stat: TicketStatRow): number {
    const total = props.ticketStats[0]?.value ?? 0;
    if (total <= 0) {
        return 0;
    }
    return Math.round((stat.value / total) * 100);
}

function statAriaLabel(stat: TicketStatRow): string {
    const base = `Filter by ${stat.label}, ${stat.value} incidents`;
    if (stat.status === 'All') {
        return base;
    }
    return `${base}, ${shareOfTotal(stat)} percent of total`;
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-3.5 lg:grid-cols-6" role="toolbar" aria-label="Filter incidents by status">
        <button
            v-for="stat in ticketStats"
            :key="stat.label"
            type="button"
            :aria-pressed="currentStatus === stat.status"
            :aria-label="statAriaLabel(stat)"
            :class="
                cn(
                    'group relative flex min-h-[7rem] flex-col overflow-hidden rounded-2xl border bg-card/95 p-3.5 text-left shadow-sm ring-1 ring-border/20 transition-all duration-200 dark:bg-card/80 dark:ring-border/15',
                    'hover:-translate-y-0.5 hover:shadow-md hover:ring-border/35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                    currentStatus === stat.status
                        ? stat.accentHex
                            ? 'z-[1] ring-transparent'
                            : cn('z-[1] ring-2 dark:bg-card', stat.borderActive, stat.glowClass, stat.ringClass)
                        : 'border-border/55 hover:border-primary/25',
                )
            "
            :style="currentStatus === stat.status && stat.accentHex ? statCardActiveAccentStyle(stat.accentHex) : {}"
            @click="$emit('update:currentStatus', stat.status)"
        >
            <div
                class="pointer-events-none absolute -right-5 -top-5 h-20 w-20 rounded-full opacity-[0.12] blur-2xl transition-[opacity,transform] duration-300 group-hover:scale-110 group-hover:opacity-[0.2]"
                :class="stat.accentHex ? '' : stat.bgClass.replace('/10', '/30')"
                :style="stat.accentHex ? { backgroundColor: stat.accentHex } : {}"
                aria-hidden="true"
            />

            <div
                class="absolute inset-y-3 left-0 w-1 rounded-r-full transition-all duration-200"
                :class="[
                    currentStatus === stat.status && !stat.accentHex ? stat.bgClass.replace('/10', '') : '',
                    currentStatus === stat.status ? 'opacity-100' : 'opacity-0',
                ]"
                :style="currentStatus === stat.status && stat.accentHex ? { backgroundColor: stat.accentHex } : {}"
                aria-hidden="true"
            />

            <div class="relative flex flex-1 flex-col gap-2.5">
                <div class="flex items-start gap-3">
                    <div
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border/40 bg-background/70 shadow-sm transition-transform duration-200 group-hover:scale-[1.04] dark:bg-background/30"
                        :class="stat.accentHex ? 'border-transparent' : stat.bgClass"
                        :style="
                            stat.accentHex
                                ? {
                                      backgroundColor: stat.accentHex + '22',
                                      boxShadow: `inset 0 1px 0 ${stat.accentHex}33`,
                                  }
                                : {}
                        "
                    >
                        <component
                            :is="stat.icon"
                            class="h-[18px] w-[18px]"
                            stroke-width="2"
                            :class="stat.accentHex ? '' : stat.colorClass"
                            :style="stat.accentHex ? { color: stat.accentHex } : {}"
                        />
                        <span
                            v-if="stat.status === primaryQueueStatusName && stat.value > 0"
                            class="absolute inset-0 animate-ping rounded-xl opacity-25"
                            :class="stat.accentHex ? '' : stat.bgClass"
                            :style="stat.accentHex ? { backgroundColor: stat.accentHex + '55' } : {}"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <p class="text-xs font-semibold leading-tight text-muted-foreground">{{ stat.label }}</p>
                        <p
                            class="mt-1 text-2xl font-bold tabular-nums leading-none tracking-tight text-foreground"
                            :class="stat.accentHex ? '' : stat.colorClass"
                            :style="stat.accentHex ? { color: stat.accentHex } : {}"
                        >
                            {{ stat.value }}
                        </p>
                    </div>
                </div>

                <div class="mt-auto space-y-1.5">
                    <div class="h-1 overflow-hidden rounded-full bg-muted/60 dark:bg-muted/40">
                        <div
                            v-if="stat.status !== 'All'"
                            class="h-full rounded-full transition-all duration-700 ease-out"
                            :class="stat.accentHex ? '' : stat.bgClass.replace('/10', '/75')"
                            :style="{
                                width: `${shareOfTotal(stat)}%`,
                                ...(stat.accentHex ? { backgroundColor: stat.accentHex + 'cc' } : {}),
                            }"
                        />
                        <div v-else class="h-full w-full rounded-full bg-gradient-to-r from-primary/50 to-primary/25" />
                    </div>
                    <p v-if="stat.status !== 'All'" class="text-[11px] font-medium tabular-nums text-muted-foreground">
                        <span :class="stat.accentHex ? '' : stat.colorClass + '/80'" :style="stat.accentHex ? { color: stat.accentHex + 'dd' } : {}">
                            {{ shareOfTotal(stat) }}%
                        </span>
                        <span class="text-muted-foreground/80"> of total</span>
                    </p>
                    <p v-else class="text-[11px] font-medium text-muted-foreground/90">All incidents in view</p>
                </div>
            </div>
        </button>
    </div>
</template>
