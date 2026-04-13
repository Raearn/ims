<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import RichTextEditor from '@/components/RichTextEditor.vue';
import TicketComments from '@/components/TicketComments.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link, useForm, usePage } from '@inertiajs/vue3';
import { VisAxis, VisLine, VisXYContainer, VisArea } from '@unovis/vue';
import { ChartCrosshair } from '@/components/ui/chart';
import DonutChart from '@/components/DonutChart.vue';
import { CurveType } from '@unovis/ts';
import { AlertCircle, AlertTriangle, Ban, CheckCircle2, Circle, Clock, ImageIcon, Loader2, ListOrdered, Pause, Play, BarChart2, PieChart, RefreshCcw, ChevronDown, ChevronRight, ArrowUpRight, UserPlus, Search, X, MessageSquare, Crown, ShieldCheck, Headset, UserRound, FileText, Timer, Layers } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { laravelFetch } from '@/lib/laravelFetch';
import Sparkline from '@/components/Sparkline.vue';
import { ensureLucideIconsLoaded, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import { ref, computed, watch, onMounted, nextTick } from 'vue';

interface Stat {
    title: string;
    value: string | number;
    description: string;
    sparkline: number[];
    sparklineValueSuffix?: string;
    stroke: string;
}

interface ChartItem {
    name: string;
    count: number;
    color: string;
    hex: string;
}

interface TrendPoint {
    x: number;
    day: string;
    date: string;
    created: number;
    resolved: number;
}

interface TopRecurringTagRow {
    rank: number;
    tag: string;
    count: number;
}

interface ActivityItem {
    id: number;
    numericId: number;
    tktId: string;
    title: string;
    description: string | null;
    time: string;
    createdAtFormatted: string;
    reporter: string;
    reporterId?: number;
    priority: string;
    status?: string;
    category: string;
    tags?: string[];
    handlerIds: number[];
    handlers: { id: number; name: string }[];
    attachmentUrl: string | null;
}

interface User {
    id: number;
    name: string;
}

interface RecentComment {
    id: number;
    userName: string;
    userInitials: string;
    userRole: string;
    bodySnippet: string;
    snippetImageUrls: string[];
    ticketNumericId: number | null;
    ticketTktId: string;
    ticketTitle: string;
    ticketDescription: string | null;
    ticketStatus: string;
    ticketPriority: string;
    ticketCategory: string;
    ticketReporter: string;
    ticketReporterId: number | null;
    ticketCreatedAtFormatted: string;
    ticketHandlerIds: number[];
    ticketHandlers: { id: number; name: string }[];
    ticketTags?: string[];
    ticketAttachmentUrl: string | null;
    createdAt: string;
}

interface SeverityTicket {
    id: number; numericId: number; tktId: string; title: string;
    description: string | null; status: string; priority: string; category: string;
    tags?: string[];
    reporter: string; reporterId: number | null;
    handlerIds: number[]; handlers: { id: number; name: string }[];
    attachmentUrl: string | null;
    createdAtFormatted: string; time: string;
}

interface TicketStatusConfig {
    id: number;
    name: string;
    icon: string;
    color: string;
    handler_requirement: 'none' | 'optional' | 'required';
}

interface PriorityLegendItem {
    name: string;
    hex: string;
    icon: string;
}

interface CategoryLegendItem {
    name: string;
    hex: string;
}

interface CategoryChartChild {
    name: string;
    count: number;
}

interface CategoryChartGroup {
    id: number | null;
    name: string;
    hex: string;
    rootCount: number;
    total: number;
    children: CategoryChartChild[];
}

const {
    stats,
    period,
    periodLabel,
    sparklineLabels,
    trendData,
    severities,
    categoryChartGroups,
    priorityLegend,
    categoryLegend,
    topRecurring,
    recentActivity,
    recentComments,
    users,
    statuses,
} = defineProps<{
    stats: Stat[];
    period: string;
    periodLabel: string;
    sparklineLabels: string[];
    trendData: TrendPoint[];
    severities: ChartItem[];
    categoryChartGroups: CategoryChartGroup[];
    priorityLegend: PriorityLegendItem[];
    categoryLegend: CategoryLegendItem[];
    topRecurring: TopRecurringTagRow[];
    recentActivity: ActivityItem[];
    recentComments: RecentComment[];
    users: User[];
    statuses: TicketStatusConfig[];
}>();

const dashboardFirstRequiredStatusName = computed(
    () => statuses.find((s) => s.handler_requirement === 'required')?.name ?? 'In Progress',
);

function dashboardStatusHexStyle(statusName: string): Record<string, string> {
    const hex = statuses.find((s) => s.name === statusName)?.color;
    if (! hex) {
        return {};
    }
    return {
        backgroundColor: `${hex}26`,
        color: hex,
        borderColor: `${hex}40`,
    };
}

function dashboardStatusIcon(statusName: string) {
    const iconName = statuses.find((s) => s.name === statusName)?.icon;
    return resolveLucideIcon(iconName ?? 'Circle', Circle);
}

onMounted(() => {
    void ensureLucideIconsLoaded();
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
];

const totalCategoriesCount = computed(() => categoryChartGroups.reduce((sum, g) => sum + g.total, 0));
const totalSeverityCount   = computed(() => severities.reduce((sum, s) => sum + s.count, 0));
const maxRecurringCount    = computed(() => topRecurring.length ? Math.max(...topRecurring.map(i => i.count)) : 1);

const priorityHexByName = computed(() => {
    const m: Record<string, string> = {};
    for (const p of priorityLegend) {
        m[p.name] = p.hex;
    }
    for (const s of severities) {
        m[s.name] = s.hex;
    }
    return m;
});

const categoryHexByName = computed(() => {
    const m: Record<string, string> = {};
    for (const c of categoryLegend) {
        m[c.name] = c.hex;
    }
    return m;
});

function chartToneStyle(hex: string | undefined): Record<string, string> {
    if (! hex) {
        return {};
    }
    return {
        backgroundColor: `${hex}26`,
        color: hex,
        borderColor: `${hex}40`,
    };
}

function severitySlicePct(count: number): number {
    const t = totalSeverityCount.value;
    return t > 0 ? (count / t) * 100 : 0;
}

function categorySlicePct(count: number): number {
    const t = totalCategoriesCount.value;
    return t > 0 ? (count / t) * 100 : 0;
}

/**
 * Subcategory bar color: golden-angle steps on the hue wheel so 21+ siblings stay visually distinct
 * (no fixed palette wrap). Deterministic: same parent hex + same index → same color.
 */
const GOLDEN_ANGLE_DEG = 137.50776405003785;

function categoryChildHueOffset(parentHex: string): number {
    let n = 0;
    const clean = parentHex.replace(/^#/, '');
    for (let i = 0; i < clean.length; i++) {
        n = (n * 31 + clean.charCodeAt(i)) % 360;
    }
    return n;
}

function categoryChildBarColor(parentHex: string, childIndex: number): string {
    const baseHue = categoryChildHueOffset(parentHex);
    const hue = (baseHue + childIndex * GOLDEN_ANGLE_DEG) % 360;
    const sat = 52 + (childIndex % 4) * 4;
    const light = 42 + (childIndex % 3) * 5;
    const accent = `hsl(${hue} ${sat}% ${light}%)`;
    const parentPct = 28 + (childIndex % 7) * 2;
    return `color-mix(in srgb, ${parentHex} ${parentPct}%, ${accent})`;
}

/** Subcategory panel open state (accordion). Key in set = expanded; default is collapsed (empty set). */
const categorySubcatsExpandedKeys = ref<Set<string>>(new Set());

function categoryChartGroupKey(group: CategoryChartGroup, index: number): string {
    return group.id != null ? `cat-${group.id}` : `orphan-${index}-${group.name}`;
}

function isCategorySubcatsExpanded(key: string): boolean {
    return categorySubcatsExpandedKeys.value.has(key);
}

function toggleCategorySubcats(key: string): void {
    const next = new Set(categorySubcatsExpandedKeys.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    categorySubcatsExpandedKeys.value = next;
}

watch(
    () => categoryChartGroups.map((g, i) => categoryChartGroupKey(g, i)).join('|'),
    () => {
        const valid = new Set(categoryChartGroups.map((g, i) => categoryChartGroupKey(g, i)));
        const filtered = new Set([...categorySubcatsExpandedKeys.value].filter((k) => valid.has(k)));
        if (filtered.size !== categorySubcatsExpandedKeys.value.size) {
            categorySubcatsExpandedKeys.value = filtered;
        }
    },
);

// Static color palettes per stat index so Tailwind can detect them at build time
const statColors = [
    {
        card: 'border-0 bg-gradient-to-br from-rose-50/90 via-rose-50/40 to-background dark:from-rose-950/35 dark:via-rose-950/15 dark:to-card shadow-sm ring-1 ring-rose-200/70 ring-inset dark:ring-rose-800/45 transition-all duration-300 hover:shadow-md hover:ring-rose-300/80 dark:hover:ring-rose-700/55',
        text: 'text-rose-800 dark:text-rose-200',
        iconBg: 'bg-rose-500/[0.14] text-rose-600 shadow-inner shadow-rose-900/5 ring-1 ring-rose-500/20 dark:bg-rose-500/25 dark:text-rose-300 dark:ring-rose-400/20',
    },
    {
        card: 'border-0 bg-gradient-to-br from-orange-50/90 via-orange-50/40 to-background dark:from-orange-950/35 dark:via-orange-950/15 dark:to-card shadow-sm ring-1 ring-orange-200/70 ring-inset dark:ring-orange-800/45 transition-all duration-300 hover:shadow-md hover:ring-orange-300/80 dark:hover:ring-orange-700/55',
        text: 'text-orange-900 dark:text-orange-200',
        iconBg: 'bg-orange-500/[0.14] text-orange-600 shadow-inner shadow-orange-900/5 ring-1 ring-orange-500/20 dark:bg-orange-500/25 dark:text-orange-300 dark:ring-orange-400/20',
    },
    {
        card: 'border-0 bg-gradient-to-br from-emerald-50/90 via-emerald-50/40 to-background dark:from-emerald-950/35 dark:via-emerald-950/15 dark:to-card shadow-sm ring-1 ring-emerald-200/70 ring-inset dark:ring-emerald-800/45 transition-all duration-300 hover:shadow-md hover:ring-emerald-300/80 dark:hover:ring-emerald-700/55',
        text: 'text-emerald-900 dark:text-emerald-200',
        iconBg: 'bg-emerald-500/[0.14] text-emerald-600 shadow-inner shadow-emerald-900/5 ring-1 ring-emerald-500/20 dark:bg-emerald-500/25 dark:text-emerald-300 dark:ring-emerald-400/20',
    },
    {
        card: 'border-0 bg-gradient-to-br from-blue-50/90 via-blue-50/40 to-background dark:from-blue-950/35 dark:via-blue-950/15 dark:to-card shadow-sm ring-1 ring-blue-200/70 ring-inset dark:ring-blue-800/45 transition-all duration-300 hover:shadow-md hover:ring-blue-300/80 dark:hover:ring-blue-700/55',
        text: 'text-blue-900 dark:text-blue-200',
        iconBg: 'bg-blue-500/[0.14] text-blue-600 shadow-inner shadow-blue-900/5 ring-1 ring-blue-500/20 dark:bg-blue-500/25 dark:text-blue-300 dark:ring-blue-400/20',
    },
] as const;

const statWidgetIcons = [AlertCircle, Clock, CheckCircle2, Timer] as const;

/** Shared chrome for non-stat dashboard panels (the `stats` grid uses `statColors` instead). */
const dashboardPanelCard =
    'flex min-w-0 flex-col overflow-hidden rounded-xl border border-border/50 bg-card text-card-foreground shadow-md ring-1 ring-border/20 transition-all duration-300 hover:shadow-lg dark:ring-border/15';

const dashboardPanelHeader =
    '!flex !flex-col !gap-3 !border-b !border-border/50 !bg-gradient-to-b !from-muted/35 !via-muted/15 !to-transparent !px-5 !pb-4 !pt-5 dark:!from-muted/25 dark:!via-muted/10';

const dashboardPanelHeaderRow = 'flex items-start justify-between gap-3';

const dashboardPanelHeaderIconWrap =
    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border/40 bg-background/80 text-primary shadow-sm dark:bg-background/60';

const dashboardContentWell =
    'rounded-xl border border-border/40 bg-muted/15 p-4 shadow-inner dark:bg-muted/10';

const dashboardPanelFooter =
    'mt-auto border-t border-border/50 bg-gradient-to-b from-transparent to-muted/25 px-5 py-3.5 dark:to-muted/15';

const dashboardSegmentedControl =
    'inline-flex h-8 shrink-0 items-center gap-0.5 rounded-lg border border-border/50 bg-muted/40 p-0.5 shadow-inner';

const dashboardSegmentedBtnOn =
    'flex h-7 w-7 items-center justify-center rounded-md bg-background text-primary shadow-sm ring-1 ring-border/40 transition-all duration-200';

const dashboardSegmentedBtnOff =
    'flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-all duration-200 hover:bg-muted/80 hover:text-foreground';

const severityChartType = ref<'bar' | 'donut'>('donut');

const displayData = computed(() => {
    return trendData.map((d, i) => ({
        ...d,
        x: i,
        displayLabel: d.day + ' - ' + d.date,
    }));
});

const chartItems = [
    { name: 'created', label: 'New', color: '#f43f5e' },
    { name: 'resolved', label: 'Resolved', color: '#10b981' },
];

const xTickValues = computed(() => displayData.value.map(d => d.x));

const xTickFormat = (x: number) => {
    const d = displayData.value[Math.round(x)];
    if (!d) return '';
    return trendData.length <= 14 ? d.day : d.date;
};

const totalCreated  = computed(() => displayData.value.reduce((s, d) => s + d.created, 0));
const totalResolved = computed(() => displayData.value.reduce((s, d) => s + d.resolved, 0));


const isRefreshing = ref(false);
const refresh = () => {
    if (isRefreshing.value) return;
    isRefreshing.value = true;
    router.reload({ onFinish: () => { isRefreshing.value = false; } });
};

const isRefreshingActivity = ref(false);
const refreshActivity = () => {
    if (isRefreshingActivity.value) return;
    isRefreshingActivity.value = true;
    router.reload({ only: ['recentActivity'], onFinish: () => { isRefreshingActivity.value = false; } });
};

const isRefreshingComments = ref(false);
const refreshComments = () => {
    if (isRefreshingComments.value) return;
    isRefreshingComments.value = true;
    router.reload({ only: ['recentComments'], onFinish: () => { isRefreshingComments.value = false; } });
};

// ── Helpers ────────────────────────────────────────────────────────────────
const isEmptyHtml = (html: string): boolean => !html.replace(/<[^>]*>/g, '').trim();

const getInitials = (name: string) => {
    if (name === 'Unassigned') return 'UN';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Open':        return 'bg-rose-500/15 text-rose-500 border-rose-500/30';
        case 'In Progress': return 'bg-blue-500/15 text-blue-500 border-blue-500/30';
        case 'On Hold':     return 'bg-amber-500/15 text-amber-500 border-amber-500/30';
        case 'Resolved':    return 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30';
        case 'Closed': // legacy audit / old data
        case 'Cancelled':   return 'bg-slate-500/15 text-slate-500 border-slate-500/30';
        default:            return 'bg-secondary text-secondary-foreground';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'Open':        return AlertTriangle;
        case 'In Progress': return Play;
        case 'On Hold':     return Pause;
        case 'Resolved':    return CheckCircle2;
        case 'Closed': // legacy
        case 'Cancelled':   return Ban;
        default:            return Circle;
    }
};

function getPriorityIcon(priority: string) {
    const iconName = priorityLegend.find((p) => p.name === priority)?.icon;
    return resolveLucideIcon(iconName ?? 'Circle', Circle);
}

const AVATAR_COLORS = [
    'bg-violet-500/15 text-violet-500 border-violet-500/20',
    'bg-sky-500/15 text-sky-500 border-sky-500/20',
    'bg-emerald-500/15 text-emerald-500 border-emerald-500/20',
    'bg-orange-500/15 text-orange-500 border-orange-500/20',
    'bg-rose-500/15 text-rose-500 border-rose-500/20',
    'bg-amber-500/15 text-amber-500 border-amber-500/20',
    'bg-teal-500/15 text-teal-500 border-teal-500/20',
    'bg-indigo-500/15 text-indigo-500 border-indigo-500/20',
];

const getAvatarColor = (name: string): string => {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
};

const getRoleBadgeClass = (role: string): string => {
    switch (role) {
        case 'admin':      return 'bg-rose-500/15 text-rose-500 border-rose-500/30';
        case 'supervisor': return 'bg-amber-500/15 text-amber-500 border-amber-500/30';
        case 'technical':  return 'bg-blue-500/15 text-blue-500 border-blue-500/30';
        default:           return 'bg-muted text-muted-foreground border-border';
    }
};

const getRoleIcon = (role: string) => {
    switch (role) {
        case 'admin':      return Crown;
        case 'supervisor': return ShieldCheck;
        case 'technical':  return Headset;
        default:           return UserRound;
    }
};

const getRoleLabel = (role: string): string => {
    switch (role) {
        case 'admin':      return 'Admin';
        case 'supervisor': return 'Supervisor';
        case 'technical':  return 'Technical';
        default:           return 'User';
    }
};

// ── Detail modal ───────────────────────────────────────────────────────────
const isDetailModalOpen = ref(false);
const selectedActivity  = ref<ActivityItem | null>(null);

const openDetailModal = (item: ActivityItem) => {
    selectedActivity.value  = item;
    isDetailModalOpen.value = true;
};

const openCommentTicketDetail = (comment: RecentComment) => {
    if (!comment.ticketNumericId) return;
    openDetailModal({
        id:                 comment.ticketNumericId,
        numericId:          comment.ticketNumericId,
        tktId:              comment.ticketTktId,
        title:              comment.ticketTitle,
        description:        comment.ticketDescription,
        time:               comment.createdAt,
        createdAtFormatted: comment.ticketCreatedAtFormatted,
        reporter:           comment.ticketReporter,
        reporterId:         comment.ticketReporterId ?? undefined,
        priority:           comment.ticketPriority,
        status:             comment.ticketStatus,
        category:           comment.ticketCategory,
        handlerIds:         comment.ticketHandlerIds,
        handlers:           comment.ticketHandlers,
        tags:               comment.ticketTags ?? [],
        attachmentUrl:      comment.ticketAttachmentUrl,
    });
};
// ──────────────────────────────────────────────────────────────────────────

// ── Assign modal ───────────────────────────────────────────────────────────
const isAssignModalOpen    = ref(false);
const assigningTicket      = ref<ActivityItem | null>(null);
const assignHandlerSearch  = ref('');
const assignStatusOverride = ref<string>(
    statuses.find((s) => s.handler_requirement === 'required')?.name ?? 'In Progress',
);

// ── Severity drill-down modal ───────────────────────────────────────────────
const severityModalOpen     = ref(false);
const severityModalPriority = ref('');
const severityModalTickets  = ref<SeverityTicket[]>([]);
const severityModalLoading  = ref(false);

function dashboardReportingPeriod(): string {
    return String(usePage().props.period ?? '7d');
}

/** Query string for dashboard drill-down JSON routes (always includes current reporting period). */
function dashboardPeekQueryString(additional: Record<string, string> = {}): string {
    return new URLSearchParams({
        period: dashboardReportingPeriod(),
        ...additional,
    }).toString();
}

async function openSeverityModal(priority: string) {
    severityModalPriority.value = priority;
    severityModalTickets.value  = [];
    severityModalOpen.value     = true;
    severityModalLoading.value  = true;
    await nextTick();
    try {
        const url = `${route('tickets.by-priority', { priority })}?${dashboardPeekQueryString()}`;
        const res = await laravelFetch(url);
        if (res.ok) severityModalTickets.value = await res.json();
    } finally {
        severityModalLoading.value = false;
    }
}

const categoryModalOpen     = ref(false);
const categoryModalName     = ref('');
const categoryModalIsRootScope = ref(false);
const categoryModalTickets  = ref<SeverityTicket[]>([]);
const categoryModalLoading  = ref(false);

async function openCategoryModal(category: string): Promise<void> {
    categoryModalIsRootScope.value = false;
    categoryModalName.value = category;
    categoryModalTickets.value = [];
    categoryModalOpen.value = true;
    categoryModalLoading.value = true;
    await nextTick();
    try {
        const url = `${route('tickets.by-category', { category })}?${dashboardPeekQueryString()}`;
        const res = await laravelFetch(url);
        if (res.ok) {
            categoryModalTickets.value = await res.json();
        }
    } finally {
        categoryModalLoading.value = false;
    }
}

async function openCategoryRootModal(group: CategoryChartGroup): Promise<void> {
    if (group.id == null) {
        await openCategoryModal(group.name);
        return;
    }
    categoryModalIsRootScope.value = true;
    categoryModalName.value = `${group.name} (all types)`;
    categoryModalTickets.value = [];
    categoryModalOpen.value = true;
    categoryModalLoading.value = true;
    await nextTick();
    try {
        const url = `${route('tickets.by-category-root', group.id)}?${dashboardPeekQueryString()}`;
        const res = await laravelFetch(url);
        if (res.ok) {
            categoryModalTickets.value = await res.json();
        }
    } finally {
        categoryModalLoading.value = false;
    }
}

const topRecurringThemeModalOpen     = ref(false);
const topRecurringThemeModalLabel    = ref('');
const topRecurringThemeModalTickets  = ref<SeverityTicket[]>([]);
const topRecurringThemeModalLoading  = ref(false);

async function openTopRecurringThemeModal(themeLabel: string) {
    topRecurringThemeModalLabel.value   = themeLabel;
    topRecurringThemeModalTickets.value  = [];
    topRecurringThemeModalOpen.value     = true;
    topRecurringThemeModalLoading.value  = true;
    await nextTick();
    try {
        const url = `${route('tickets.by-tag')}?${dashboardPeekQueryString({ name: themeLabel })}`;
        const res = await laravelFetch(url);
        if (res.ok) topRecurringThemeModalTickets.value = await res.json();
    } finally {
        topRecurringThemeModalLoading.value = false;
    }
}

const openIncidentsListModalOpen = ref(false);
const openIncidentsListTickets = ref<SeverityTicket[]>([]);
const openIncidentsListLoading = ref(false);

async function openOpenIncidentsListModal(): Promise<void> {
    openIncidentsListTickets.value = [];
    openIncidentsListModalOpen.value = true;
    openIncidentsListLoading.value = true;
    await nextTick();
    try {
        const url = `${route('tickets.by-open')}?${dashboardPeekQueryString()}`;
        const res = await laravelFetch(url);
        if (res.ok) {
            openIncidentsListTickets.value = await res.json();
        }
    } finally {
        openIncidentsListLoading.value = false;
    }
}

function selectOpenIncidentFromPeek(t: SeverityTicket): void {
    openIncidentsListModalOpen.value = false;
    void nextTick(() => {
        openDetailModal({
            id: t.id,
            numericId: t.numericId,
            tktId: t.tktId,
            title: t.title,
            description: t.description,
            status: t.status,
            priority: t.priority,
            category: t.category,
            tags: t.tags ?? [],
            reporter: t.reporter,
            reporterId: t.reporterId ?? undefined,
            handlerIds: t.handlerIds,
            handlers: t.handlers,
            attachmentUrl: t.attachmentUrl,
            createdAtFormatted: t.createdAtFormatted,
            time: t.time,
        });
    });
}
// ──────────────────────────────────────────────────────────────────────────

const assignForm = useForm({
    handler_ids: [] as number[],
    solution: '',
});

const assignModalTargetStatuses = computed(() => {
    const ticket = assigningTicket.value;
    if (ticket?.status) {
        return statuses.filter((s) => s.name !== ticket.status);
    }

    return statuses.filter((s) => s.handler_requirement === 'required');
});

const assignTargetRequiresHandlers = computed(() => {
    const req = statuses.find((s) => s.name === assignStatusOverride.value)?.handler_requirement;

    return req === 'required';
});

const filteredAssignUsers = computed(() => {
    if (!assignHandlerSearch.value.trim()) return users;
    const q = assignHandlerSearch.value.toLowerCase();
    return users.filter(u => u.name.toLowerCase().includes(q));
});

const openAssignModal = (ticket: ActivityItem, defaultStatus?: string) => {
    assigningTicket.value   = ticket;
    assignForm.handler_ids  = [...ticket.handlerIds];
    assignHandlerSearch.value  = '';
    assignStatusOverride.value = defaultStatus ?? dashboardFirstRequiredStatusName.value;
    isAssignModalOpen.value    = true;
};

const submitAssign = () => {
    if (!assigningTicket.value) return;
    assignForm
        .transform(data => ({
            handler_ids: data.handler_ids,
            status: assignStatusOverride.value,
            ...(assignStatusOverride.value === 'Resolved' ? { solution: data.solution } : {}),
        }))
        .patch(route('tickets.handlers.update', { ticket: assigningTicket.value.numericId }), {
            preserveScroll: true,
            onSuccess: () => {
                isAssignModalOpen.value = false;
                assigningTicket.value   = null;
            },
        });
};

watch(assignStatusOverride, (name) => {
    if (statuses.find((s) => s.name === name)?.handler_requirement === 'none') {
        assignForm.handler_ids = [];
    }
});

watch(isAssignModalOpen, (val) => {
    if (!val) {
        assignHandlerSearch.value  = '';
        assigningTicket.value      = null;
        assignStatusOverride.value = dashboardFirstRequiredStatusName.value;
        assignForm.solution        = '';
    }
});
// ──────────────────────────────────────────────────────────────────────────
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
            <div
                class="flex flex-col gap-5 rounded-2xl border border-border/60 bg-gradient-to-b from-muted/40 via-background to-background p-4 shadow-sm ring-1 ring-border/30 dark:from-muted/15 dark:via-card/90 dark:to-card sm:p-5 md:gap-6 md:p-6"
            >
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div class="min-w-0 space-y-1">
                        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Dashboard</h2>
                        <p class="text-sm text-muted-foreground">Overview of incidents and system activity.</p>
                    </div>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                        <Select :model-value="period" @update:model-value="(v) => router.get(route('dashboard'), { period: v }, { preserveState: true, replace: true })">
                            <SelectTrigger class="h-9 w-full bg-background/60 backdrop-blur-sm border-border/60 shadow-sm transition-colors hover:border-primary/30 hover:bg-background/80 sm:w-[140px]">
                                <SelectValue placeholder="Select period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="7d">Last 7 Days</SelectItem>
                                    <SelectItem value="30d">Last 30 Days</SelectItem>
                                    <SelectItem value="this_month">This Month</SelectItem>
                                    <SelectItem value="last_month">Last Month</SelectItem>
                                    <SelectItem value="ytd">Year to Date</SelectItem>
                                    <SelectItem value="all">All Time</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <button
                            type="button"
                            aria-label="Refresh dashboard"
                            @click="refresh"
                            :disabled="isRefreshing"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-background/60 text-muted-foreground shadow-sm backdrop-blur-sm transition-colors hover:border-primary/30 hover:bg-background/80 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            title="Refresh"
                        >
                            <RefreshCcw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                        </button>
                        <a
                            :href="route('dashboard.export-pdf', { period })"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 text-sm font-medium text-muted-foreground shadow-sm backdrop-blur-sm transition-colors hover:border-primary/30 hover:bg-background/80 hover:text-foreground sm:flex-initial"
                            title="Export analytics report as PDF"
                        >
                            <FileText class="h-3.5 w-3.5 shrink-0" />
                            Export PDF
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-4">
                <Card
                    v-for="(stat, idx) in stats"
                    :key="stat.title"
                    :class="cn('group relative overflow-hidden ring-1 ring-border/20 dark:ring-border/15', statColors[idx]?.card)"
                >
                    <div
                        class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full opacity-[0.12] blur-2xl transition-opacity duration-300 group-hover:opacity-[0.18]"
                        :style="{ backgroundColor: stat.stroke }"
                        aria-hidden="true"
                    />
                    <CardHeader class="relative flex flex-row items-start gap-3 space-y-0 p-5 pb-3">
                        <div
                            :class="cn(
                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-[1.03]',
                                statColors[idx]?.iconBg,
                            )"
                        >
                            <component :is="statWidgetIcons[idx] ?? AlertCircle" class="h-5 w-5" stroke-width="2" />
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <CardTitle :class="cn('text-sm font-semibold leading-snug tracking-tight', statColors[idx]?.text)">
                                {{ stat.title }}
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="relative px-5 pb-5 pt-0">
                        <div class="flex items-end justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-2xl font-bold tracking-tight text-foreground md:text-3xl">{{ stat.value }}</div>
                            </div>
                            <div class="h-12 w-24 shrink-0">
                                <Sparkline
                                    :data="stat.sparkline"
                                    :labels="sparklineLabels"
                                    :value-suffix="stat.sparklineValueSuffix ?? ''"
                                    :stroke="stat.stroke"
                                    :width="96"
                                    :height="48"
                                />
                            </div>
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-muted-foreground">{{ stat.description }}</p>
                    </CardContent>
                </Card>
                </div>
            </div>

            <!-- Row 1: Recent Activity + Incidents Over Time -->
            <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                <!-- Recent Activity -->
                <Card :class="dashboardPanelCard">
                    <CardHeader :class="dashboardPanelHeader">
                        <div :class="dashboardPanelHeaderRow">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div :class="dashboardPanelHeaderIconWrap">
                                    <AlertTriangle class="h-4 w-4" stroke-width="2" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <CardTitle class="text-lg font-semibold tracking-tight">Recent Open Incidents</CardTitle>
                                    <CardDescription class="leading-relaxed">Latest unresolved incidents</CardDescription>
                                </div>
                            </div>
                            <button
                                type="button"
                                aria-label="Refresh recent incidents"
                                @click="refreshActivity"
                                :disabled="isRefreshingActivity"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-border/50 bg-background/80 text-muted-foreground shadow-sm transition-all hover:border-primary/30 hover:bg-background hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                title="Refresh"
                            >
                                <RefreshCcw :class="['h-3.5 w-3.5', isRefreshingActivity && 'animate-spin']" />
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent class="flex-1 !p-0 px-5 pb-4 pt-0">
                        <div :class="cn(dashboardContentWell, 'space-y-2')">
                        <div
                            v-for="item in recentActivity"
                            :key="item.id"
                            class="group min-w-0 cursor-pointer rounded-xl border border-border/40 bg-gradient-to-br from-background to-muted/25 px-4 py-3 shadow-sm transition-all duration-300 hover:border-border/60 hover:to-muted/45 hover:shadow-md"
                            @click="openDetailModal(item)"
                        >
                            <!-- Top row: title + priority badge / actions -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold leading-snug text-foreground truncate">{{ item.title }}</p>
                                    <p class="mt-0.5 text-[10px] font-mono text-muted-foreground/50 uppercase tracking-wider">{{ item.tktId }}</p>
                                </div>
                                <!-- Badge reserves layout space; actions overlay it absolutely -->
                                <div class="relative shrink-0 self-start">
                                    <span
                                        class="block rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide transition-opacity duration-150 group-hover:opacity-0 pointer-events-none"
                                        :class="['border', !priorityHexByName[item.priority] && 'bg-muted text-muted-foreground border-border']"
                                        :style="chartToneStyle(priorityHexByName[item.priority])"
                                    >{{ item.priority }}</span>
                                    <!-- Actions: absolutely positioned at the same right edge, revealed on hover -->
                                    <div class="absolute top-1/2 right-0 -translate-y-1/2 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                                        <button
                                            type="button"
                                            @click.stop="openAssignModal(item, 'Resolved')"
                                            class="h-6 w-6 inline-flex items-center justify-center rounded-md text-emerald-500 hover:bg-emerald-500/10 transition-all duration-150"
                                            title="Mark as Resolved"
                                        >
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="openAssignModal(item)"
                                            class="h-6 w-6 inline-flex items-center justify-center rounded-md text-blue-500 hover:bg-blue-500/10 transition-all duration-150"
                                            title="Assign Handler"
                                        >
                                            <UserPlus class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Meta row -->
                            <div class="mt-2 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                <span
                                    :class="['rounded px-1.5 py-0.5 font-medium border', !categoryHexByName[item.category] && 'bg-muted text-muted-foreground border-border']"
                                    :style="chartToneStyle(categoryHexByName[item.category])"
                                >
                                    {{ item.category }}
                                </span>
                                <span class="opacity-30">·</span>
                                <div class="flex items-center gap-1">
                                    <Clock class="h-3 w-3 opacity-60" />
                                    <span>{{ item.time }}</span>
                                </div>
                                <span class="opacity-30">·</span>
                                <span class="font-medium text-foreground/60">{{ item.reporter }}</span>
                            </div>
                        </div>
                        <div v-if="recentActivity.length === 0" class="flex flex-col items-center justify-center py-10 transition-all duration-300">
                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted/50 shadow-inner">
                                <CheckCircle2 class="h-6 w-6 text-muted-foreground/40" />
                            </div>
                            <p class="text-sm font-semibold text-foreground">No open incidents</p>
                            <p class="mt-1 text-xs text-muted-foreground">You're all caught up!</p>
                        </div>
                        </div>
                    </CardContent>
                    <div :class="dashboardPanelFooter">
                        <button
                            type="button"
                            class="group flex w-full items-center justify-between text-left text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                            @click="openOpenIncidentsListModal"
                        >
                            <span>View all open incidents</span>
                            <ArrowUpRight class="h-3.5 w-3.5 shrink-0 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                        </button>
                    </div>
                </Card>

                <!-- Incidents Over Time -->
                <Card :class="dashboardPanelCard">
                    <CardHeader :class="dashboardPanelHeader">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <div :class="dashboardPanelHeaderIconWrap">
                                <Timer class="h-4 w-4" stroke-width="2" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <CardTitle class="text-lg font-semibold tracking-tight">Incidents Over Time</CardTitle>
                                <p class="text-sm text-muted-foreground">
                                    <span class="font-semibold text-rose-500">{{ totalCreated }}</span> new ·
                                    <span class="font-semibold text-emerald-500">{{ totalResolved }}</span> resolved
                                </p>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <span class="inline-block h-2 w-4 rounded-full bg-rose-500 opacity-80"></span>
                                        New incidents
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <span class="inline-block h-2 w-4 rounded-full bg-emerald-500 opacity-80"></span>
                                        Resolved
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex-1 min-w-0 !p-0 px-5 pb-5 pt-0">
                        <div :class="dashboardContentWell">
                        <svg width="0" height="0" class="block">
                            <defs>
                                <linearGradient id="roseGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.18"/>
                                    <stop offset="100%" stop-color="#f43f5e" stop-opacity="0"/>
                                </linearGradient>
                                <linearGradient id="emeraldGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.15"/>
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <!-- Chart + interactive overlay -->
                        <div class="relative h-[180px] w-full min-w-0 sm:h-[220px]">
                            <!-- Unovis chart -->
                            <VisXYContainer :data="displayData" class="h-full w-full" :duration="350" :class="trendData.length > 14 ? 'range-30' : 'range-7'">
                                <VisAxis type="x" :x="(d: TrendPoint) => d.x" :grid-line="false" :tick-line="false" :domain-line="false" :tick-format="xTickFormat" :tick-values="xTickValues" />
                                <VisAxis type="y" :grid-line="true" :tick-line="false" :domain-line="false" />
                                <!-- Areas -->
                                <VisArea v-if="trendData.length <= 14" :x="(d: TrendPoint) => d.x" :y="(d: TrendPoint) => d.created" color="url(#roseGradient)" :opacity="1" :curve-type="CurveType.MonotoneX" />
                                <VisArea v-if="trendData.length <= 14" :x="(d: TrendPoint) => d.x" :y="(d: TrendPoint) => d.resolved" color="url(#emeraldGradient)" :opacity="1" :curve-type="CurveType.MonotoneX" />
                                <!-- Lines -->
                                <VisLine :x="(d: TrendPoint) => d.x" :y="(d: TrendPoint) => d.created" color="#f43f5e" :stroke-width="2" :curve-type="CurveType.MonotoneX" />
                                <VisLine :x="(d: TrendPoint) => d.x" :y="(d: TrendPoint) => d.resolved" color="#10b981" :stroke-width="2" :curve-type="CurveType.MonotoneX" />
                                <!-- Crosshair: cursor-following dots + tooltip -->
                                <ChartCrosshair
                                    :colors="['#f43f5e', '#10b981']"
                                    index="displayLabel"
                                    :items="chartItems"
                                />
                            </VisXYContainer>
                        </div>
                        </div>
                    </CardContent>
                </Card>

            </div>

            <!-- Analytics: charts stack in the left column, themes + comments on the right (avoids a tall empty cell beside Top Recurring) -->
            <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-4 sm:gap-6">
                <!-- Incidents by Severity -->
                <Card :class="dashboardPanelCard">
                    <CardHeader :class="dashboardPanelHeader">
                        <div :class="dashboardPanelHeaderRow">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div :class="dashboardPanelHeaderIconWrap">
                                    <ShieldCheck class="h-4 w-4" stroke-width="2" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <CardTitle class="text-lg font-semibold tracking-tight">Incidents by Severity</CardTitle>
                                    <CardDescription>Breakdown by priority level</CardDescription>
                                </div>
                            </div>
                            <div :class="dashboardSegmentedControl" role="group" aria-label="Chart type">
                                <button type="button" @click="severityChartType = 'bar'" :class="severityChartType === 'bar' ? dashboardSegmentedBtnOn : dashboardSegmentedBtnOff" title="Bar Chart">
                                    <BarChart2 class="h-4 w-4" />
                                </button>
                                <button type="button" @click="severityChartType = 'donut'" :class="severityChartType === 'donut' ? dashboardSegmentedBtnOn : dashboardSegmentedBtnOff" title="Donut Chart">
                                    <PieChart class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="!p-0 px-5 pb-5 pt-0">
                        <div :class="dashboardContentWell">
                        <div v-if="severityChartType === 'bar'" class="space-y-3 sm:space-y-5">
                            <template v-if="severities.length">
                                <div v-for="sev in severities" :key="sev.name" class="group relative cursor-pointer" @click="openSeverityModal(sev.name)">
                                    <div class="flex items-center justify-between text-sm mb-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: sev.hex }"></div>
                                            <span class="font-medium transition-colors group-hover:text-foreground truncate">{{ sev.name }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0 ml-2">
                                            <span class="text-xs font-semibold text-muted-foreground group-hover:text-foreground transition-colors">{{ Math.round(severitySlicePct(sev.count)) }}%</span>
                                            <span class="text-muted-foreground w-6 text-right">{{ sev.count }}</span>
                                        </div>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                        <div class="h-full transition-all duration-500 ease-out group-hover:opacity-80" :style="{ width: `${severitySlicePct(sev.count)}%`, backgroundColor: sev.hex }"></div>
                                    </div>
                                    <div class="absolute -inset-x-2 -inset-y-2.5 z-[-1] rounded-lg bg-muted/50 opacity-0 transition-opacity group-hover:opacity-100"></div>
                                </div>
                            </template>
                            <p v-else class="py-10 text-center text-sm text-muted-foreground">Configure ticket priorities in Settings to see severity breakdown.</p>
                        </div>
                        <DonutChart v-else-if="totalSeverityCount > 0" :data="severities" :total="totalSeverityCount" @segment-click="openSeverityModal" />
                        <p v-else class="py-10 text-center text-sm text-muted-foreground">No incidents in this period.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Incidents by Category -->
                <Card :class="dashboardPanelCard">
                    <CardHeader :class="dashboardPanelHeader">
                        <div class="flex min-w-0 items-start gap-3">
                            <div :class="dashboardPanelHeaderIconWrap">
                                <Layers class="h-4 w-4" stroke-width="2" />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <CardTitle class="text-lg font-semibold tracking-tight">Incidents by Category</CardTitle>
                                <CardDescription class="text-xs leading-relaxed sm:text-sm">
                                    Sub-types are hidden until you expand. Click the parent row for all types in that family; use sub-rows for a single type.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="!p-0 px-5 pb-5 pt-0">
                        <div :class="dashboardContentWell">
                        <div v-if="categoryChartGroups.length" class="divide-y divide-border/50">
                            <div
                                v-for="(group, gi) in categoryChartGroups"
                                :key="group.id ?? `orphan-${group.name}-${gi}`"
                                class="py-4 first:pt-0 last:pb-0"
                            >
                                <div
                                    class="group/root relative cursor-pointer rounded-lg py-1 -mx-1 px-1"
                                    @click="openCategoryRootModal(group)"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                        <div class="flex min-w-0 flex-1 items-center gap-2">
                                            <div class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: group.hex }" />
                                            <span class="truncate font-medium transition-colors group-hover/root:text-foreground">{{ group.name }}</span>
                                            <button
                                                v-if="group.children.length"
                                                type="button"
                                                class="inline-flex shrink-0 items-center gap-1 rounded-md px-1 py-0.5 text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                                :aria-expanded="isCategorySubcatsExpanded(categoryChartGroupKey(group, gi))"
                                                :aria-label="isCategorySubcatsExpanded(categoryChartGroupKey(group, gi)) ? 'Hide subcategories' : 'Show subcategories'"
                                                @click.stop="toggleCategorySubcats(categoryChartGroupKey(group, gi))"
                                            >
                                                <span class="hidden tabular-nums text-[11px] font-medium sm:inline">{{ group.children.length }}</span>
                                                <ChevronDown
                                                    class="h-3.5 w-3.5 shrink-0 transition-transform duration-300 ease-out motion-reduce:transition-none"
                                                    :class="isCategorySubcatsExpanded(categoryChartGroupKey(group, gi)) ? '' : '-rotate-90'"
                                                />
                                            </button>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-3 tabular-nums">
                                            <span class="text-xs font-semibold text-muted-foreground transition-colors group-hover/root:text-foreground">{{ Math.round(categorySlicePct(group.total)) }}%</span>
                                            <span class="min-w-[1.25rem] text-right text-muted-foreground group-hover/root:text-foreground">{{ group.total }}</span>
                                        </div>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                        <div
                                            class="h-full transition-all duration-500 ease-out group-hover/root:opacity-85"
                                            :style="{ width: `${categorySlicePct(group.total)}%`, backgroundColor: group.hex }"
                                        />
                                    </div>
                                    <div class="absolute -inset-x-2 -inset-y-2 z-[-1] rounded-lg bg-muted/50 opacity-0 transition-opacity group-hover/root:opacity-100" />
                                </div>
                                <div
                                    v-if="group.children.length"
                                    class="grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none"
                                    :class="isCategorySubcatsExpanded(categoryChartGroupKey(group, gi)) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                                >
                                    <div class="min-h-0 overflow-hidden">
                                        <div
                                            class="mt-3 space-y-3 border-l-2 border-border/25 pl-3 sm:pl-4 ml-0.5 transition-opacity duration-300 ease-out motion-reduce:transition-none"
                                            :class="
                                                isCategorySubcatsExpanded(categoryChartGroupKey(group, gi))
                                                    ? 'opacity-100'
                                                    : 'pointer-events-none opacity-0'
                                            "
                                            :style="{ borderLeftColor: `${group.hex}40` }"
                                        >
                                            <button
                                                v-for="(ch, ci) in group.children"
                                                :key="`sub-${group.name}-${ch.name}`"
                                                type="button"
                                                class="group/sub block w-full cursor-pointer rounded-md py-0.5 text-left transition-colors hover:bg-muted/40"
                                                @click.stop="openCategoryModal(ch.name)"
                                            >
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="min-w-0 truncate text-xs text-muted-foreground group-hover/sub:text-foreground">{{ ch.name }}</span>
                                                    <span class="shrink-0 tabular-nums text-[11px] text-muted-foreground">
                                                        <span>{{ Math.round(categorySlicePct(ch.count)) }}%</span>
                                                        <span class="ml-2 font-medium text-foreground/90">{{ ch.count }}</span>
                                                    </span>
                                                </div>
                                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-500 ease-out group-hover/sub:opacity-90"
                                                        :style="{
                                                            width: `${categorySlicePct(ch.count)}%`,
                                                            backgroundColor: categoryChildBarColor(group.hex, ci),
                                                        }"
                                                    />
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="py-10 text-center text-sm text-muted-foreground">No incidents in this period.</p>
                        </div>
                    </CardContent>
                </Card>
                </div>

                <div class="flex min-w-0 flex-col gap-4 sm:gap-6">
                <!-- Top Recurring Incidents (volume leaderboard) -->
                <Card :class="dashboardPanelCard">
                    <CardHeader :class="dashboardPanelHeader">
                        <div :class="dashboardPanelHeaderRow">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div :class="cn(dashboardPanelHeaderIconWrap, 'text-amber-600 dark:text-amber-400')">
                                    <Crown class="h-4 w-4" stroke-width="2" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <CardTitle class="text-lg font-semibold tracking-tight">Top Recurring Incidents</CardTitle>
                                    <CardDescription class="leading-relaxed">
                                        Highest-volume tag themes in the selected period.
                                    </CardDescription>
                                </div>
                            </div>
                            <div
                                class="shrink-0 rounded-xl border border-border/60 bg-background/80 px-3 py-2 text-right shadow-md shadow-black/5 backdrop-blur-sm dark:shadow-black/20"
                                :title="periodLabel"
                            >
                                <span class="block text-[9px] font-bold uppercase tracking-[0.14em] text-muted-foreground/70">Reporting</span>
                                <span class="mt-0.5 block max-w-[7.5rem] truncate text-xs font-semibold text-foreground">{{ periodLabel }}</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="!p-0 p-4">
                        <div :class="cn(dashboardContentWell, '!p-0 overflow-hidden')">
                        <ul v-if="topRecurring.length" class="divide-y divide-border/40">
                            <li
                                v-for="row in topRecurring"
                                :key="row.tag"
                                role="button"
                                tabindex="0"
                                class="group flex cursor-pointer items-center gap-3 px-4 py-3.5 transition-all hover:bg-muted/35 hover:shadow-sm active:scale-[0.998] sm:gap-4 sm:px-5 sm:py-4"
                                @click="openTopRecurringThemeModal(row.tag)"
                                @keydown.enter.prevent="openTopRecurringThemeModal(row.tag)"
                                @keydown.space.prevent="openTopRecurringThemeModal(row.tag)"
                            >
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border/50 bg-muted/20 text-sm font-bold tabular-nums text-muted-foreground shadow-sm transition-colors group-hover:border-border group-hover:bg-muted/40 group-hover:text-foreground"
                                >
                                    {{ row.rank }}
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col justify-center gap-2">
                                    <p class="truncate text-sm font-semibold leading-tight text-foreground tracking-tight">
                                        {{ row.tag }}
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-secondary/80">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-primary/55 via-primary/75 to-primary transition-[width] duration-500 ease-out group-hover:from-primary/65 group-hover:via-primary/85 group-hover:to-primary"
                                                :style="{ width: `${(row.count / maxRecurringCount) * 100}%` }"
                                            ></div>
                                        </div>
                                        <span class="shrink-0 tabular-nums">
                                            <span class="text-sm font-bold text-foreground">{{ row.count }}</span>
                                            <span class="ml-1 text-[11px] font-medium text-muted-foreground">{{ row.count === 1 ? 'ticket' : 'tickets' }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="flex flex-col items-center justify-center gap-3 px-8 py-14 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-dashed border-border/60 bg-muted/20">
                                <ListOrdered class="h-6 w-6 text-muted-foreground/45" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-foreground">Nothing ranked for this window</p>
                                <p class="text-xs leading-relaxed text-muted-foreground max-w-[260px]">
                                    When incidents are classified, recurring themes show up here for
                                    <span class="font-medium text-foreground/80">{{ periodLabel }}</span>.
                                </p>
                            </div>
                        </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Comments -->
                <Card :class="dashboardPanelCard">
                    <CardHeader :class="cn(dashboardPanelHeader, 'shrink-0')">
                        <div :class="dashboardPanelHeaderRow">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div :class="dashboardPanelHeaderIconWrap">
                                    <MessageSquare class="h-4 w-4" stroke-width="2" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <CardTitle class="text-lg font-semibold leading-tight tracking-tight">Recent Comments</CardTitle>
                                    <CardDescription class="text-xs">Latest across all tickets</CardDescription>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span v-if="recentComments.length" class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary tabular-nums shadow-sm">
                                    {{ recentComments.length }}
                                </span>
                                <button
                                    type="button"
                                    @click="refreshComments"
                                    :disabled="isRefreshingComments"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border/50 bg-background/80 text-muted-foreground shadow-sm transition-all hover:bg-background hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                    title="Refresh"
                                >
                                    <RefreshCcw :class="['h-3.5 w-3.5', isRefreshingComments && 'animate-spin']" />
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex min-h-0 flex-1 flex-col !p-0 px-5 pb-5 pt-0">
                        <div :class="cn(dashboardContentWell, 'modal-body max-h-[380px] flex-1 overflow-y-auto')">
                        <!-- Empty state -->
                        <div v-if="!recentComments.length" class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted/50">
                                <MessageSquare class="h-5 w-5 text-muted-foreground/50" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">No comments yet</p>
                                <p class="text-xs text-muted-foreground mt-0.5">Comments on tickets will appear here</p>
                            </div>
                        </div>

                        <!-- Comment list -->
                        <div v-else class="flex flex-col gap-1">
                            <div
                                v-for="comment in recentComments"
                                :key="comment.id"
                                class="group relative flex cursor-pointer gap-3 rounded-xl px-3 py-3 transition-all duration-300 hover:bg-muted/35 hover:shadow-sm active:scale-[0.99]"
                                @click="openCommentTicketDetail(comment)"
                            >
                                <!-- Avatar -->
                                <div
                                    :class="['h-8 w-8 rounded-full text-xs font-bold flex items-center justify-center border select-none shrink-0 mt-0.5 transition-all group-hover:ring-2 group-hover:ring-primary/30 group-hover:ring-offset-1', getAvatarColor(comment.userName)]"
                                >
                                    {{ comment.userInitials }}
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0 pr-6">
                                    <!-- Ticket ID + title (top, emphasized) -->
                                    <div class="flex items-center gap-1.5 mb-1.5 min-w-0">
                                        <span class="inline-flex items-center shrink-0 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary border border-primary/20 leading-none">
                                            {{ comment.ticketTktId }}
                                        </span>
                                        <span class="text-xs font-semibold text-foreground truncate leading-none">{{ comment.ticketTitle }}</span>
                                    </div>

                                    <!-- Name + role badge + time -->
                                    <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                        <span class="text-[11px] font-medium text-muted-foreground leading-none">{{ comment.userName }}</span>
                                        <span :class="['inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold border leading-none', getRoleBadgeClass(comment.userRole)]">
                                            <component :is="getRoleIcon(comment.userRole)" class="h-2.5 w-2.5" />
                                            {{ getRoleLabel(comment.userRole) }}
                                        </span>
                                        <span class="text-[10px] text-muted-foreground/60 leading-none">· {{ comment.createdAt }}</span>
                                    </div>

                                    <!-- Body snippet + inline image previews -->
                                    <p
                                        v-if="comment.bodySnippet"
                                        class="text-xs text-foreground/70 leading-relaxed line-clamp-2"
                                    >
                                        {{ comment.bodySnippet }}
                                    </p>
                                    <div
                                        v-if="comment.snippetImageUrls?.length"
                                        class="mt-1.5 flex flex-wrap gap-1.5"
                                    >
                                        <img
                                            v-for="(imgUrl, idx) in comment.snippetImageUrls"
                                            :key="idx"
                                            :src="imgUrl"
                                            alt=""
                                            loading="lazy"
                                            class="h-11 w-11 rounded-md border border-border/50 object-cover shrink-0 bg-muted/30"
                                        />
                                    </div>
                                    <p
                                        v-if="!comment.bodySnippet && comment.snippetImageUrls?.length"
                                        class="text-xs text-muted-foreground/80 italic"
                                    >
                                        Image
                                    </p>
                                </div>

                                <!-- Hover chevron -->
                                <ChevronRight class="absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/40 opacity-0 transition-all group-hover:translate-x-0.5 group-hover:text-primary/60 group-hover:opacity-100" />
                            </div>
                        </div>
                        </div>
                    </CardContent>
                </Card>
                </div>
            </div>
        </div>

        <!-- ── View Details Modal ──────────────────────────────────────── -->
        <Dialog v-model:open="isDetailModalOpen">
            <DialogContent
                class="flex max-h-[92dvh] w-[calc(100vw-1.5rem)] flex-col overflow-hidden border border-border/40 p-0 shadow-2xl sm:max-w-5xl sm:rounded-2xl lg:max-w-6xl"
                v-if="selectedActivity"
            >
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                {{ selectedActivity.tktId }}
                            </Badge>
                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border', getStatusColor(selectedActivity.status ?? 'Open')]">
                                <component :is="getStatusIcon(selectedActivity.status ?? 'Open')" class="h-3 w-3" />
                                {{ selectedActivity.status ?? 'Open' }}
                            </Badge>
                            <span
                                :class="['inline-flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[10px] font-bold uppercase', !priorityHexByName[selectedActivity.priority] && 'bg-muted text-muted-foreground border-border']"
                                :style="chartToneStyle(priorityHexByName[selectedActivity.priority])"
                            >
                                <component :is="getPriorityIcon(selectedActivity.priority)" class="h-3 w-3" />
                                {{ selectedActivity.priority }}
                            </span>
                        </div>
                        <DialogTitle class="text-base font-bold tracking-tight leading-snug sm:text-lg">
                            {{ selectedActivity.title }}
                        </DialogTitle>
                        <DialogDescription class="text-muted-foreground/70 text-xs mt-0.5">
                            Submitted {{ selectedActivity.createdAtFormatted }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body (scrollable) -->
                <div class="modal-body overflow-y-auto flex-1 px-5 py-5 grid gap-4">
                    <!-- Meta grid -->
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <div class="flex flex-col gap-1 rounded-xl bg-muted/40 px-3 py-2.5 border border-border/40">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Category</span>
                            <span class="text-sm font-semibold text-foreground">{{ selectedActivity.category }}</span>
                        </div>
                        <div class="flex flex-col gap-1 rounded-xl bg-muted/40 px-3 py-2.5 border border-border/40">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Reporter</span>
                            <div class="flex items-center gap-1.5">
                                <div class="h-5 w-5 rounded-full bg-muted flex items-center justify-center text-[9px] font-bold border border-border/50 shrink-0">
                                    {{ getInitials(selectedActivity.reporter) }}
                                </div>
                                <span class="text-sm font-semibold text-foreground truncate">{{ selectedActivity.reporter }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1 rounded-xl bg-muted/40 px-3 py-2.5 border border-border/40 col-span-2 sm:col-span-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Handlers</span>
                            <div v-if="selectedActivity.handlers.length > 0" class="flex flex-wrap gap-1 mt-0.5">
                                <span
                                    v-for="h in selectedActivity.handlers"
                                    :key="h.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-muted px-1.5 py-0.5 text-[11px] font-semibold border border-border/50"
                                >
                                    <span class="h-3.5 w-3.5 rounded-full bg-muted-foreground/20 flex items-center justify-center text-[8px] font-bold shrink-0">{{ getInitials(h.name) }}</span>
                                    {{ h.name }}
                                </span>
                            </div>
                            <span v-else class="text-sm text-muted-foreground/50 italic">Unassigned</span>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="selectedActivity.tags && selectedActivity.tags.length > 0" class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Tags</span>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                v-for="tag in selectedActivity.tags"
                                :key="tag"
                                variant="secondary"
                                class="bg-primary/10 text-primary hover:bg-primary/20 border-primary/20 text-[10px] font-semibold px-2 py-0.5"
                            >
                                {{ tag }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Description</span>
                        <div
                            v-if="selectedActivity.description"
                            class="rounded-xl border border-border/40 bg-muted/20 px-4 py-3 text-sm text-foreground leading-relaxed prose prose-sm max-w-none dark:prose-invert"
                            v-html="selectedActivity.description"
                        />
                        <div v-else class="rounded-xl border border-dashed border-border/40 bg-muted/10 px-4 py-5 text-center text-sm text-muted-foreground/60 italic">
                            No description provided.
                        </div>
                    </div>

                    <!-- Attachment -->
                    <div v-if="selectedActivity.attachmentUrl" class="flex flex-col gap-2">
                        <div class="flex items-center gap-1.5">
                            <ImageIcon class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Attachment</span>
                        </div>
                        <a :href="selectedActivity.attachmentUrl" target="_blank" class="block rounded-xl overflow-hidden border border-border/50 bg-muted/20 hover:opacity-90 transition-opacity">
                            <img :src="selectedActivity.attachmentUrl" alt="Ticket attachment" class="w-full max-h-52 object-contain" />
                        </a>
                    </div>

                    <!-- Timeline -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground/70">
                            <Clock class="h-3.5 w-3.5 shrink-0" />
                            <span>Opened {{ selectedActivity.time }}</span>
                            <span class="text-muted-foreground/40">·</span>
                            <span>{{ selectedActivity.createdAtFormatted }}</span>
                        </div>
                    </div>

                    <!-- Comments -->
                    <TicketComments :ticket-id="selectedActivity.numericId" :reporter-id="selectedActivity.reporterId" />
                </div>

                <DialogFooter class="px-5 py-4 bg-muted/20 border-t border-border/50 flex items-center gap-2">
                    <Button variant="outline" @click="isDetailModalOpen = false" class="ml-auto text-xs font-bold">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- ──────────────────────────────────────────────────────────────── -->

        <!-- ── Open incidents list (peek) ──────────────────────────────── -->
        <Dialog v-model:open="openIncidentsListModalOpen">
            <DialogContent class="flex max-h-[85dvh] flex-col overflow-hidden border border-border/40 p-0 shadow-2xl sm:max-w-[640px] sm:rounded-2xl">
                <div class="shrink-0 border-b border-primary/10 bg-primary/5 px-5 pb-4 pt-5">
                    <DialogHeader>
                        <div class="mb-1 flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 text-rose-500" aria-hidden="true" />
                            <DialogTitle class="text-base font-bold">Open incidents</DialogTitle>
                        </div>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Status Open · created in {{ periodLabel }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div class="modal-body flex-1 overflow-y-auto">
                    <div
                        v-if="openIncidentsListLoading"
                        class="flex min-h-[220px] flex-col items-center justify-center gap-6 px-5 py-10"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <div class="flex flex-col items-center gap-3 text-center">
                            <Loader2 class="h-9 w-9 animate-spin text-primary" aria-hidden="true" />
                            <p class="text-sm font-medium text-foreground">Loading open incidents…</p>
                            <p class="max-w-[240px] text-xs text-muted-foreground">Fetching tickets for {{ periodLabel }}</p>
                        </div>
                        <div class="flex w-full max-w-md flex-col gap-2.5">
                            <div v-for="i in 5" :key="i" class="flex gap-3 rounded-lg border border-border/40 bg-muted/20 p-3">
                                <div class="h-5 w-14 shrink-0 animate-pulse rounded bg-muted/60" />
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="h-3.5 max-w-sm w-[85%] animate-pulse rounded bg-muted/60" />
                                    <div class="h-2.5 w-1/2 animate-pulse rounded bg-muted/40" />
                                </div>
                                <div class="mt-0.5 h-3 w-12 shrink-0 animate-pulse self-start rounded bg-muted/40" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!openIncidentsListTickets.length" class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                        <CheckCircle2 class="h-8 w-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">No open incidents in {{ periodLabel }}.</p>
                    </div>

                    <div v-else class="divide-y divide-border/40">
                        <div
                            v-for="t in openIncidentsListTickets"
                            :key="t.numericId"
                            class="group flex cursor-pointer items-start gap-3 px-5 py-3.5 transition-all hover:bg-muted/40 active:scale-[0.995]"
                            @click="selectOpenIncidentFromPeek(t)"
                        >
                            <span
                                class="mt-0.5 inline-flex shrink-0 items-center rounded-md border border-primary/20 bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold leading-none text-primary"
                            >
                                {{ t.tktId }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium leading-snug text-foreground transition-colors group-hover:text-primary">
                                    {{ t.title }}
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-bold',
                                            !priorityHexByName[t.priority] && 'border-border bg-muted text-muted-foreground',
                                        ]"
                                        :style="chartToneStyle(priorityHexByName[t.priority])"
                                    >
                                        <component :is="getPriorityIcon(t.priority)" class="h-2.5 w-2.5" />
                                        {{ t.priority }}
                                    </span>
                                    <span class="text-[11px] text-muted-foreground">{{ t.category }}</span>
                                    <span class="text-[11px] text-muted-foreground">·</span>
                                    <span class="truncate text-[11px] text-muted-foreground">{{ t.reporter }}</span>
                                </div>
                            </div>
                            <div class="mt-0.5 flex shrink-0 items-center gap-1">
                                <span class="text-[11px] text-muted-foreground">{{ t.time }}</span>
                                <ChevronRight
                                    class="h-3.5 w-3.5 text-muted-foreground/30 transition-all group-hover:translate-x-0.5 group-hover:text-primary/60 group-hover:opacity-100 opacity-0"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 border-t border-border/50 bg-muted/20 px-5 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Link
                            :href="route('tickets')"
                            class="mr-auto text-xs font-semibold text-primary underline-offset-4 hover:underline"
                        >
                            Open Incidents page
                        </Link>
                        <Button variant="outline" class="text-xs font-bold" @click="openIncidentsListModalOpen = false">
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- ── Assign Handler Modal ─────────────────────────────────────── -->
        <Dialog v-model:open="isAssignModalOpen">
            <DialogContent class="sm:max-w-[460px] p-0 overflow-hidden border border-border/40 shadow-2xl sm:rounded-2xl flex flex-col max-h-[90dvh]" v-if="assigningTicket">
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                {{ assigningTicket.tktId }}
                            </Badge>
                            <Badge
                                variant="outline"
                                :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border']"
                                :style="dashboardStatusHexStyle(assigningTicket.status ?? 'Open')"
                            >
                                <component :is="dashboardStatusIcon(assigningTicket.status ?? 'Open')" class="h-3 w-3" />
                                {{ assigningTicket.status ?? 'Open' }}
                            </Badge>
                            <span class="text-muted-foreground/40 text-xs">→</span>
                            <Badge
                                variant="outline"
                                :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border']"
                                :style="dashboardStatusHexStyle(assignStatusOverride)"
                            >
                                <component :is="dashboardStatusIcon(assignStatusOverride)" class="h-3 w-3" />
                                {{ assignStatusOverride }}
                            </Badge>
                        </div>
                        <DialogTitle class="text-base font-bold tracking-tight leading-snug flex items-center gap-2">
                            <UserPlus class="h-4 w-4 text-primary shrink-0" />
                            Assign Handler & Update Status
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground/80 truncate mt-0.5">
                            {{ assigningTicket.title }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="modal-body flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-5">

                    <!-- Status picker -->
                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Set New Status</p>
                            <span class="inline-flex items-center rounded-md bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-500">Required</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="s in assignModalTargetStatuses"
                                :key="s.name"
                                type="button"
                                @click="assignStatusOverride = s.name"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold border-2 transition-all',
                                    assignStatusOverride === s.name
                                        ? 'border-current shadow-sm scale-[1.03]'
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                ]"
                                :style="assignStatusOverride === s.name ? dashboardStatusHexStyle(s.name) : {}"
                            >
                                <component :is="dashboardStatusIcon(s.name)" class="h-3 w-3" />
                                {{ s.name }}
                            </button>
                        </div>
                        <div class="h-px bg-border/50" />
                    </div>

                    <!-- Currently assigned tags -->
                    <div class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            {{ assignForm.handler_ids.length > 0 ? 'Currently Assigned' : 'No Handlers Yet' }}
                        </p>
                        <div v-if="assignForm.handler_ids.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="id in assignForm.handler_ids"
                                :key="id"
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 border border-primary/20 pl-2 pr-1 py-1 text-[11px] font-semibold text-primary"
                            >
                                <span class="h-4 w-4 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[9px] font-bold shrink-0">
                                    {{ getInitials(users.find(u => u.id === id)?.name ?? '') }}
                                </span>
                                {{ users.find(u => u.id === id)?.name }}
                                <button
                                    type="button"
                                    @click="assignForm.handler_ids = assignForm.handler_ids.filter(i => i !== id)"
                                    class="ml-0.5 h-4 w-4 rounded-full hover:bg-primary/20 flex items-center justify-center transition-colors"
                                >
                                    <X class="h-2.5 w-2.5" />
                                </button>
                            </span>
                        </div>
                        <div v-else class="flex items-center gap-2 rounded-xl border border-dashed border-border/50 bg-muted/10 px-4 py-3">
                            <UserPlus class="h-4 w-4 text-muted-foreground/40 shrink-0" />
                            <p class="text-xs text-muted-foreground/60 italic">Select handlers from the list below.</p>
                        </div>
                    </div>

                    <!-- Search + user list -->
                    <div class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Select Handlers</p>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                            <input
                                v-model="assignHandlerSearch"
                                type="text"
                                placeholder="Search users…"
                                class="w-full rounded-lg border border-input bg-background pl-9 pr-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="handler-list overflow-y-auto rounded-xl border border-border/50 divide-y divide-border/40 bg-muted/10" style="max-height: 200px;">
                            <button
                                v-for="user in filteredAssignUsers"
                                :key="user.id"
                                type="button"
                                @click="assignForm.handler_ids = assignForm.handler_ids.includes(user.id)
                                    ? assignForm.handler_ids.filter(i => i !== user.id)
                                    : [...assignForm.handler_ids, user.id]"
                                :class="[
                                    'w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                    assignForm.handler_ids.includes(user.id) ? 'bg-primary/10 text-primary' : 'hover:bg-muted/60 text-foreground'
                                ]"
                            >
                                <div :class="[
                                    'h-7 w-7 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 border transition-colors',
                                    assignForm.handler_ids.includes(user.id) ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted border-border/50'
                                ]">
                                    {{ getInitials(user.name) }}
                                </div>
                                <span class="text-sm font-medium truncate flex-1">{{ user.name }}</span>
                                <div :class="[
                                    'h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors',
                                    assignForm.handler_ids.includes(user.id) ? 'bg-primary border-primary' : 'border-border/50'
                                ]">
                                    <CheckCircle2 v-if="assignForm.handler_ids.includes(user.id)" class="h-3 w-3 text-primary-foreground" />
                                </div>
                            </button>
                            <div v-if="filteredAssignUsers.length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground/60 italic">
                                No users match your search.
                            </div>
                        </div>
                    </div>

                    <span v-if="assignForm.errors.handler_ids" class="text-xs text-destructive font-medium">{{ assignForm.errors.handler_ids }}</span>
                </div>

                <!-- Solution (only when Resolved) -->
                <div v-if="assignStatusOverride === 'Resolved'" class="px-5 grid gap-2">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Solution <span class="ml-1 text-destructive">*</span>
                    </Label>
                    <RichTextEditor v-model="assignForm.solution" placeholder="Describe how the issue was resolved…" />
                    <span v-if="assignForm.errors.solution" class="text-xs text-destructive font-medium">{{ assignForm.errors.solution }}</span>
                </div>

                <!-- Footer -->
                <DialogFooter class="px-5 py-4 bg-muted/20 border-t border-border/50">
                    <div class="flex w-full items-center justify-between gap-2">
                        <p class="text-xs text-muted-foreground">
                            <span class="font-semibold text-foreground">{{ assignForm.handler_ids.length }}</span>
                            handler{{ assignForm.handler_ids.length !== 1 ? 's' : '' }} selected
                        </p>
                        <div class="flex items-center gap-2">
                            <Button type="button" variant="outline" @click="isAssignModalOpen = false" class="text-xs font-bold">
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                :disabled="assignForm.processing || (assignTargetRequiresHandlers && assignForm.handler_ids.length === 0) || (assignStatusOverride === 'Resolved' && isEmptyHtml(assignForm.solution))"
                                @click="submitAssign"
                                class="text-xs font-bold gap-1.5 shadow-sm shadow-primary/20"
                            >
                                <span v-if="!assignForm.processing" class="flex items-center gap-1.5">
                                    <UserPlus class="h-3.5 w-3.5" />
                                    Assign & Update
                                </span>
                                <span v-else class="flex items-center gap-1.5">
                                    Saving… <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                </span>
                            </Button>
                        </div>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- ──────────────────────────────────────────────────────────────── -->
        <!-- ── Severity Drill-Down Modal ─────────────────────────────────── -->
        <Dialog v-model:open="severityModalOpen">
            <DialogContent class="sm:max-w-[640px] p-0 overflow-hidden border border-border/40 shadow-2xl sm:rounded-2xl max-h-[85dvh] flex flex-col">
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10 shrink-0">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-1">
                            <component :is="getPriorityIcon(severityModalPriority)" class="h-4 w-4 text-muted-foreground" />
                            <DialogTitle class="text-base font-bold">{{ severityModalPriority }} Priority Tickets</DialogTitle>
                        </div>
                        <DialogDescription class="text-xs text-muted-foreground">
                            {{ severityModalPriority }} priority · tickets created in {{ periodLabel }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto modal-body">
                    <!-- Loading -->
                    <div
                        v-if="severityModalLoading"
                        class="flex min-h-[220px] flex-col items-center justify-center gap-6 px-5 py-10"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <div class="flex flex-col items-center gap-3 text-center">
                            <Loader2 class="h-9 w-9 animate-spin text-primary" aria-hidden="true" />
                            <p class="text-sm font-medium text-foreground">Loading tickets…</p>
                            <p class="text-xs text-muted-foreground max-w-[240px]">Fetching {{ severityModalPriority.toLowerCase() }} priority for {{ periodLabel }}</p>
                        </div>
                        <div class="w-full max-w-md flex flex-col gap-2.5">
                            <div v-for="i in 5" :key="i" class="flex gap-3 rounded-lg border border-border/40 bg-muted/20 p-3">
                                <div class="h-5 w-14 shrink-0 rounded bg-muted/60 animate-pulse" />
                                <div class="flex-1 space-y-2 min-w-0">
                                    <div class="h-3.5 w-[85%] max-w-sm rounded bg-muted/60 animate-pulse" />
                                    <div class="h-2.5 w-1/2 rounded bg-muted/40 animate-pulse" />
                                </div>
                                <div class="h-3 w-12 shrink-0 rounded bg-muted/40 animate-pulse self-start mt-0.5" />
                            </div>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="!severityModalTickets.length" class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                        <AlertCircle class="h-8 w-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">No {{ severityModalPriority.toLowerCase() }} priority tickets in {{ periodLabel }}.</p>
                    </div>

                    <!-- Ticket rows -->
                    <div v-else class="divide-y divide-border/40">
                        <div
                            v-for="t in severityModalTickets"
                            :key="t.numericId"
                            class="group flex items-start gap-3 px-5 py-3.5 hover:bg-muted/40 active:scale-[0.995] transition-all cursor-pointer"
                            @click="openDetailModal({ id: t.id, numericId: t.numericId, tktId: t.tktId, title: t.title, description: t.description, status: t.status, priority: t.priority, category: t.category, tags: t.tags ?? [], reporter: t.reporter, reporterId: t.reporterId ?? undefined, handlerIds: t.handlerIds, handlers: t.handlers, attachmentUrl: t.attachmentUrl, createdAtFormatted: t.createdAtFormatted, time: t.time })"
                        >
                            <!-- TKT ID badge -->
                            <span class="inline-flex items-center shrink-0 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary border border-primary/20 leading-none mt-0.5">
                                {{ t.tktId }}
                            </span>
                            <!-- Main info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-foreground leading-snug truncate group-hover:text-primary transition-colors">{{ t.title }}</p>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 border', getStatusColor(t.status)]">
                                        <component :is="getStatusIcon(t.status)" class="h-2.5 w-2.5" />
                                        {{ t.status }}
                                    </Badge>
                                    <span class="text-[11px] text-muted-foreground">{{ t.category }}</span>
                                    <span class="text-[11px] text-muted-foreground">·</span>
                                    <span class="text-[11px] text-muted-foreground truncate">{{ t.reporter }}</span>
                                </div>
                            </div>
                            <!-- Date + chevron -->
                            <div class="flex items-center gap-1 shrink-0 mt-0.5">
                                <span class="text-[11px] text-muted-foreground">{{ t.time }}</span>
                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/30 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:text-primary/60 transition-all" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 bg-muted/20 border-t border-border/50 shrink-0">
                    <Button variant="outline" class="ml-auto flex text-xs font-bold" @click="severityModalOpen = false">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
        <!-- ──────────────────────────────────────────────────────────────── -->
        <!-- ── Category Drill-Down Modal ────────────────────────────────── -->
        <Dialog v-model:open="categoryModalOpen">
            <DialogContent class="sm:max-w-[640px] p-0 overflow-hidden border border-border/40 shadow-2xl sm:rounded-2xl max-h-[85dvh] flex flex-col">
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10 shrink-0">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-1">
                            <BarChart2 class="h-4 w-4 text-muted-foreground" />
                            <DialogTitle class="text-base font-bold">{{ categoryModalName }} Tickets</DialogTitle>
                        </div>
                        <DialogDescription class="text-xs text-muted-foreground">
                            <template v-if="categoryModalIsRootScope">All types under this family · tickets created in {{ periodLabel }}</template>
                            <template v-else>{{ categoryModalName }} category · tickets created in {{ periodLabel }}</template>
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto modal-body">
                    <!-- Loading -->
                    <div
                        v-if="categoryModalLoading"
                        class="flex min-h-[220px] flex-col items-center justify-center gap-6 px-5 py-10"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <div class="flex flex-col items-center gap-3 text-center">
                            <Loader2 class="h-9 w-9 animate-spin text-primary" aria-hidden="true" />
                            <p class="text-sm font-medium text-foreground">Loading tickets…</p>
                            <p class="text-xs text-muted-foreground max-w-[240px]">
                                <template v-if="categoryModalIsRootScope">Loading tickets for {{ categoryModalName }} · {{ periodLabel }}</template>
                                <template v-else>Fetching {{ categoryModalName }} for {{ periodLabel }}</template>
                            </p>
                        </div>
                        <div class="w-full max-w-md flex flex-col gap-2.5">
                            <div v-for="i in 5" :key="i" class="flex gap-3 rounded-lg border border-border/40 bg-muted/20 p-3">
                                <div class="h-5 w-14 shrink-0 rounded bg-muted/60 animate-pulse" />
                                <div class="flex-1 space-y-2 min-w-0">
                                    <div class="h-3.5 w-[85%] max-w-sm rounded bg-muted/60 animate-pulse" />
                                    <div class="h-2.5 w-1/2 rounded bg-muted/40 animate-pulse" />
                                </div>
                                <div class="h-3 w-12 shrink-0 rounded bg-muted/40 animate-pulse self-start mt-0.5" />
                            </div>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="!categoryModalTickets.length" class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                        <AlertCircle class="h-8 w-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">No tickets in {{ categoryModalName }} for {{ periodLabel }}.</p>
                    </div>

                    <!-- Ticket rows -->
                    <div v-else class="divide-y divide-border/40">
                        <div
                            v-for="t in categoryModalTickets"
                            :key="t.numericId"
                            class="group flex items-start gap-3 px-5 py-3.5 hover:bg-muted/40 active:scale-[0.995] transition-all cursor-pointer"
                            @click="openDetailModal({ id: t.id, numericId: t.numericId, tktId: t.tktId, title: t.title, description: t.description, status: t.status, priority: t.priority, category: t.category, tags: t.tags ?? [], reporter: t.reporter, reporterId: t.reporterId ?? undefined, handlerIds: t.handlerIds, handlers: t.handlers, attachmentUrl: t.attachmentUrl, createdAtFormatted: t.createdAtFormatted, time: t.time })"
                        >
                            <!-- TKT ID badge -->
                            <span class="inline-flex items-center shrink-0 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary border border-primary/20 leading-none mt-0.5">
                                {{ t.tktId }}
                            </span>
                            <!-- Main info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-foreground leading-snug truncate group-hover:text-primary transition-colors">{{ t.title }}</p>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 border', getStatusColor(t.status)]">
                                        <component :is="getStatusIcon(t.status)" class="h-2.5 w-2.5" />
                                        {{ t.status }}
                                    </Badge>
                                    <span class="text-[11px] text-muted-foreground">{{ t.priority }}</span>
                                    <span class="text-[11px] text-muted-foreground">·</span>
                                    <span class="text-[11px] text-muted-foreground truncate">{{ t.reporter }}</span>
                                </div>
                            </div>
                            <!-- Time + chevron -->
                            <div class="flex items-center gap-1 shrink-0 mt-0.5">
                                <span class="text-[11px] text-muted-foreground">{{ t.time }}</span>
                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/30 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:text-primary/60 transition-all" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 bg-muted/20 border-t border-border/50 shrink-0">
                    <Button variant="outline" class="ml-auto flex text-xs font-bold" @click="categoryModalOpen = false">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
        <!-- ──────────────────────────────────────────────────────────────── -->
        <!-- ── Top recurring theme (tag) drill-down ─────────────────────── -->
        <Dialog v-model:open="topRecurringThemeModalOpen">
            <DialogContent class="sm:max-w-[640px] p-0 overflow-hidden border border-border/40 shadow-2xl sm:rounded-2xl max-h-[85dvh] flex flex-col">
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10 shrink-0">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-1">
                            <ListOrdered class="h-4 w-4 text-muted-foreground" />
                            <DialogTitle class="text-base font-bold">{{ topRecurringThemeModalLabel }}</DialogTitle>
                        </div>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Tickets with this tag created in {{ periodLabel }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div class="flex-1 overflow-y-auto modal-body">
                    <div
                        v-if="topRecurringThemeModalLoading"
                        class="flex min-h-[220px] flex-col items-center justify-center gap-6 px-5 py-10"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <div class="flex flex-col items-center gap-3 text-center">
                            <Loader2 class="h-9 w-9 animate-spin text-primary" aria-hidden="true" />
                            <p class="text-sm font-medium text-foreground">Loading tickets…</p>
                            <p class="text-xs text-muted-foreground max-w-[240px]">Fetching tickets for this theme</p>
                        </div>
                        <div class="w-full max-w-md flex flex-col gap-2.5">
                            <div v-for="i in 5" :key="i" class="flex gap-3 rounded-lg border border-border/40 bg-muted/20 p-3">
                                <div class="h-5 w-14 shrink-0 rounded bg-muted/60 animate-pulse" />
                                <div class="flex-1 space-y-2 min-w-0">
                                    <div class="h-3.5 w-[85%] max-w-sm rounded bg-muted/60 animate-pulse" />
                                    <div class="h-2.5 w-1/2 rounded bg-muted/40 animate-pulse" />
                                </div>
                                <div class="h-3 w-12 shrink-0 rounded bg-muted/40 animate-pulse self-start mt-0.5" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!topRecurringThemeModalTickets.length" class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                        <AlertCircle class="h-8 w-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">No tickets found for this theme.</p>
                    </div>

                    <div v-else class="divide-y divide-border/40">
                        <div
                            v-for="t in topRecurringThemeModalTickets"
                            :key="t.numericId"
                            class="group flex items-start gap-3 px-5 py-3.5 hover:bg-muted/40 active:scale-[0.995] transition-all cursor-pointer"
                            @click="openDetailModal({ id: t.id, numericId: t.numericId, tktId: t.tktId, title: t.title, description: t.description, status: t.status, priority: t.priority, category: t.category, tags: t.tags ?? [], reporter: t.reporter, reporterId: t.reporterId ?? undefined, handlerIds: t.handlerIds, handlers: t.handlers, attachmentUrl: t.attachmentUrl, createdAtFormatted: t.createdAtFormatted, time: t.time })"
                        >
                            <span class="inline-flex items-center shrink-0 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary border border-primary/20 leading-none mt-0.5">
                                {{ t.tktId }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-foreground leading-snug truncate group-hover:text-primary transition-colors">{{ t.title }}</p>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 border', getStatusColor(t.status)]">
                                        <component :is="getStatusIcon(t.status)" class="h-2.5 w-2.5" />
                                        {{ t.status }}
                                    </Badge>
                                    <span class="text-[11px] text-muted-foreground">{{ t.priority }}</span>
                                    <span class="text-[11px] text-muted-foreground">·</span>
                                    <span class="text-[11px] text-muted-foreground">{{ t.category }}</span>
                                    <span class="text-[11px] text-muted-foreground">·</span>
                                    <span class="text-[11px] text-muted-foreground truncate">{{ t.reporter }}</span>
                                </div>
                                <div v-if="t.tags && t.tags.length > 0" class="flex flex-wrap gap-1 mt-2">
                                    <Badge
                                        v-for="tag in t.tags"
                                        :key="tag"
                                        variant="secondary"
                                        class="bg-primary/10 text-primary border-primary/20 text-[10px] font-semibold px-2 py-0.5"
                                    >
                                        {{ tag }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0 mt-0.5">
                                <span class="text-[11px] text-muted-foreground">{{ t.time }}</span>
                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/30 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:text-primary/60 transition-all" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 bg-muted/20 border-t border-border/50 shrink-0">
                    <Button variant="outline" class="ml-auto flex text-xs font-bold" @click="topRecurringThemeModalOpen = false">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
        <!-- ──────────────────────────────────────────────────────────────── -->
    </AppLayout>
</template>

<style scoped>
:deep(.unovis-axis-grid-line) {
    stroke: hsl(var(--border));
    stroke-opacity: 0.12;
}

:deep(.unovis-axis-domain-line) {
    stroke: transparent;
}

:deep(.unovis-axis-tick-text) {
    fill: hsl(var(--muted-foreground));
    font-size: 11px;
}

/* Smaller x-axis labels for 30-day range */
:deep(.range-30 .unovis-axis-tick-text) {
    font-size: 9px;
}

/* Scatter dots: white ring + smooth hover scale */
:deep(.unovis-scatter-point) {
    stroke: hsl(var(--background));
    stroke-width: 2px;
    transition: r 0.15s ease, opacity 0.15s ease;
}
:deep(.unovis-scatter-point:hover) {
    r: 6px;
    filter: brightness(1.15);
}
</style>
