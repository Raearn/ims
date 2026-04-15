<script setup lang="ts">
import type { IncidentPriorityOption, IncidentStatusOption, IncidentTicketRow } from '@/components/incidents/incidentFormTypes';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SortKey } from '@/composables/useIncidentsList';
import { lucideAllIconMap, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import {
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
    Circle,
    Clock,
    ExternalLink,
    Lock,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    RefreshCcw,
    Search,
    TicketCheck,
    Trash2,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    sortedTickets: IncidentTicketRow[];
    paginatedTickets: IncidentTicketRow[];
    search: string;
    currentStatus: string;
    currentPriority: string;
    currentCategory: string;
    dateFrom: string;
    dateTo: string;
    selectedIds: number[];
    isAllSelected: boolean;
    sortKey: SortKey | null;
    sortDir: 'asc' | 'desc';
    currentPage: number;
    totalPages: number;
    pageRange: (number | '...')[];
    pageSize: number;
    pageSizeOptions: readonly number[];
    priorities: IncidentPriorityOption[];
    statuses: IncidentStatusOption[];
    statusProcessing: number | null;
    /** Hide selection, edits, and destructive actions (technical Incidents page). */
    readonly?: boolean;
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:pageSize': [value: number];
    'update:currentPage': [value: number];
    toggleSelectAll: [];
    toggleTicket: [numericId: number, checked: boolean];
    toggleSort: [key: SortKey];
    clearFilters: [];
    openDetail: [ticket: IncidentTicketRow];
    openEdit: [ticket: IncidentTicketRow];
    openAssign: [ticket: IncidentTicketRow, defaultStatus?: string];
    openChangeStatus: [ticket: IncidentTicketRow];
    openDelete: [ticket: IncidentTicketRow];
    updateStatus: [ticket: IncidentTicketRow, status: string];
}>();

const searchInputRef = ref<HTMLInputElement | null>(null);
defineExpose({ searchInputRef });

const statusOptionsLocal = computed(() => {
    void lucideAllIconMap.value;
    return props.statuses.map((s) => ({
        ...s,
        iconComponent: resolveLucideIcon(s.icon, Circle),
    }));
});

function getStatusMeta(status: string) {
    return statusOptionsLocal.value.find((s) => s.name === status);
}

function getStatusStyle(status: string): Record<string, string> {
    const found = getStatusMeta(status);
    if (!found?.color) {
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

function statusStripeGradientStyle(status: string): Record<string, string> {
    const c = getStatusMeta(status)?.color ?? '#94a3b8';
    return {
        background: `linear-gradient(to bottom, ${c}cc, ${c}66, transparent)`,
    };
}

const priorityOptions = computed(() =>
    props.priorities.map((p) => ({
        ...p,
        iconComponent: resolveLucideIcon(p.icon, Circle),
    })),
);

function getPriorityIcon(priority: string) {
    return priorityOptions.value.find((p) => p.name === priority)?.iconComponent ?? Circle;
}

function getPriorityStyle(priority: string): Record<string, string> {
    const found = props.priorities.find((p) => p.name === priority);
    if (!found) {
        return {};
    }
    return {
        backgroundColor: found.color + '26',
        color: found.color,
        borderColor: found.color + '40',
    };
}

function isStatusNoHandlers(statusName: string): boolean {
    const row = props.statuses.find((s) => s.name === statusName);
    return row?.handler_requirement === 'none';
}

function getInitials(name: string) {
    if (name === 'Unassigned') {
        return 'UN';
    }
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

const handlerTooltip = ref<{ name: string; x: number; y: number } | null>(null);
const reporterTooltip = ref<{ name: string; x: number; y: number } | null>(null);

function showHandlerTooltip(e: MouseEvent, name: string) {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    handlerTooltip.value = { name, x: rect.left + rect.width / 2, y: rect.top };
}
function hideHandlerTooltip() {
    handlerTooltip.value = null;
}
function showReporterTooltip(e: MouseEvent, name: string) {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    reporterTooltip.value = { name, x: rect.left + rect.width / 2, y: rect.top };
}
function hideReporterTooltip() {
    reporterTooltip.value = null;
}
</script>

<template>
    <div class="contents">
        <Card class="overflow-hidden rounded-xl border border-border/60 shadow-sm ring-1 ring-border/30">
            <CardContent class="p-0">
                <!-- List toolbar: search + count -->
                <div
                    v-if="sortedTickets.length > 0"
                    class="flex flex-col gap-2 border-b border-border/50 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-4 md:px-5"
                >
                    <div class="relative w-full sm:max-w-xs md:max-w-sm">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            ref="searchInputRef"
                            :value="search"
                            type="search"
                            placeholder="Search incidents, tags, reporters…"
                            class="flex h-9 w-full rounded-lg border border-border/60 bg-background/60 py-1 pl-9 pr-8 text-sm text-foreground shadow-sm backdrop-blur-sm transition-colors placeholder:text-muted-foreground/50 hover:border-primary/25 hover:bg-background/80 focus-visible:border-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
                            @input="emit('update:search', ($event.target as HTMLInputElement).value)"
                        />
                        <kbd
                            v-if="!search"
                            class="pointer-events-none absolute right-2.5 top-1/2 hidden -translate-y-1/2 select-none items-center rounded border border-border/60 bg-muted/80 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground/60 sm:inline-flex"
                        >
                            /
                        </kbd>
                        <button
                            v-else
                            type="button"
                            aria-label="Clear search"
                            class="absolute right-2.5 top-1/2 flex h-4 w-4 -translate-y-1/2 items-center justify-center rounded text-muted-foreground/40 transition-colors hover:text-foreground"
                            @click="emit('update:search', '')"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground sm:text-right">
                        <span class="font-semibold text-foreground">{{ sortedTickets.length }}</span>
                        incident{{ sortedTickets.length !== 1 ? 's' : '' }} in view
                    </p>
                </div>

                <div v-if="sortedTickets.length === 0" class="flex flex-col items-center justify-center gap-5 px-6 py-20 text-center sm:py-24">
                    <div class="relative">
                        <div
                            class="flex h-20 w-20 items-center justify-center rounded-2xl border border-border/50 bg-gradient-to-br from-muted/80 to-muted/40 shadow-inner"
                        >
                            <TicketCheck class="h-9 w-9 text-muted-foreground/40" aria-hidden="true" />
                        </div>
                        <div
                            class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full border border-border/60 bg-background shadow-sm"
                        >
                            <Search class="h-3 w-3 text-muted-foreground/60" aria-hidden="true" />
                        </div>
                    </div>
                    <div class="max-w-sm space-y-1">
                        <p class="text-base font-semibold text-foreground">No incidents found</p>
                        <p class="text-sm leading-relaxed text-muted-foreground">
                            {{
                                search
                                    ? `No results for "${search}". Try a different search or reset filters.`
                                    : readonly
                                      ? 'No incidents match the current filters.'
                                      : 'No incidents match the current filters. Adjust filters or create a new incident.'
                            }}
                        </p>
                    </div>
                    <button
                        v-if="search || currentStatus !== 'All' || currentPriority !== 'All' || currentCategory !== 'All' || dateFrom || dateTo"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-background/80 px-4 py-2 text-xs font-semibold text-foreground shadow-sm backdrop-blur-sm transition-colors hover:border-primary/30 hover:bg-background"
                        @click="emit('clearFilters')"
                    >
                        <X class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                        Clear all filters
                    </button>
                </div>

                <div v-else class="divide-y divide-border/50 md:hidden">
                    <div
                        v-for="ticket in paginatedTickets"
                        :key="ticket.id"
                        :class="[
                            'group flex cursor-pointer items-stretch gap-0 transition-colors hover:bg-muted/35 active:bg-muted/50',
                            !readonly && selectedIds.includes(ticket.numericId) ? 'bg-primary/5 hover:bg-primary/10' : '',
                        ]"
                        @click="emit('openDetail', ticket)"
                    >
                        <div class="w-1 shrink-0 rounded-none" :style="statusStripeGradientStyle(ticket.status)" />
                        <div class="flex min-w-0 flex-1 items-start gap-3 px-4 py-3.5">
                            <div v-if="!readonly" @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(ticket.numericId)"
                                    class="mt-0.5 shrink-0"
                                    @update:checked="(val) => emit('toggleTicket', ticket.numericId, !!val)"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono text-[10px] font-bold text-muted-foreground/60">{{ ticket.id }}</span>
                                        <Badge
                                            variant="outline"
                                            :class="['inline-flex items-center gap-1 border px-1.5 py-0.5 text-[10px] font-bold']"
                                            :style="getStatusStyle(ticket.status)"
                                        >
                                            <component :is="getStatusIcon(ticket.status)" class="h-2.5 w-2.5 shrink-0" />
                                            {{ ticket.status }}
                                        </Badge>
                                    </div>
                                    <div v-if="!readonly" @click.stop>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button
                                                    type="button"
                                                    aria-label="Open incident actions"
                                                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-muted-foreground outline-none transition-colors hover:bg-muted"
                                                >
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuLabel>Incident Actions</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem @click="emit('openDetail', ticket)">
                                                    <ExternalLink class="mr-2 h-4 w-4" />View Details
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="emit('openEdit', ticket)">
                                                    <Pencil class="mr-2 h-4 w-4" />Edit Incident
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="emit('openAssign', ticket)">
                                                    <UserPlus class="mr-2 h-4 w-4" />
                                                    Assign Handler
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="emit('openChangeStatus', ticket)">
                                                    <RefreshCcw class="mr-2 h-4 w-4" />
                                                    Change Status
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    v-if="ticket.status !== 'Resolved' && ticket.status !== 'Cancelled'"
                                                    :disabled="statusProcessing === ticket.numericId"
                                                    class="text-emerald-600 focus:bg-emerald-50 focus:text-emerald-700 dark:text-emerald-400 dark:focus:bg-emerald-950/40 dark:focus:text-emerald-300"
                                                    @click="
                                                        isStatusNoHandlers(ticket.status)
                                                            ? emit('openAssign', ticket, 'Resolved')
                                                            : emit('updateStatus', ticket, 'Resolved')
                                                    "
                                                >
                                                    <span
                                                        v-if="statusProcessing === ticket.numericId"
                                                        class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                                                    />
                                                    <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                                                    Mark as Resolved
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="!['Resolved', 'Cancelled'].includes(ticket.status)"
                                                    :disabled="statusProcessing === ticket.numericId"
                                                    class="text-slate-500 focus:bg-slate-100 focus:text-slate-700 dark:text-slate-400 dark:focus:bg-slate-800/50 dark:focus:text-slate-300"
                                                    @click="emit('updateStatus', ticket, 'Cancelled')"
                                                >
                                                    <span
                                                        v-if="statusProcessing === ticket.numericId"
                                                        class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                                                    />
                                                    <Lock v-else class="mr-2 h-4 w-4" />
                                                    Mark as Cancelled
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                                    @click="emit('openDelete', ticket)"
                                                    ><Trash2 class="mr-2 h-4 w-4" />Delete Incident</DropdownMenuItem
                                                >
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                                <p class="line-clamp-2 text-sm font-semibold leading-snug text-foreground transition-colors group-hover:text-primary">
                                    {{ ticket.title }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                        :style="getPriorityStyle(ticket.priority)"
                                    >
                                        <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                        {{ ticket.priority }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-md border border-border/40 bg-muted/60 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-tight text-muted-foreground"
                                    >
                                        {{ ticket.category }}
                                    </span>
                                    <Badge
                                        v-for="tag in ticket.tags"
                                        :key="tag"
                                        variant="secondary"
                                        class="border-border/50 px-1.5 py-0.5 text-[9px] font-medium"
                                    >
                                        {{ tag }}
                                    </Badge>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <div
                                            class="flex h-5 w-5 shrink-0 cursor-default items-center justify-center rounded-full border border-border/50 bg-muted text-[9px] font-bold"
                                            @mouseenter="showReporterTooltip($event, ticket.reporter)"
                                            @mouseleave="hideReporterTooltip"
                                        >
                                            {{ getInitials(ticket.reporter) }}
                                        </div>
                                        <span class="truncate text-[11px] text-muted-foreground">{{ ticket.reporter }}</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1 text-[10px] text-muted-foreground/60">
                                        <template v-if="ticket.commentsCount > 0">
                                            <MessageSquare class="h-3 w-3" />
                                            <span class="mr-1">{{ ticket.commentsCount }}</span>
                                        </template>
                                        <Clock class="h-3 w-3" />
                                        {{ ticket.createdAt }}
                                    </div>
                                </div>
                                <div v-if="ticket.handlers.length > 0" class="mt-2 flex items-center gap-1.5">
                                    <div class="flex -space-x-1.5">
                                        <div
                                            v-for="(h, i) in ticket.handlers.slice(0, 3)"
                                            :key="h.id"
                                            :style="{ zIndex: 3 - i }"
                                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-background bg-muted text-[9px] font-bold"
                                        >
                                            {{ getInitials(h.name) }}
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground">
                                        {{ ticket.handlers.length === 1 ? ticket.handlers[0].name : `${ticket.handlers.length} handlers` }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative hidden w-full overflow-x-auto p-2 md:block">
                    <div class="overflow-hidden rounded-xl border border-border/50 bg-card/40 shadow-sm ring-1 ring-border/20 dark:bg-card/30">
                        <table class="w-full min-w-[760px] border-collapse text-left">
                            <thead class="sticky top-0 z-10 border-b border-border/60 bg-muted/55 shadow-sm backdrop-blur-md dark:bg-muted/40">
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <th v-if="!readonly" class="w-10 py-3.5 pl-5 pr-3">
                                        <Checkbox
                                            :checked="isAllSelected"
                                            aria-label="Select all incidents on this page"
                                            @update:checked="emit('toggleSelectAll')"
                                        />
                                    </th>
                                    <th class="w-28 px-3 py-3.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md transition-colors hover:text-foreground"
                                            aria-label="Sort by incident ID"
                                            @click="emit('toggleSort', 'id')"
                                        >
                                            ID
                                            <ChevronUp v-if="sortKey === 'id' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'id' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="px-3 py-3.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md transition-colors hover:text-foreground"
                                            aria-label="Sort by incident title"
                                            @click="emit('toggleSort', 'title')"
                                        >
                                            Incident
                                            <ChevronUp v-if="sortKey === 'title' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'title' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="w-44 px-4 py-3.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md transition-colors hover:text-foreground"
                                            aria-label="Sort by handlers"
                                            @click="emit('toggleSort', 'handlers')"
                                        >
                                            Handlers
                                            <ChevronUp v-if="sortKey === 'handlers' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'handlers' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="w-28 px-4 py-3.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md transition-colors hover:text-foreground"
                                            aria-label="Sort by reporter"
                                            @click="emit('toggleSort', 'reporter')"
                                        >
                                            Reporter
                                            <ChevronUp v-if="sortKey === 'reporter' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'reporter' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="w-36 px-4 py-3.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md transition-colors hover:text-foreground"
                                            aria-label="Sort by status"
                                            @click="emit('toggleSort', 'status')"
                                        >
                                            Status
                                            <ChevronUp v-if="sortKey === 'status' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'status' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th v-if="!readonly" class="w-24 px-4 py-3.5 pr-5 text-right">
                                        <span class="sr-only">Actions</span>
                                        <span aria-hidden="true" class="text-[10px] font-normal normal-case tracking-normal text-muted-foreground/40"
                                            >Actions</span
                                        >
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                <tr
                                    v-for="ticket in paginatedTickets"
                                    :key="ticket.id"
                                    :class="[
                                        'group cursor-pointer transition-all duration-150 hover:bg-muted/50 hover:shadow-[inset_3px_0_0_0] hover:shadow-primary/35',
                                        !readonly && selectedIds.includes(ticket.numericId) ? 'bg-primary/5 hover:bg-primary/10' : '',
                                    ]"
                                    @click="emit('openDetail', ticket)"
                                >
                                    <td v-if="!readonly" class="py-3.5 pl-5 pr-3" @click.stop>
                                        <Checkbox
                                            :checked="selectedIds.includes(ticket.numericId)"
                                            @update:checked="(val) => emit('toggleTicket', ticket.numericId, !!val)"
                                        />
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span
                                            class="font-mono text-xs font-bold text-muted-foreground/50 transition-colors group-hover:text-muted-foreground"
                                            >{{ ticket.id }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex max-w-sm flex-col">
                                            <span class="truncate text-sm font-semibold text-foreground transition-colors group-hover:text-primary">{{
                                                ticket.title
                                            }}</span>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                                    :style="getPriorityStyle(ticket.priority)"
                                                >
                                                    <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                                    {{ ticket.priority }}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground/40">·</span>
                                                <span class="text-[10px] font-medium uppercase tracking-tight text-muted-foreground/70">{{
                                                    ticket.category
                                                }}</span>
                                                <Badge
                                                    v-for="tag in ticket.tags"
                                                    :key="tag"
                                                    variant="secondary"
                                                    class="ml-1 border-border/50 px-1.5 py-0 text-[9px] font-medium"
                                                >
                                                    {{ tag }}
                                                </Badge>
                                                <span class="text-[10px] text-muted-foreground/40">·</span>
                                                <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                    <Clock class="h-2.5 w-2.5" />
                                                    {{ ticket.createdAt }}
                                                </div>
                                                <template v-if="ticket.commentsCount > 0">
                                                    <span class="text-[10px] text-muted-foreground/40">·</span>
                                                    <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                        <MessageSquare class="h-2.5 w-2.5" />
                                                        {{ ticket.commentsCount }}
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div v-if="ticket.handlers.length > 0" class="flex items-center gap-1.5">
                                            <div class="flex -space-x-2">
                                                <div
                                                    v-for="(h, i) in ticket.handlers.slice(0, 3)"
                                                    :key="h.id"
                                                    :style="{ zIndex: 3 - i }"
                                                    class="flex h-7 w-7 shrink-0 cursor-default items-center justify-center rounded-full border-2 border-background bg-muted text-[10px] font-bold"
                                                    @mouseenter="showHandlerTooltip($event, h.name)"
                                                    @mouseleave="hideHandlerTooltip"
                                                >
                                                    {{ getInitials(h.name) }}
                                                </div>
                                            </div>
                                            <span v-if="ticket.handlers.length > 3" class="text-[10px] font-bold text-muted-foreground"
                                                >+{{ ticket.handlers.length - 3 }}</span
                                            >
                                            <span
                                                v-if="ticket.handlers.length === 1"
                                                class="max-w-[90px] truncate text-xs font-medium text-muted-foreground"
                                                >{{ ticket.handlers[0].name }}</span
                                            >
                                        </div>
                                        <button
                                            v-else-if="!readonly"
                                            type="button"
                                            aria-label="Assign handler"
                                            class="hover:bg-primary/8 inline-flex items-center gap-1 rounded-md border border-dashed border-border/40 px-1.5 py-0.5 text-[10px] font-semibold italic text-muted-foreground/40 transition-colors hover:border-primary/30 hover:text-primary"
                                            @click.stop="emit('openAssign', ticket)"
                                        >
                                            <UserPlus class="h-2.5 w-2.5 not-italic" />
                                            Assign
                                        </button>
                                        <span v-else class="text-[10px] font-medium italic text-muted-foreground/50">Unassigned</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex min-w-0 items-center gap-1.5">
                                            <div
                                                class="flex h-6 w-6 shrink-0 cursor-default items-center justify-center rounded-full border border-border/40 bg-muted text-[10px] font-bold"
                                                @mouseenter="showReporterTooltip($event, ticket.reporter)"
                                                @mouseleave="hideReporterTooltip"
                                            >
                                                {{ getInitials(ticket.reporter) }}
                                            </div>
                                            <span class="max-w-[80px] truncate text-xs text-muted-foreground">{{ ticket.reporter }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <Badge
                                            variant="outline"
                                            :class="['inline-flex items-center gap-1 whitespace-nowrap border px-2 py-1 text-[10px] font-bold']"
                                            :style="getStatusStyle(ticket.status)"
                                        >
                                            <component :is="getStatusIcon(ticket.status)" class="h-3 w-3 shrink-0" />
                                            {{ ticket.status }}
                                        </Badge>
                                    </td>
                                    <td v-if="!readonly" class="px-4 py-3.5 pr-5 text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                v-if="ticket.status !== 'Resolved' && ticket.status !== 'Cancelled'"
                                                type="button"
                                                aria-label="Mark as resolved"
                                                :disabled="statusProcessing === ticket.numericId"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground/0 transition-all duration-150 hover:bg-emerald-500/10 disabled:opacity-50 group-hover:text-emerald-500"
                                                title="Mark as Resolved"
                                                @click.stop="
                                                    isStatusNoHandlers(ticket.status)
                                                        ? emit('openAssign', ticket, 'Resolved')
                                                        : emit('updateStatus', ticket, 'Resolved')
                                                "
                                            >
                                                <span
                                                    v-if="statusProcessing === ticket.numericId"
                                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                                                />
                                                <CheckCircle2 v-else class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label="Assign handler"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground/0 transition-all duration-150 hover:bg-blue-500/10 group-hover:text-blue-500"
                                                title="Assign Handler"
                                                @click.stop="emit('openAssign', ticket)"
                                            >
                                                <UserPlus class="h-3.5 w-3.5" />
                                            </button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <button
                                                        type="button"
                                                        aria-label="Open incident actions"
                                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground/40 outline-none transition-all duration-150 hover:bg-muted group-hover:text-foreground"
                                                    >
                                                        <MoreHorizontal class="h-4 w-4" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48">
                                                    <DropdownMenuLabel>Incident Actions</DropdownMenuLabel>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem @click="emit('openDetail', ticket)">
                                                        <ExternalLink class="mr-2 h-4 w-4" />View Details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="emit('openEdit', ticket)">
                                                        <Pencil class="mr-2 h-4 w-4" />Edit Incident
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="emit('openAssign', ticket)">
                                                        <UserPlus class="mr-2 h-4 w-4" />
                                                        Assign Handler
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="emit('openChangeStatus', ticket)">
                                                        <RefreshCcw class="mr-2 h-4 w-4" />
                                                        Change Status
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        v-if="ticket.status !== 'Resolved' && ticket.status !== 'Cancelled'"
                                                        :disabled="statusProcessing === ticket.numericId"
                                                        class="text-emerald-600 focus:bg-emerald-50 focus:text-emerald-700 dark:text-emerald-400 dark:focus:bg-emerald-950/40 dark:focus:text-emerald-300"
                                                        @click="
                                                            isStatusNoHandlers(ticket.status)
                                                                ? emit('openAssign', ticket, 'Resolved')
                                                                : emit('updateStatus', ticket, 'Resolved')
                                                        "
                                                    >
                                                        <span
                                                            v-if="statusProcessing === ticket.numericId"
                                                            class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                                                        />
                                                        <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                                                        Mark as Resolved
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="!['Resolved', 'Cancelled'].includes(ticket.status)"
                                                        :disabled="statusProcessing === ticket.numericId"
                                                        class="text-slate-500 focus:bg-slate-100 focus:text-slate-700 dark:text-slate-400 dark:focus:bg-slate-800/50 dark:focus:text-slate-300"
                                                        @click="emit('updateStatus', ticket, 'Cancelled')"
                                                    >
                                                        <span
                                                            v-if="statusProcessing === ticket.numericId"
                                                            class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                                                        />
                                                        <Lock v-else class="mr-2 h-4 w-4" />
                                                        Mark as Cancelled
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        class="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                                        @click="emit('openDelete', ticket)"
                                                        ><Trash2 class="mr-2 h-4 w-4" />Delete Incident</DropdownMenuItem
                                                    >
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>

            <div
                v-if="sortedTickets.length > 0"
                class="flex flex-col items-center justify-between gap-3 border-t border-border/50 px-4 py-3 sm:flex-row sm:px-5"
            >
                <div class="order-2 flex items-center gap-3 sm:order-1">
                    <span class="text-xs text-muted-foreground">
                        Showing
                        <span class="font-semibold text-foreground">{{ (currentPage - 1) * pageSize + 1 }}</span>
                        –
                        <span class="font-semibold text-foreground">{{ Math.min(currentPage * pageSize, sortedTickets.length) }}</span>
                        of
                        <span class="font-semibold text-foreground">{{ sortedTickets.length }}</span>
                    </span>
                    <Select
                        :model-value="String(pageSize)"
                        @update:model-value="
                            (v) => {
                                const n = parseInt(String(v), 10);
                                if (!Number.isNaN(n)) {
                                    emit('update:pageSize', n);
                                }
                            }
                        "
                    >
                        <SelectTrigger
                            aria-label="Rows per page"
                            class="h-8 w-[104px] rounded-lg border-border/60 bg-background/80 text-xs font-medium shadow-sm backdrop-blur-sm hover:border-primary/25"
                        >
                            <SelectValue placeholder="Per page" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="n in pageSizeOptions" :key="n" :value="String(n)"> {{ n }} / page </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="order-1 flex items-center gap-1 sm:order-2">
                    <button
                        type="button"
                        aria-label="Previous page"
                        :disabled="currentPage === 1"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-30"
                        @click="emit('update:currentPage', Math.max(1, currentPage - 1))"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>
                    <template v-for="(p, i) in pageRange" :key="i">
                        <span v-if="p === '...'" class="flex h-7 w-5 select-none items-center justify-center text-xs text-muted-foreground/50"
                            >…</span
                        >
                        <button
                            v-else
                            type="button"
                            :aria-label="`Go to page ${p}`"
                            :aria-current="currentPage === p ? 'page' : undefined"
                            :class="[
                                'inline-flex h-7 min-w-[28px] items-center justify-center rounded-lg border px-1 text-xs font-semibold transition-all duration-150',
                                currentPage === p
                                    ? 'border-foreground bg-foreground text-background shadow-sm'
                                    : 'border-border/60 text-muted-foreground hover:border-border hover:bg-muted hover:text-foreground',
                            ]"
                            @click="emit('update:currentPage', p as number)"
                        >
                            {{ p }}
                        </button>
                    </template>
                    <button
                        type="button"
                        aria-label="Next page"
                        :disabled="currentPage === totalPages"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-30"
                        @click="emit('update:currentPage', Math.min(totalPages, currentPage + 1))"
                    >
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </Card>

        <Teleport to="body">
            <Transition name="handler-tooltip">
                <div
                    v-if="handlerTooltip"
                    class="pointer-events-none fixed z-[9999]"
                    :style="{ left: `${handlerTooltip.x}px`, top: `${handlerTooltip.y}px`, transform: 'translate(-50%, calc(-100% - 8px))' }"
                >
                    <div
                        class="whitespace-nowrap rounded-lg border border-border bg-popover px-2.5 py-1.5 text-[11px] font-semibold text-popover-foreground shadow-lg"
                    >
                        {{ handlerTooltip.name }}
                    </div>
                    <div class="mx-auto h-0 w-0 border-x-[5px] border-t-[5px] border-x-transparent border-t-border" />
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="handler-tooltip">
                <div
                    v-if="reporterTooltip"
                    class="pointer-events-none fixed z-[9999]"
                    :style="{ left: `${reporterTooltip.x}px`, top: `${reporterTooltip.y}px`, transform: 'translate(-50%, calc(-100% - 8px))' }"
                >
                    <div
                        class="whitespace-nowrap rounded-lg border border-border bg-popover px-2.5 py-1.5 text-[11px] font-semibold text-popover-foreground shadow-lg"
                    >
                        {{ reporterTooltip.name }}
                    </div>
                    <div class="mx-auto h-0 w-0 border-x-[5px] border-t-[5px] border-x-transparent border-t-border" />
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.handler-tooltip-enter-active,
.handler-tooltip-leave-active {
    transition: opacity 0.12s ease;
}
.handler-tooltip-enter-from,
.handler-tooltip-leave-to {
    opacity: 0;
}
</style>
