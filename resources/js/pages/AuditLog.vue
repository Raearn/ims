<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    FilePenLine,
    Flag,
    GitBranch,
    History,
    LogIn,
    LogOut,
    MessageSquare,
    Pin,
    PinOff,
    ScrollText,
    Smile,
    Trash2,
    UserCog,
    UserMinus,
    UserPlus,
    UserX,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ActivityRow {
    id: number;
    action: string;
    oldValue: string | null;
    newValue: string | null;
    userName: string;
    userId: number | null;
    ticketId: number | null;
    ticketTitle: string;
    ticketTktId: string;
    createdAt: string;
    createdAtFormatted: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    activities: {
        data: ActivityRow[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        action?: string;
        user_id?: string;
        ticket_id?: string;
        from?: string;
        to?: string;
    };
    users: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Audit Log', href: '/audit-log' },
];

// ── Filter state ───────────────────────────────────────────────────────────
const filterAction   = ref(props.filters.action ?? '');
const filterUserId   = ref(props.filters.user_id ?? '');
const filterTicketId = ref(props.filters.ticket_id ?? '');
const filterFrom     = ref(props.filters.from ?? '');
const filterTo       = ref(props.filters.to ?? '');

const ALL_ACTIONS = [
    'created',
    'status_changed',
    'priority_changed',
    'solution_updated',
    'handler_assigned',
    'handler_removed',
    'comment_posted',
    'comment_deleted',
    'comment_pinned',
    'comment_unpinned',
    'reaction_added',
    'reaction_removed',
    'ticket_deleted',
    'user_login',
    'user_logout',
    'user_created',
    'user_updated',
    'user_deleted',
] as const;

const ACTION_LABELS: Record<string, string> = {
    created:           'Ticket Created',
    status_changed:    'Status Changed',
    priority_changed:  'Priority Changed',
    solution_updated:  'Solution Updated',
    handler_assigned:  'Handler Assigned',
    handler_removed:   'Handler Removed',
    comment_posted:    'Comment Posted',
    comment_deleted:   'Comment Deleted',
    comment_pinned:    'Comment Pinned',
    comment_unpinned:  'Comment Unpinned',
    reaction_added:    'Reaction Added',
    reaction_removed:  'Reaction Removed',
    ticket_deleted:    'Ticket Deleted',
    user_login:        'User Login',
    user_logout:       'User Logout',
    user_created:      'User Created',
    user_updated:      'User Updated',
    user_deleted:      'User Deleted',
};

type IconComponent = typeof History;

const ACTIVITY_CONFIG: Record<string, { icon: IconComponent; classes: string }> = {
    created:           { icon: FilePenLine,   classes: 'bg-primary/10 text-primary border-primary/20' },
    status_changed:    { icon: GitBranch,     classes: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' },
    priority_changed:  { icon: Flag,          classes: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' },
    solution_updated:  { icon: CheckCircle2,  classes: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' },
    handler_assigned:  { icon: UserPlus,      classes: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20' },
    handler_removed:   { icon: UserMinus,     classes: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' },
    comment_posted:    { icon: MessageSquare, classes: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20' },
    comment_deleted:   { icon: Trash2,        classes: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' },
    comment_pinned:    { icon: Pin,           classes: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' },
    comment_unpinned:  { icon: PinOff,        classes: 'bg-muted text-muted-foreground border-border/50' },
    reaction_added:    { icon: Smile,         classes: 'bg-pink-500/10 text-pink-600 dark:text-pink-400 border-pink-500/20' },
    reaction_removed:  { icon: Smile,         classes: 'bg-muted text-muted-foreground border-border/50' },
    ticket_deleted:    { icon: Trash2,        classes: 'bg-rose-600/10 text-rose-700 dark:text-rose-400 border-rose-600/20' },
    user_login:        { icon: LogIn,         classes: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' },
    user_logout:       { icon: LogOut,        classes: 'bg-muted text-muted-foreground border-border/50' },
    user_created:      { icon: UserPlus,      classes: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20' },
    user_updated:      { icon: UserCog,       classes: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' },
    user_deleted:      { icon: UserX,         classes: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' },
};

const getActivityIcon = (action: string): IconComponent =>
    (ACTIVITY_CONFIG[action]?.icon ?? History) as IconComponent;

const getActivityIconClass = (action: string): string =>
    ACTIVITY_CONFIG[action]?.classes ?? 'bg-muted text-muted-foreground border-border/50';

const getActionLabel = (action: string): string =>
    ACTION_LABELS[action] ?? action.replace(/_/g, ' ');

// ── Filter apply / clear ───────────────────────────────────────────────────
const hasActiveFilters = computed(() =>
    !!(filterAction.value || filterUserId.value || filterTicketId.value || filterFrom.value || filterTo.value),
);

function applyFilters(): void {
    router.get(
        route('audit-log'),
        {
            action:    filterAction.value || undefined,
            user_id:   filterUserId.value || undefined,
            ticket_id: filterTicketId.value || undefined,
            from:      filterFrom.value || undefined,
            to:        filterTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    filterAction.value   = '';
    filterUserId.value   = '';
    filterTicketId.value = '';
    filterFrom.value     = '';
    filterTo.value       = '';
    router.get(route('audit-log'), {}, { preserveState: false });
}

// Debounce ticket_id text input
let ticketDebounce: ReturnType<typeof setTimeout> | null = null;
watch(filterTicketId, () => {
    if (ticketDebounce) clearTimeout(ticketDebounce);
    ticketDebounce = setTimeout(applyFilters, 400);
});

// Immediately apply when select/date inputs change
watch([filterAction, filterUserId, filterFrom, filterTo], applyFilters);

// ── Pagination helper ──────────────────────────────────────────────────────
const pageLinks = computed(() =>
    props.activities.links.filter(l => l.label !== '« Previous' && l.label !== 'Next »'),
);

const prevLink = computed(() => props.activities.links.find(l => l.label === '« Previous') ?? null);
const nextLink = computed(() => props.activities.links.find(l => l.label === 'Next »') ?? null);
</script>

<template>
    <Head title="Audit Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">

            <!-- Page header -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ScrollText class="h-6 w-6 text-primary" />
                        Audit Log
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Complete history of all ticket and comment actions
                    </p>
                </div>

                <!-- Total badge -->
                <div class="flex items-center gap-2 rounded-xl border border-border/50 bg-muted/40 px-4 py-2.5">
                    <History class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm font-semibold tabular-nums">{{ activities.total.toLocaleString() }}</span>
                    <span class="text-xs text-muted-foreground">total events</span>
                </div>
            </div>

            <!-- Filter bar -->
            <Card class="shadow-none border border-border/50">
                <CardContent class="px-4 py-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <!-- Action type -->
                        <div class="flex flex-col gap-1 min-w-[180px]">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Action</span>
                            <select
                                v-model="filterAction"
                                class="h-8 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-0"
                            >
                                <option value="">All actions</option>
                                <option v-for="a in ALL_ACTIONS" :key="a" :value="a">
                                    {{ getActionLabel(a) }}
                                </option>
                            </select>
                        </div>

                        <!-- Actor -->
                        <div class="flex flex-col gap-1 min-w-[160px]">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Actor</span>
                            <select
                                v-model="filterUserId"
                                class="h-8 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-0"
                            >
                                <option value="">All users</option>
                                <option v-for="u in users" :key="u.id" :value="String(u.id)">
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Ticket ID -->
                        <div class="flex flex-col gap-1 min-w-[120px]">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Ticket #</span>
                            <Input
                                v-model="filterTicketId"
                                type="number"
                                min="1"
                                placeholder="e.g. 1001"
                                class="h-8 text-xs"
                            />
                        </div>

                        <!-- Date from -->
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">From</span>
                            <Input v-model="filterFrom" type="date" class="h-8 text-xs" />
                        </div>

                        <!-- Date to -->
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">To</span>
                            <Input v-model="filterTo" type="date" class="h-8 text-xs" />
                        </div>

                        <!-- Clear -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground self-end"
                            @click="clearFilters"
                        >
                            <X class="h-3.5 w-3.5" />
                            Clear
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="shadow-none border border-border/50 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/30">
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground whitespace-nowrap">Date / Time</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground whitespace-nowrap">Actor</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground whitespace-nowrap">Action</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground whitespace-nowrap">Ticket</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground whitespace-nowrap">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">

                            <!-- Empty state -->
                            <tr v-if="activities.data.length === 0">
                                <td colspan="5" class="px-4 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3 text-muted-foreground/60">
                                        <History class="h-10 w-10" />
                                        <p class="text-sm font-medium">No activity found</p>
                                        <p v-if="hasActiveFilters" class="text-xs">
                                            Try clearing the filters to see all events.
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Rows -->
                            <tr
                                v-for="entry in activities.data"
                                :key="entry.id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <!-- Date -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-xs font-medium text-foreground">{{ entry.createdAtFormatted }}</p>
                                    <p class="text-[10px] text-muted-foreground/60 mt-0.5">{{ entry.createdAt }}</p>
                                </td>

                                <!-- Actor -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full bg-muted border border-border/50 flex items-center justify-center text-[9px] font-bold shrink-0 uppercase">
                                            {{ entry.userName.split(' ').map(n => n[0]).join('').substring(0, 2) }}
                                        </div>
                                        <span class="text-xs font-medium text-foreground">{{ entry.userName }}</span>
                                    </div>
                                </td>

                                <!-- Action badge -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span :class="['inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold', getActivityIconClass(entry.action)]">
                                        <component :is="getActivityIcon(entry.action)" class="h-3 w-3 shrink-0" />
                                        {{ getActionLabel(entry.action) }}
                                    </span>
                                </td>

                                <!-- Ticket -->
                                <td class="px-4 py-3 max-w-[200px]">
                                    <template v-if="entry.ticketId">
                                        <p class="text-[10px] font-bold text-primary/70">{{ entry.ticketTktId }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ entry.ticketTitle }}</p>
                                    </template>
                                    <span v-else class="text-xs text-muted-foreground/40">—</span>
                                </td>

                                <!-- Change old → new -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            v-if="entry.oldValue"
                                            class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-destructive/10 text-destructive/80 line-through"
                                        >{{ entry.oldValue }}</span>
                                        <ChevronRight v-if="entry.oldValue && entry.newValue" class="h-3 w-3 text-muted-foreground/40 shrink-0" />
                                        <span
                                            v-if="entry.newValue"
                                            class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                        >{{ entry.newValue }}</span>
                                        <span v-if="!entry.oldValue && !entry.newValue" class="text-[10px] text-muted-foreground/40">—</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="activities.last_page > 1" class="flex items-center justify-between border-t border-border/40 px-4 py-3 bg-muted/10">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-semibold">{{ activities.from ?? 0 }}</span>–<span class="font-semibold">{{ activities.to ?? 0 }}</span>
                        of <span class="font-semibold">{{ activities.total.toLocaleString() }}</span> events
                    </p>

                    <div class="flex items-center gap-1">
                        <!-- Prev -->
                        <Link
                            v-if="prevLink?.url"
                            :href="prevLink.url"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border/50 bg-background text-xs hover:bg-muted transition-colors"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                        </Link>
                        <span v-else class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border/30 bg-muted/30 text-xs text-muted-foreground/40">
                            <ChevronLeft class="h-3.5 w-3.5" />
                        </span>

                        <!-- Page numbers -->
                        <template v-for="link in pageLinks" :key="link.label">
                            <Link
                                v-if="link.url && !link.active"
                                :href="link.url"
                                class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md border border-border/50 bg-background px-2 text-xs hover:bg-muted transition-colors"
                                v-html="link.label"
                            />
                            <span
                                v-else-if="link.active"
                                class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md border border-primary/40 bg-primary/10 px-2 text-xs font-bold text-primary"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="inline-flex h-7 min-w-[28px] items-center justify-center px-1 text-xs text-muted-foreground/50"
                                v-html="link.label"
                            />
                        </template>

                        <!-- Next -->
                        <Link
                            v-if="nextLink?.url"
                            :href="nextLink.url"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border/50 bg-background text-xs hover:bg-muted transition-colors"
                        >
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                        <span v-else class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border/30 bg-muted/30 text-xs text-muted-foreground/40">
                            <ChevronRight class="h-3.5 w-3.5" />
                        </span>
                    </div>
                </div>
            </Card>

        </div>
    </AppLayout>
</template>
