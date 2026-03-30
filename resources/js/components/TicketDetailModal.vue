<script setup lang="ts">
import TicketComments from '@/components/TicketComments.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ensureLucideIconsLoaded, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import { router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronRight,
    Circle,
    Clock,
    ExternalLink as ExternalLinkIcon,
    FilePenLine,
    Flag,
    GitBranch,
    History,
    ImageIcon,
    Info,
    Loader,
    MessageSquare,
    Pencil,
    Pin,
    PinOff,
    Smile,
    Trash2,
    UserMinus,
    UserPlus,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { laravelFetch } from '@/lib/laravelFetch';

export interface TicketDetail {
    numericId: number;
    id: string;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    category: string;
    handlerIds: number[];
    handlers: { id: number; name: string }[];
    reporter: string;
    reporterId: number;
    attachmentUrl: string | null;
    createdAt: string;
    createdAtFormatted: string;
    createdAtRaw: string;
    solution: string | null;
    resolvedInDuration: string | null;
    resolvedAtFormatted: string | null;
    commentsCount: number;
    tags: string[];
}

interface ActivityEntry {
    id: number;
    action: string;
    oldValue: string | null;
    newValue: string | null;
    userName: string;
    createdAt: string;
    createdAtFormatted: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        ticket: TicketDetail | null;
        priorities: { id: number; name: string; icon: string; color: string }[];
        statuses: { id: number; name: string; icon: string; color: string; handler_requirement?: string }[];
        loading?: boolean;
        showEditButton?: boolean;
        showOpenInTicketsButton?: boolean;
    }>(),
    {
        statuses: () => [],
        loading: false,
        showEditButton: true,
        showOpenInTicketsButton: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    edit: [ticket: TicketDetail];
}>();

const detailTab = ref<'overview' | 'history'>('overview');
const activityLog = ref<ActivityEntry[]>([]);
const activityLoading = ref(false);
const historyFetchedFor = ref<number | null>(null);

async function fetchHistory(ticketId: number): Promise<void> {
    if (historyFetchedFor.value === ticketId) {
        return;
    }
    activityLoading.value = true;
    try {
        const res = await laravelFetch(route('tickets.history', { ticket: ticketId }));
        if (res.ok) {
            activityLog.value = await res.json();
            historyFetchedFor.value = ticketId;
        }
    } finally {
        activityLoading.value = false;
    }
}

watch(
    () => detailTab.value,
    (tab) => {
        if (tab === 'history' && props.ticket) {
            void fetchHistory(props.ticket.numericId);
        }
    },
);

watch(
    () => props.modelValue,
    (open) => {
        if (! open) {
            detailTab.value = 'overview';
            historyFetchedFor.value = null;
            activityLog.value = [];
        }
    },
);

watch(
    () => props.ticket?.numericId,
    (id, prev) => {
        if (id !== prev) {
            historyFetchedFor.value = null;
            activityLog.value = [];
        }
    },
);

onMounted(() => {
    void ensureLucideIconsLoaded();
});

const getInitials = (name: string): string => {
    if (name === 'Unassigned') {
        return 'UN';
    }
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

type IconComp = typeof History;

const ACTIVITY_CONFIG: Record<string, { icon: IconComp; classes: string; verb: string }> = {
    created: { icon: FilePenLine, classes: 'bg-primary/10 border-primary/20 text-primary', verb: 'created this ticket' },
    status_changed: { icon: GitBranch, classes: 'bg-blue-500/10 border-blue-500/20 text-blue-500', verb: 'changed status' },
    priority_changed: { icon: Flag, classes: 'bg-amber-500/10 border-amber-500/20 text-amber-500', verb: 'changed priority' },
    solution_updated: { icon: CheckCircle2, classes: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500', verb: 'updated the solution' },
    handler_assigned: { icon: UserPlus, classes: 'bg-violet-500/10 border-violet-500/20 text-violet-500', verb: 'assigned handler(s)' },
    handler_removed: { icon: UserMinus, classes: 'bg-rose-500/10 border-rose-500/20 text-rose-500', verb: 'removed handler(s)' },
    comment_posted: { icon: MessageSquare, classes: 'bg-sky-500/10 border-sky-500/20 text-sky-500', verb: 'posted a comment' },
    comment_deleted: { icon: Trash2, classes: 'bg-rose-500/10 border-rose-500/20 text-rose-500', verb: 'deleted a comment' },
    comment_pinned: { icon: Pin, classes: 'bg-amber-500/10 border-amber-500/20 text-amber-500', verb: 'pinned a comment' },
    comment_unpinned: { icon: PinOff, classes: 'bg-muted border-border/50 text-muted-foreground', verb: 'unpinned a comment' },
    reaction_added: { icon: Smile, classes: 'bg-pink-500/10 border-pink-500/20 text-pink-500', verb: 'reacted' },
    reaction_removed: { icon: Smile, classes: 'bg-muted border-border/50 text-muted-foreground', verb: 'removed a reaction' },
};

const getActivityIcon = (action: string): IconComp =>
    (ACTIVITY_CONFIG[action]?.icon ?? History) as IconComp;

const getActivityIconClass = (action: string): string =>
    ACTIVITY_CONFIG[action]?.classes ?? 'bg-muted border-border/50 text-muted-foreground';

const getActivityLabel = (entry: ActivityEntry): string => {
    const verb = ACTIVITY_CONFIG[entry.action]?.verb ?? entry.action.replace(/_/g, ' ');
    if (entry.action === 'handler_assigned' && entry.newValue) {
        return `assigned ${entry.newValue}`;
    }
    if (entry.action === 'handler_removed' && entry.oldValue) {
        return `removed ${entry.oldValue}`;
    }
    if (entry.action === 'reaction_added' && entry.newValue) {
        return `reacted with ${entry.newValue}`;
    }
    if (['comment_posted', 'comment_deleted'].includes(entry.action)) {
        const snippet = (entry.newValue ?? entry.oldValue)?.trim();
        return snippet ? `${verb}: "${snippet}"` : verb;
    }
    return verb;
};

const statusOptionsLocal = computed(() =>
    props.statuses.map((s) => ({
        ...s,
        iconComponent: resolveLucideIcon(s.icon, Circle),
    })),
);

function getStatusMeta(status: string) {
    return statusOptionsLocal.value.find((s) => s.name === status);
}

function getStatusStyle(status: string): Record<string, string> {
    const found = getStatusMeta(status);
    if (! found?.color) {
        return {};
    }
    return {
        backgroundColor: found.color + '26',
        color: found.color,
        borderColor: found.color + '40',
    };
}

function getStatusIcon(status: string) {
    return getStatusMeta(status)?.iconComponent ?? Circle;
}

const priorityOptionsLocal = computed(() =>
    props.priorities.map((p) => ({
        ...p,
        iconComponent: resolveLucideIcon(p.icon, Circle),
    })),
);

const getPriorityIcon = (priority: string) => {
    const found = priorityOptionsLocal.value.find((p) => p.name === priority);
    return found?.iconComponent ?? Circle;
};

const getPriorityStyle = (priority: string): Record<string, string> => {
    const found = props.priorities.find((p) => p.name === priority);
    if (! found) {
        return {};
    }
    return {
        backgroundColor: found.color + '26',
        color: found.color,
        borderColor: found.color + '40',
    };
};

function onEdit(): void {
    if (props.ticket) {
        emit('edit', props.ticket);
        emit('update:modelValue', false);
    }
}

function openInTickets(): void {
    if (! props.ticket) {
        return;
    }
    emit('update:modelValue', false);
    router.get(route('tickets'), { ticket_id: props.ticket.numericId });
}
</script>

<template>
    <Dialog :open="modelValue" @update:open="emit('update:modelValue', $event)">
        <DialogContent
            v-if="loading"
            class="sm:max-w-[580px] border-none p-0 shadow-2xl"
        >
            <div class="flex flex-col items-center justify-center gap-3 py-20">
                <Loader class="h-8 w-8 animate-spin text-muted-foreground" />
                <p class="text-sm text-muted-foreground">Loading ticket…</p>
            </div>
        </DialogContent>

        <DialogContent
            v-else-if="ticket"
            class="flex max-h-[92dvh] flex-col overflow-hidden border-none p-0 shadow-2xl sm:max-w-[580px]"
        >
            <!-- Header -->
            <div class="border-b border-primary/10 bg-primary/5 px-5 pb-4 pt-5">
                <DialogHeader>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <Badge variant="outline" class="bg-primary/10 px-2 py-0 text-[10px] font-bold uppercase tracking-wider text-primary border-primary/20">
                            {{ ticket.id }}
                        </Badge>
                        <Badge variant="outline" :class="['inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold border']" :style="getStatusStyle(ticket.status)">
                            <component :is="getStatusIcon(ticket.status)" class="h-3 w-3" />
                            {{ ticket.status }}
                        </Badge>
                        <span class="inline-flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[10px] font-bold uppercase" :style="getPriorityStyle(ticket.priority)">
                            <component :is="getPriorityIcon(ticket.priority)" class="h-3 w-3" />
                            {{ ticket.priority }}
                        </span>
                        <Badge v-for="tag in ticket.tags" :key="tag" variant="secondary" class="px-2 py-0.5 text-[10px] font-medium border-border/50">
                            {{ tag }}
                        </Badge>
                    </div>
                    <DialogTitle class="text-base font-bold leading-snug tracking-tight sm:text-lg">
                        {{ ticket.title }}
                    </DialogTitle>
                    <DialogDescription class="mt-0.5 text-xs text-muted-foreground/70">
                        Submitted {{ ticket.createdAtFormatted }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Body -->
            <div class="modal-body flex flex-1 flex-col overflow-y-auto">
                <div class="flex items-center gap-1 border-b border-border/40 px-5 pb-0 pt-4">
                    <button
                        type="button"
                        @click="detailTab = 'overview'"
                        :class="[
                            '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-semibold transition-colors',
                            detailTab === 'overview'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        <Info class="h-3.5 w-3.5" /> Overview
                    </button>
                    <button
                        type="button"
                        @click="detailTab = 'history'"
                        :class="[
                            '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-semibold transition-colors',
                            detailTab === 'history'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        <History class="h-3.5 w-3.5" /> History
                    </button>
                </div>

                <!-- Overview -->
                <div v-if="detailTab === 'overview'" class="grid gap-4 px-5 py-5">
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <div class="flex flex-col gap-1 rounded-xl border border-border/40 bg-muted/40 px-3 py-2.5">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Category</span>
                            <span class="text-sm font-semibold text-foreground">{{ ticket.category }}</span>
                        </div>
                        <div class="flex flex-col gap-1 rounded-xl border border-border/40 bg-muted/40 px-3 py-2.5">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Reporter</span>
                            <div class="flex items-center gap-1.5">
                                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-border/50 bg-muted text-[9px] font-bold">
                                    {{ getInitials(ticket.reporter) }}
                                </div>
                                <span class="truncate text-sm font-semibold text-foreground">{{ ticket.reporter }}</span>
                            </div>
                        </div>
                        <div class="col-span-2 flex flex-col gap-1 rounded-xl border border-border/40 bg-muted/40 px-3 py-2.5 sm:col-span-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Handlers</span>
                            <div v-if="ticket.handlers.length > 0" class="mt-0.5 flex flex-wrap gap-1">
                                <span
                                    v-for="h in ticket.handlers"
                                    :key="h.id"
                                    class="inline-flex items-center gap-1 rounded-full border border-border/50 bg-muted px-1.5 py-0.5 text-[11px] font-semibold"
                                >
                                    <span class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full bg-muted-foreground/20 text-[8px] font-bold">{{ getInitials(h.name) }}</span>
                                    {{ h.name }}
                                </span>
                            </div>
                            <span v-else class="text-sm italic text-muted-foreground/50">Unassigned</span>
                        </div>
                    </div>

                    <div v-if="ticket.tags && ticket.tags.length > 0" class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Tags</span>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="tag in ticket.tags" :key="tag" variant="secondary" class="bg-primary/10 text-primary hover:bg-primary/20 border-primary/20 text-[10px] font-semibold px-2 py-0.5">
                                {{ tag }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Description</span>
                        <div
                            v-if="ticket.description"
                            class="prose prose-sm max-w-none rounded-xl border border-border/40 bg-muted/20 px-4 py-3 text-sm leading-relaxed text-foreground dark:prose-invert"
                            v-html="ticket.description"
                        />
                        <div v-else class="rounded-xl border border-dashed border-border/40 bg-muted/10 px-4 py-5 text-center text-sm italic text-muted-foreground/60">
                            No description provided.
                        </div>
                    </div>

                    <div v-if="ticket.solution" class="flex flex-col gap-2">
                        <div class="flex items-center gap-1.5">
                            <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500 dark:text-emerald-400" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Solution</span>
                        </div>
                        <div
                            class="prose prose-sm max-w-none rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-relaxed text-foreground dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:prose-invert"
                            v-html="ticket.solution"
                        />
                    </div>

                    <div v-if="ticket.attachmentUrl" class="flex flex-col gap-2">
                        <div class="flex items-center gap-1.5">
                            <ImageIcon class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Attachment</span>
                        </div>
                        <a :href="ticket.attachmentUrl" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl border border-border/50 bg-muted/20 transition-opacity hover:opacity-90">
                            <img :src="ticket.attachmentUrl" alt="Ticket attachment" class="max-h-52 w-full object-contain" />
                        </a>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground/70">
                            <Clock class="h-3.5 w-3.5 shrink-0" />
                            <span>Opened {{ ticket.createdAt }}</span>
                            <span class="text-muted-foreground/40">·</span>
                            <span>{{ ticket.createdAtFormatted }}</span>
                        </div>
                        <div v-if="ticket.resolvedAtFormatted" class="flex items-center gap-2 text-xs">
                            <CheckCircle2 class="h-3.5 w-3.5 shrink-0 text-emerald-500 dark:text-emerald-400" />
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">Resolved in {{ ticket.resolvedInDuration }}</span>
                            <span class="text-muted-foreground/40">·</span>
                            <span class="text-muted-foreground/70">{{ ticket.resolvedAtFormatted }}</span>
                        </div>
                    </div>

                    <TicketComments :ticket-id="ticket.numericId" :reporter-id="ticket.reporterId" />
                </div>

                <!-- History -->
                <div v-if="detailTab === 'history'" class="px-5 py-5">
                    <div v-if="activityLoading" class="flex flex-col gap-3">
                        <div v-for="i in 5" :key="i" class="flex animate-pulse items-start gap-3">
                            <div class="mt-0.5 h-7 w-7 shrink-0 rounded-full bg-muted" />
                            <div class="flex flex-1 flex-col gap-1.5 pt-1">
                                <div class="h-3 w-3/4 rounded bg-muted" />
                                <div class="h-2.5 w-1/2 rounded bg-muted/60" />
                            </div>
                        </div>
                    </div>
                    <div v-else-if="activityLog.length === 0" class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <History class="h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground/60">No activity recorded yet.</p>
                    </div>
                    <div v-else class="relative">
                        <div class="absolute bottom-4 left-3.5 top-4 w-px bg-border/50" />
                        <div class="flex flex-col gap-0">
                            <div
                                v-for="entry in activityLog"
                                :key="entry.id"
                                class="group relative flex items-start gap-3 py-2.5"
                            >
                                <div :class="['z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border', getActivityIconClass(entry.action)]">
                                    <component :is="getActivityIcon(entry.action)" class="h-3.5 w-3.5" />
                                </div>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <p class="text-xs font-medium leading-snug text-foreground">
                                        <span class="font-semibold">{{ entry.userName }}</span>
                                        {{ getActivityLabel(entry) }}
                                    </p>
                                    <div
                                        v-if="(entry.oldValue || entry.newValue) && !['comment_posted','comment_deleted','comment_pinned','comment_unpinned','reaction_added','reaction_removed'].includes(entry.action)"
                                        class="mt-1 flex flex-wrap items-center gap-1.5"
                                    >
                                        <span v-if="entry.oldValue" class="inline-flex items-center rounded bg-destructive/10 px-1.5 py-0.5 text-[10px] font-semibold text-destructive/80 line-through">{{ entry.oldValue }}</span>
                                        <ChevronRight v-if="entry.oldValue && entry.newValue" class="h-3 w-3 shrink-0 text-muted-foreground/50" />
                                        <span v-if="entry.newValue" class="inline-flex items-center rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">{{ entry.newValue }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[10px] text-muted-foreground/50" :title="entry.createdAtFormatted">{{ entry.createdAt }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex flex-wrap items-center gap-2 border-t border-border/50 bg-muted/20 px-5 py-4">
                <Button
                    v-if="showOpenInTicketsButton"
                    variant="outline"
                    class="gap-1.5 text-xs font-bold"
                    @click="openInTickets"
                >
                    <ExternalLinkIcon class="h-3.5 w-3.5" />
                    Open in Tickets
                </Button>
                <Button
                    v-if="showEditButton"
                    variant="outline"
                    class="gap-1.5 text-xs font-bold"
                    @click="onEdit"
                >
                    <Pencil class="h-3.5 w-3.5" /> Edit
                </Button>
                <Button variant="outline" class="ml-auto text-xs font-bold" @click="emit('update:modelValue', false)">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
