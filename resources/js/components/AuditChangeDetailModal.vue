<script setup lang="ts">
import AuditConfigChangeBlock from '@/components/AuditConfigChangeBlock.vue';
import TicketDetailBody from '@/components/TicketDetailBody.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { laravelFetch } from '@/lib/laravelFetch';
import type { TicketDetail } from '@/types/ticketDetail';
import { ArrowLeftRight, Clock, Layers, LayoutList, ScrollText, Ticket, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface AuditChangeDetailEntry {
    action: string;
    actionLabel: string;
    oldValue: string | null;
    newValue: string | null;
    userName: string;
    userRole: string | null;
    createdAtFormatted: string;
    createdAtRelative: string;
    ticketId: number | null;
    ticketTktId: string;
    ticketTitle: string;
}

const props = defineProps<{
    modelValue: boolean;
    entry: AuditChangeDetailEntry | null;
    statuses: { id: number; name: string; icon: string; color: string; handler_requirement?: string }[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const ticketLoading = ref(false);
const ticketDetail = ref<TicketDetail | null>(null);
const ticketPriorities = ref<{ id: number; name: string; icon: string; color: string }[]>([]);

function close(): void {
    emit('update:modelValue', false);
}

const usesTicketConfigChangeBlock = computed(() => {
    const action = props.entry?.action;
    if (!action) {
        return false;
    }

    return action === 'ticket_statuses_updated' || action === 'ticket_categories_updated' || action === 'ticket_priorities_updated';
});

async function fetchLinkedTicket(): Promise<void> {
    const id = props.entry?.ticketId;
    if (!id || !props.modelValue) {
        return;
    }
    ticketLoading.value = true;
    ticketDetail.value = null;
    ticketPriorities.value = [];
    try {
        const res = await laravelFetch(route('tickets.detail-json', { ticket: id }));
        if (res.ok) {
            const data = (await res.json()) as { ticket: TicketDetail; priorities: typeof ticketPriorities.value };
            ticketDetail.value = data.ticket;
            ticketPriorities.value = data.priorities;
        }
    } finally {
        ticketLoading.value = false;
    }
}

watch(
    () => [props.modelValue, props.entry?.ticketId] as const,
    ([open, ticketId]) => {
        if (open && ticketId) {
            void fetchLinkedTicket();
        }
        if (!open) {
            ticketDetail.value = null;
            ticketPriorities.value = [];
        }
    },
);

function getRoleBadgeClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-rose-500/15 text-rose-500 border-rose-500/30';
        case 'supervisor':
            return 'bg-amber-500/15 text-amber-500 border-amber-500/30';
        case 'technical':
            return 'bg-blue-500/15 text-blue-500 border-blue-500/30';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function formatUserRoleLabel(role: string): string {
    if (role.length === 0) {
        return role;
    }
    return role.charAt(0).toUpperCase() + role.slice(1);
}
</script>

<template>
    <Dialog :open="modelValue" @update:open="(v: boolean) => emit('update:modelValue', v)">
        <DialogContent
            class="flex max-h-[min(90vh,920px)] w-[min(calc(100vw-1rem),56rem)] flex-col gap-0 overflow-hidden border-border/60 bg-card/95 p-0 shadow-2xl ring-1 ring-black/5 dark:bg-card dark:ring-white/10 sm:max-w-4xl sm:rounded-2xl"
        >
            <template v-if="entry">
                <DialogHeader
                    class="relative shrink-0 overflow-hidden border-b border-border/40 bg-gradient-to-br from-primary/[0.07] via-background to-background px-6 pb-6 pt-6 text-left"
                >
                    <div
                        class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/[0.12] blur-3xl dark:bg-primary/20"
                        aria-hidden="true"
                    />
                    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-start sm:gap-6">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-primary/25 bg-primary/10 text-primary shadow-sm"
                        >
                            <ScrollText class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <DialogTitle class="text-lg font-semibold tracking-tight text-foreground"> Audit log event </DialogTitle>
                            <p class="max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Who performed the action, when it happened, and how values changed.
                            </p>
                        </div>
                    </div>

                    <div class="relative mt-6 grid gap-3 sm:grid-cols-2">
                        <div
                            class="group flex gap-3 rounded-xl border border-border/50 bg-background/80 p-3.5 shadow-sm backdrop-blur-sm transition-colors hover:border-border hover:bg-background dark:bg-background/60"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/50 text-muted-foreground group-hover:text-foreground"
                            >
                                <Clock class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/90">When</p>
                                <p class="mt-1 text-sm font-semibold tabular-nums leading-snug text-foreground">
                                    {{ entry.createdAtFormatted }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ entry.createdAtRelative }}</p>
                            </div>
                        </div>

                        <div
                            class="group flex gap-3 rounded-xl border border-border/50 bg-background/80 p-3.5 shadow-sm backdrop-blur-sm transition-colors hover:border-border hover:bg-background dark:bg-background/60"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/50 text-muted-foreground group-hover:text-foreground"
                            >
                                <UserRound class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/90">Actor</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border/60 bg-gradient-to-br from-muted to-muted/50 text-[10px] font-bold uppercase text-foreground shadow-inner"
                                    >
                                        {{
                                            entry.userName
                                                .split(' ')
                                                .map((n) => n[0])
                                                .join('')
                                                .substring(0, 2)
                                        }}
                                    </div>
                                    <span class="text-sm font-semibold text-foreground">{{ entry.userName }}</span>
                                    <Badge
                                        v-if="entry.userRole"
                                        variant="outline"
                                        :class="['text-[9px] font-bold', getRoleBadgeClass(entry.userRole)]"
                                    >
                                        {{ formatUserRoleLabel(entry.userRole) }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div
                            class="group flex gap-3 rounded-xl border border-border/50 bg-background/80 p-3.5 shadow-sm backdrop-blur-sm transition-colors hover:border-border hover:bg-background dark:bg-background/60 sm:col-span-1"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/50 text-muted-foreground group-hover:text-foreground"
                            >
                                <Layers class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/90">Action</p>
                                <p class="mt-1 text-sm font-semibold leading-snug text-foreground">{{ entry.actionLabel }}</p>
                                <p class="mt-1 font-mono text-[10px] leading-relaxed text-muted-foreground/80">{{ entry.action }}</p>
                            </div>
                        </div>

                        <div
                            class="group flex gap-3 rounded-xl border border-border/50 bg-background/80 p-3.5 shadow-sm backdrop-blur-sm transition-colors hover:border-border hover:bg-background dark:bg-background/60 sm:col-span-1"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/50 text-muted-foreground group-hover:text-foreground"
                            >
                                <Ticket class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/90">Incident</p>
                                <template v-if="entry.ticketId">
                                    <p class="mt-1 font-mono text-xs font-bold text-primary">
                                        {{ entry.ticketTktId }}
                                    </p>
                                    <p class="mt-0.5 line-clamp-3 text-xs leading-snug text-muted-foreground">
                                        {{ entry.ticketTitle }}
                                    </p>
                                </template>
                                <p v-else class="mt-2 text-sm text-muted-foreground">No incident linked</p>
                            </div>
                        </div>
                    </div>
                </DialogHeader>

                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain bg-muted/20 px-6 py-5 dark:bg-muted/10">
                    <section v-if="entry.oldValue || entry.newValue" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground shadow-sm"
                            >
                                <ArrowLeftRight class="h-4 w-4" />
                            </span>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">What changed</h3>
                            <Separator class="flex-1 bg-border/60" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-stretch md:gap-5">
                            <div
                                v-if="entry.oldValue"
                                class="relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border border-destructive/20 bg-destructive/[0.04] shadow-sm dark:border-destructive/30 dark:bg-destructive/10 md:max-h-[min(52vh,480px)]"
                            >
                                <div
                                    class="h-1 shrink-0 bg-gradient-to-r from-destructive/0 via-destructive/50 to-destructive/0"
                                    aria-hidden="true"
                                />
                                <div class="flex min-h-0 flex-1 flex-col px-4 pb-4 pt-3 md:overflow-y-auto">
                                    <span
                                        class="mb-3 inline-flex w-fit items-center gap-1.5 rounded-full border border-destructive/25 bg-destructive/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-destructive"
                                    >
                                        Previous
                                    </span>
                                    <div class="min-h-0 min-w-0 flex-1">
                                        <AuditConfigChangeBlock v-if="usesTicketConfigChangeBlock" :text="entry.oldValue" tone="previous" />
                                        <div
                                            v-else
                                            class="whitespace-pre-wrap break-words text-[11px] font-medium leading-relaxed text-destructive/90 line-through"
                                        >
                                            {{ entry.oldValue }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="entry.newValue"
                                class="relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border border-emerald-500/25 bg-emerald-500/[0.06] shadow-sm dark:border-emerald-500/35 dark:bg-emerald-500/10 md:max-h-[min(52vh,480px)]"
                            >
                                <div
                                    class="h-1 shrink-0 bg-gradient-to-r from-emerald-500/0 via-emerald-500/50 to-emerald-500/0"
                                    aria-hidden="true"
                                />
                                <div class="flex min-h-0 flex-1 flex-col px-4 pb-4 pt-3 md:overflow-y-auto">
                                    <span
                                        class="mb-3 inline-flex w-fit items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400"
                                    >
                                        Updated
                                    </span>
                                    <div class="min-h-0 min-w-0 flex-1">
                                        <AuditConfigChangeBlock v-if="usesTicketConfigChangeBlock" :text="entry.newValue" tone="updated" />
                                        <div
                                            v-else
                                            class="whitespace-pre-wrap break-words text-[11px] font-medium leading-relaxed text-emerald-800 dark:text-emerald-200"
                                        >
                                            {{ entry.newValue }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="entry.ticketId" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground shadow-sm"
                            >
                                <LayoutList class="h-4 w-4" />
                            </span>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Incident snapshot</h3>
                            <Separator class="flex-1 bg-border/60" />
                        </div>
                        <p class="-mt-1 text-xs text-muted-foreground">Current incident state (overview, history, and comments).</p>
                        <div
                            class="overflow-hidden rounded-2xl border border-border/50 bg-card shadow-md ring-1 ring-black/[0.03] dark:ring-white/[0.06]"
                        >
                            <TicketDetailBody
                                :ticket="ticketDetail"
                                :priorities="ticketPriorities"
                                :statuses="statuses"
                                :loading="ticketLoading"
                                :visible="modelValue"
                                variant="embedded"
                                :show-edit-button="false"
                                :show-open-in-tickets-button="true"
                                @close="close"
                            />
                        </div>
                    </section>
                </div>

                <DialogFooter
                    class="shrink-0 flex-row justify-end gap-2 border-t border-border/50 bg-gradient-to-t from-muted/40 to-muted/10 px-6 py-4 dark:from-muted/25 dark:to-transparent"
                >
                    <Button type="button" variant="default" size="sm" class="h-9 min-w-[5.5rem] text-xs font-semibold shadow-sm" @click="close">
                        Done
                    </Button>
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
