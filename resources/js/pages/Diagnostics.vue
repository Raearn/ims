<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    Bug,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    Database,
    DatabaseBackup,
    Download,
    FileText,
    HardDrive,
    Info,
    RefreshCcw,
    Server,
    Settings,
    Terminal,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface LogInfo {
    path: string;
    content: string;
    size: string;
}

interface Props {
    phpVersion: string;
    laravelVersion: string;
    environment: string;
    debugMode: boolean;
    dbConnection: string;
    dbName: string;
    dbSizeMb: number;
    backupsTotalSizeMb: number;
    cacheDriver: string;
    queueDriver: string;
    serverInfo: string;
    timezone: string;
    serverTime: string;
    storageWritable: boolean;
    dbStats: {
        users: { total: number; admins: number; supervisors: number; technicals: number };
        tickets: { total: number; open: number; in_progress: number; on_hold: number; resolved: number; closed: number };
        comments: { total: number; reactions: number; votes: number };
        auditLogCount: number;
    };
    logs: {
        laravel: LogInfo;
        php: LogInfo;
    };
    backups: Array<{
        name: string;
        size: string;
        date: string;
    }>;
}

const props = defineProps<Props>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Diagnostics', href: route('diagnostics') },
];

// ── Refresh ────────────────────────────────────────────────────────────────
const isRefreshing = ref(false);
const refresh = (): void => {
    if (isRefreshing.value) return;
    isRefreshing.value = true;
    router.reload({ onFinish: () => { isRefreshing.value = false; } });
};

// ── Logs ───────────────────────────────────────────────────────────────────
const activeLogTab = ref<'laravel' | 'php'>('laravel');

const copied = ref(false);
const copyLogs = async (): Promise<void> => {
    const content = activeLogTab.value === 'laravel' ? props.logs.laravel.content : props.logs.php.content;
    try {
        await navigator.clipboard.writeText(content);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch (err) {
        console.error('Failed to copy logs', err);
    }
};

// ── Backup create ──────────────────────────────────────────────────────────
const isCreatingBackup = ref(false);
const createBackup = (): void => {
    if (isCreatingBackup.value) return;
    isCreatingBackup.value = true;
    router.post(route('diagnostics.backup'), {}, {
        preserveScroll: true,
        onFinish: () => { isCreatingBackup.value = false; },
    });
};

// ── Backup delete ──────────────────────────────────────────────────────────
const confirmingDelete = ref<string | null>(null);
const isDeletingBackup = ref<string | null>(null);

const confirmDelete = (filename: string): void => { confirmingDelete.value = filename; };

const deleteBackup = (): void => {
    if (!confirmingDelete.value || isDeletingBackup.value) return;
    const filename = confirmingDelete.value;
    isDeletingBackup.value = filename;
    confirmingDelete.value = null;
    router.delete(route('diagnostics.backup.delete', { filename }), {
        preserveScroll: true,
        onFinish: () => { isDeletingBackup.value = null; },
    });
};

// ── Ticket bar widths ──────────────────────────────────────────────────────
const ticketBarWidth = (count: number): string => {
    const total = props.dbStats.tickets.total;
    if (!total) return '0%';
    return `${Math.round((count / total) * 100)}%`;
};
</script>

<template>
    <Head title="System Diagnostics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 pb-20 md:p-6">

            <!-- Flash banners -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-1 opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="-translate-y-1 opacity-0"
            >
                <div
                    v-if="flashSuccess"
                    class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200"
                    role="status"
                >
                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-500" />
                    {{ flashSuccess }}
                </div>
            </Transition>
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-1 opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="-translate-y-1 opacity-0"
            >
                <div
                    v-if="flashError"
                    class="flex items-center gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-800 dark:text-rose-200"
                    role="alert"
                >
                    <AlertCircle class="h-4 w-4 shrink-0 text-rose-500" />
                    {{ flashError }}
                </div>
            </Transition>

            <!-- Page header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground">
                        <Activity class="h-6 w-6 text-primary" />
                        System Diagnostics
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">Real-time health checks, environment details, and database statistics.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('diagnostics.phpinfo')"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 px-3 py-1.5 text-xs font-semibold text-foreground shadow-sm transition-colors hover:bg-muted"
                    >
                        <Info class="h-3.5 w-3.5" /> PHP Info
                    </Link>
                    <button
                        type="button"
                        :disabled="isRefreshing"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 px-3 py-1.5 text-xs font-semibold text-foreground shadow-sm transition-colors hover:bg-muted disabled:opacity-40"
                        @click="refresh"
                    >
                        <RefreshCcw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                        {{ isRefreshing ? 'Refreshing…' : 'Refresh' }}
                    </button>
                </div>
            </div>

            <!-- Health Checks -->
            <section class="flex flex-col gap-3">
                <h2 class="text-[11px] font-bold uppercase tracking-widest text-muted-foreground">Health Checks</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="group flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 shadow-sm transition-all hover:border-emerald-500/40 hover:bg-emerald-500/10">
                        <div class="flex flex-col gap-0.5">
                            <Database class="mb-1 h-4 w-4 text-emerald-500" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Database</span>
                            <span class="text-sm font-bold text-emerald-500">Connected</span>
                        </div>
                        <CheckCircle2 class="h-5 w-5 text-emerald-500 opacity-70 transition-transform group-hover:scale-110" />
                    </div>

                    <div
                        :class="[
                            'group flex items-center justify-between rounded-xl border px-4 py-3 shadow-sm transition-all',
                            storageWritable
                                ? 'border-emerald-500/20 bg-emerald-500/5 hover:border-emerald-500/40 hover:bg-emerald-500/10'
                                : 'border-rose-500/20 bg-rose-500/5 hover:border-rose-500/40 hover:bg-rose-500/10',
                        ]"
                    >
                        <div class="flex flex-col gap-0.5">
                            <HardDrive :class="['mb-1 h-4 w-4', storageWritable ? 'text-emerald-500' : 'text-rose-500']" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Storage</span>
                            <span :class="['text-sm font-bold', storageWritable ? 'text-emerald-500' : 'text-rose-500']">
                                {{ storageWritable ? 'Writable' : 'Not Writable' }}
                            </span>
                        </div>
                        <CheckCircle2 v-if="storageWritable" class="h-5 w-5 text-emerald-500 opacity-70 transition-transform group-hover:scale-110" />
                        <AlertCircle v-else class="h-5 w-5 text-rose-500 opacity-70 transition-transform group-hover:scale-110" />
                    </div>

                    <div class="group flex items-center justify-between rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 shadow-sm transition-all hover:border-amber-500/40 hover:bg-amber-500/10">
                        <div class="flex flex-col gap-0.5">
                            <Server class="mb-1 h-4 w-4 text-amber-500" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Environment</span>
                            <span class="text-sm font-bold capitalize text-amber-500">{{ environment }}</span>
                        </div>
                        <AlertCircle class="h-5 w-5 text-amber-500 opacity-70 transition-transform group-hover:scale-110" />
                    </div>

                    <div
                        :class="[
                            'group flex items-center justify-between rounded-xl border px-4 py-3 shadow-sm transition-all',
                            debugMode
                                ? 'border-rose-500/20 bg-rose-500/5 hover:border-rose-500/40 hover:bg-rose-500/10'
                                : 'border-emerald-500/20 bg-emerald-500/5 hover:border-emerald-500/40 hover:bg-emerald-500/10',
                        ]"
                    >
                        <div class="flex flex-col gap-0.5">
                            <Bug :class="['mb-1 h-4 w-4', debugMode ? 'text-rose-500' : 'text-emerald-500']" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Debug Mode</span>
                            <span :class="['text-sm font-bold', debugMode ? 'text-rose-500' : 'text-emerald-500']">
                                {{ debugMode ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <AlertCircle v-if="debugMode" class="h-5 w-5 text-rose-500 opacity-70 transition-transform group-hover:scale-110" />
                        <CheckCircle2 v-else class="h-5 w-5 text-emerald-500 opacity-70 transition-transform group-hover:scale-110" />
                    </div>
                </div>
            </section>

            <!-- Database Statistics -->
            <section class="flex flex-col gap-3">
                <h2 class="text-[11px] font-bold uppercase tracking-widest text-muted-foreground">Database Statistics</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

                    <!-- Users -->
                    <Card class="border border-border/60 bg-card shadow-sm transition-shadow hover:shadow-md">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/10">
                                    <Users class="h-3.5 w-3.5 text-blue-500" />
                                </div>
                                <span class="font-semibold">Users</span>
                            </div>
                            <div class="mb-4 text-4xl font-bold tabular-nums">{{ dbStats.users.total }}</div>
                            <div class="space-y-2 text-sm text-muted-foreground">
                                <div class="flex items-center justify-between">
                                    <span>Admins</span>
                                    <span class="rounded border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-xs font-semibold text-rose-500">{{ dbStats.users.admins }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Supervisors</span>
                                    <span class="rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-xs font-semibold text-amber-500">{{ dbStats.users.supervisors }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Technicals</span>
                                    <span class="rounded border border-blue-500/20 bg-blue-500/10 px-1.5 py-0.5 text-xs font-semibold text-blue-500">{{ dbStats.users.technicals }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tickets with progress bars -->
                    <Card class="border border-border/60 bg-card shadow-sm transition-shadow hover:shadow-md">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-orange-500/10">
                                    <AlertTriangle class="h-3.5 w-3.5 text-orange-500" />
                                </div>
                                <span class="font-semibold">Tickets</span>
                            </div>
                            <div class="mb-4 text-4xl font-bold tabular-nums">{{ dbStats.tickets.total }}</div>
                            <div class="space-y-2.5">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Open</span>
                                        <span class="font-semibold tabular-nums text-rose-500">{{ dbStats.tickets.open }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                        <div class="h-full rounded-full bg-rose-500 transition-all duration-500" :style="{ width: ticketBarWidth(dbStats.tickets.open) }" />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>In Progress</span>
                                        <span class="font-semibold tabular-nums text-blue-500">{{ dbStats.tickets.in_progress }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                        <div class="h-full rounded-full bg-blue-500 transition-all duration-500" :style="{ width: ticketBarWidth(dbStats.tickets.in_progress) }" />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>On Hold</span>
                                        <span class="font-semibold tabular-nums text-amber-500">{{ dbStats.tickets.on_hold }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                        <div class="h-full rounded-full bg-amber-500 transition-all duration-500" :style="{ width: ticketBarWidth(dbStats.tickets.on_hold) }" />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Resolved</span>
                                        <span class="font-semibold tabular-nums text-emerald-500">{{ dbStats.tickets.resolved }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" :style="{ width: ticketBarWidth(dbStats.tickets.resolved) }" />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Closed</span>
                                        <span class="font-semibold tabular-nums">{{ dbStats.tickets.closed }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                        <div class="h-full rounded-full bg-muted-foreground/40 transition-all duration-500" :style="{ width: ticketBarWidth(dbStats.tickets.closed) }" />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Comments -->
                    <Card class="border border-border/60 bg-card shadow-sm transition-shadow hover:shadow-md">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-500/10">
                                    <FileText class="h-3.5 w-3.5 text-purple-500" />
                                </div>
                                <span class="font-semibold">Comments</span>
                            </div>
                            <div class="mb-4 text-4xl font-bold tabular-nums">{{ dbStats.comments.total }}</div>
                            <div class="space-y-2 text-sm text-muted-foreground">
                                <div class="flex items-center justify-between">
                                    <span>Reactions</span>
                                    <span class="rounded border border-purple-500/20 bg-purple-500/10 px-1.5 py-0.5 text-xs font-semibold text-purple-500">{{ dbStats.comments.reactions }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Votes</span>
                                    <span class="rounded border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 text-xs font-semibold text-emerald-500">{{ dbStats.comments.votes }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Audit Log Ribbon -->
                <div class="flex items-center justify-between rounded-xl border border-border/60 bg-card px-4 py-3 shadow-sm">
                    <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Activity class="h-4 w-4" />
                        Total audit log entries
                    </div>
                    <span class="font-bold tabular-nums text-foreground">{{ dbStats.auditLogCount }}</span>
                </div>
            </section>

            <!-- Environment Information -->
            <Card class="border border-border/60 shadow-sm">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <Server class="h-5 w-5 text-muted-foreground" />
                        <div>
                            <CardTitle class="text-base font-bold">Environment Information</CardTitle>
                            <CardDescription class="text-xs">Runtime and configuration details</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-x-10 sm:grid-cols-2">
                        <!-- Col 1 -->
                        <div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Settings class="h-4 w-4 shrink-0" /> PHP Version
                                </div>
                                <span class="font-semibold text-sm tabular-nums">{{ phpVersion }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Server class="h-4 w-4 shrink-0" /> Environment
                                </div>
                                <Badge variant="secondary" class="rounded-md border-none bg-muted/60 px-2 py-0.5 font-bold hover:bg-muted/60">{{ environment }}</Badge>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Database class="h-4 w-4 shrink-0" /> Database Name
                                </div>
                                <span class="font-semibold text-sm">{{ dbName }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Database class="h-4 w-4 shrink-0" /> DB Size (on disk)
                                </div>
                                <div class="flex flex-col items-end gap-0.5">
                                    <span class="font-semibold text-sm tabular-nums">{{ dbSizeMb }} MB</span>
                                    <span class="text-[10px] text-muted-foreground/60">data + indexes</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <DatabaseBackup class="h-4 w-4 shrink-0" /> Backups Size
                                </div>
                                <div class="flex flex-col items-end gap-0.5">
                                    <span class="font-semibold text-sm tabular-nums">{{ backupsTotalSizeMb }} MB</span>
                                    <span class="text-[10px] text-muted-foreground/60">all .sql files</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Activity class="h-4 w-4 shrink-0" /> Timezone
                                </div>
                                <span class="font-semibold text-sm">{{ timezone }}</span>
                            </div>
                        </div>
                        <!-- Col 2 -->
                        <div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Activity class="h-4 w-4 shrink-0" /> Laravel Version
                                </div>
                                <span class="font-semibold text-sm tabular-nums">{{ laravelVersion }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Bug class="h-4 w-4 shrink-0" /> Debug Mode
                                </div>
                                <Badge :variant="debugMode ? 'destructive' : 'secondary'" class="rounded-md border-none px-2 py-0.5 font-bold">
                                    {{ debugMode ? 'Enabled' : 'Disabled' }}
                                </Badge>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Database class="h-4 w-4 shrink-0" /> DB Driver
                                </div>
                                <span class="font-semibold text-sm capitalize">{{ dbConnection }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <HardDrive class="h-4 w-4 shrink-0" /> Cache Driver
                                </div>
                                <span class="font-semibold text-sm capitalize">{{ cacheDriver }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-border/40 py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock class="h-4 w-4 shrink-0" /> Server Time
                                </div>
                                <span class="font-semibold text-sm tabular-nums">{{ serverTime }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Server class="h-4 w-4 shrink-0" /> OS Info
                                </div>
                                <span class="max-w-[200px] truncate text-right text-xs font-medium text-muted-foreground" :title="serverInfo">{{ serverInfo }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Data Backups -->
            <section class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-[11px] font-bold uppercase tracking-widest text-muted-foreground">Data Backups</h2>
                        <span v-if="backups.length > 0" class="rounded-full bg-muted/60 px-1.5 py-0.5 text-[10px] font-bold text-muted-foreground">{{ backups.length }}</span>
                    </div>
                    <button
                        type="button"
                        :disabled="isCreatingBackup"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-primary/60 bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary shadow-sm transition-colors hover:bg-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="createBackup"
                    >
                        <DatabaseBackup class="h-3.5 w-3.5" :class="{ 'animate-pulse': isCreatingBackup }" />
                        {{ isCreatingBackup ? 'Creating…' : 'Create Backup' }}
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-border/60 bg-card">
                    <div v-if="backups.length === 0" class="flex flex-col items-center justify-center px-4 py-12 text-center">
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted/60">
                            <DatabaseBackup class="h-6 w-6 text-muted-foreground/40" />
                        </div>
                        <p class="text-sm font-semibold text-foreground">No backups yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">Click <span class="font-medium">Create Backup</span> to generate your first database snapshot.</p>
                    </div>
                    <div v-else class="divide-y divide-border/40">
                        <div
                            v-for="backup in backups"
                            :key="backup.name"
                            class="group flex items-center justify-between p-4 transition-colors hover:bg-muted/20"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                                    <FileText class="h-5 w-5 text-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ backup.name }}</p>
                                    <div class="mt-0.5 flex items-center gap-3 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1"><Clock class="h-3 w-3" />{{ backup.date }}</span>
                                        <span class="flex items-center gap-1"><HardDrive class="h-3 w-3" />{{ backup.size }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4 flex shrink-0 items-center gap-1 opacity-60 transition-opacity group-hover:opacity-100">
                                <a
                                    :href="route('diagnostics.backup.download', { filename: backup.name })"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    title="Download"
                                >
                                    <Download class="h-4 w-4" />
                                </a>
                                <button
                                    type="button"
                                    :disabled="isDeletingBackup !== null"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-500 disabled:opacity-40"
                                    title="Delete"
                                    @click="confirmDelete(backup.name)"
                                >
                                    <Trash2 class="h-4 w-4" :class="{ 'animate-pulse': isDeletingBackup === backup.name }" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- System Logs -->
            <section class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-muted-foreground">System Logs</h2>
                    <span class="text-[10px] text-muted-foreground/60">Last 50 lines</span>
                </div>

                <div class="flex w-max items-center gap-1 rounded-lg border border-border/60 bg-muted/40 p-1">
                    <button
                        :class="[
                            'flex items-center gap-2 rounded-md px-4 py-1.5 text-xs font-semibold transition-all',
                            activeLogTab === 'laravel' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeLogTab = 'laravel'"
                    >
                        <Terminal class="h-3.5 w-3.5" /> Laravel Log
                    </button>
                    <button
                        :class="[
                            'flex items-center gap-2 rounded-md px-4 py-1.5 text-xs font-semibold transition-all',
                            activeLogTab === 'php' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeLogTab = 'php'"
                    >
                        <FileText class="h-3.5 w-3.5" /> PHP Error Log
                    </button>
                </div>

                <div class="flex flex-col overflow-hidden rounded-xl border border-border/60 bg-[#0d1117]">
                    <div class="flex items-center justify-between border-b border-white/10 bg-[#161b22] px-4 py-2">
                        <span class="truncate text-xs font-mono text-white/50">
                            {{ activeLogTab === 'laravel' ? logs.laravel.path : logs.php.path }}
                        </span>
                        <div class="ml-3 flex shrink-0 items-center gap-2">
                            <span class="rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-bold text-white/70">
                                {{ activeLogTab === 'laravel' ? logs.laravel.size : logs.php.size }}
                            </span>
                            <button
                                class="inline-flex items-center justify-center rounded p-1 text-white/50 transition-colors hover:bg-white/10 hover:text-white"
                                :title="copied ? 'Copied!' : 'Copy to clipboard'"
                                @click="copyLogs"
                            >
                                <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-400" />
                                <Copy v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="max-h-[500px] overflow-auto p-4">
                        <pre class="whitespace-pre-wrap break-all text-xs font-mono leading-relaxed text-slate-300">{{ activeLogTab === 'laravel' ? logs.laravel.content : logs.php.content }}</pre>
                    </div>
                </div>
            </section>

        </div>
    </AppLayout>

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="confirmingDelete !== null" @update:open="(v) => { if (!v) confirmingDelete = null; }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base">
                    <Trash2 class="h-4 w-4 text-rose-500" />
                    Delete Backup
                </DialogTitle>
                <DialogDescription class="break-all text-sm">
                    Are you sure you want to permanently delete
                    <span class="font-medium text-foreground">{{ confirmingDelete }}</span>?
                    This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-2 gap-2 sm:gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-muted"
                    @click="confirmingDelete = null"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="isDeletingBackup !== null"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-rose-600 disabled:opacity-50"
                    @click="deleteBackup"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    Delete
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
