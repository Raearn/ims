<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { VisAxis, VisLine, VisXYContainer, VisArea, VisScatter } from '@unovis/vue';
import DonutChart from '@/components/DonutChart.vue';
import { CurveType } from '@unovis/ts';
import { AlertCircle, CheckCircle2, Clock, TrendingUp, BarChart2, PieChart, TrendingDown, Flame } from 'lucide-vue-next';
import { ChartCrosshair } from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import Sparkline from '@/components/Sparkline.vue';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const stats = [
    {
        title: 'Total Open Incidents',
        value: '128',
        description: 'Since last week',
        trend: '13.4%',
        isUp: true,
        icon: AlertCircle,
        textColor: 'text-rose-600 dark:text-rose-400',
        cardBg: 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-100/50 dark:border-rose-900/50',
        sparkline: [10, 15, 12, 25, 20, 30, 28, 40, 35, 45, 42],
        stroke: '#f43f5e',
    },
    {
        title: 'Pending Review',
        value: '24',
        description: 'Work in progress',
        trend: '5.2%',
        isUp: true,
        icon: Clock,
        textColor: 'text-orange-600 dark:text-orange-400',
        cardBg: 'bg-orange-50/50 dark:bg-orange-950/20 border-orange-100/50 dark:border-orange-900/50',
        sparkline: [15, 18, 14, 22, 25, 20, 24, 28, 22, 26, 24],
        stroke: '#f97316',
    },
    {
        title: 'Resolved Incidents',
        value: '112',
        description: 'Since last week',
        trend: '13.4%',
        isUp: true,
        icon: CheckCircle2,
        textColor: 'text-blue-600 dark:text-blue-400',
        cardBg: 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-100/50 dark:border-blue-900/50',
        sparkline: [5, 12, 10, 15, 20, 18, 28, 25, 35, 32, 40],
        stroke: '#3b82f6',
    },
    {
        title: 'Avg. Resolution Time',
        value: '4.2h',
        description: 'Since last week',
        trend: '13.4%',
        isUp: false,
        icon: Clock,
        textColor: 'text-emerald-600 dark:text-emerald-400',
        cardBg: 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-100/50 dark:border-emerald-900/50',
        sparkline: [40, 35, 38, 30, 32, 25, 28, 20, 22, 15, 18],
        stroke: '#10b981',
    },
];

const categories = [
    { name: 'Network', count: 45, color: 'bg-blue-500', hex: '#3b82f6' },
    { name: 'Hardware', count: 32, color: 'bg-purple-500', hex: '#a855f7' },
    { name: 'Software', count: 28, color: 'bg-orange-500', hex: '#f97316' },
    { name: 'Access', count: 15, color: 'bg-green-500', hex: '#22c55e' },
    { name: 'Security', count: 8, color: 'bg-red-500', hex: '#ef4444' },
];

const totalCategoriesCount = categories.reduce((sum, cat) => sum + cat.count, 0);
const maxCategoryCount = Math.max(...categories.map(c => c.count));
const categoryChartType = ref<'bar' | 'donut'>('bar');

const severities = [
    { name: 'Critical', count: 18, color: 'bg-rose-500', hex: '#f43f5e' },
    { name: 'High', count: 34, color: 'bg-orange-500', hex: '#f97316' },
    { name: 'Medium', count: 52, color: 'bg-yellow-500', hex: '#eab308' },
    { name: 'Low', count: 24, color: 'bg-blue-400', hex: '#60a5fa' },
];

const totalSeverityCount = severities.reduce((sum, s) => sum + s.count, 0);
const severityChartType = ref<'bar' | 'donut'>('donut');

const trendData = [
    { x: 0, day: 'Mon', Incidents: 12 },
    { x: 1, day: 'Tue', Incidents: 18 },
    { x: 2, day: 'Wed', Incidents: 15 },
    { x: 3, day: 'Thu', Incidents: 25 },
    { x: 4, day: 'Fri', Incidents: 22 },
    { x: 5, day: 'Sat', Incidents: 8 },
    { x: 6, day: 'Sun', Incidents: 5 },
];

const recurringIncidents = [
    { rank: 1, title: 'Network connectivity loss', category: 'Network', count: 23, trend: 'up', change: 15 },
    { rank: 2, title: 'VPN access failure', category: 'Access', count: 18, trend: 'down', change: 8 },
    { rank: 3, title: 'Email service outage', category: 'Software', count: 15, trend: 'up', change: 12 },
    { rank: 4, title: 'Printer not responding', category: 'Hardware', count: 12, trend: 'up', change: 3 },
    { rank: 5, title: 'Password reset request', category: 'Access', count: 11, trend: 'down', change: 5 },
    { rank: 6, title: 'Unauthorized access attempt', category: 'Security', count: 9, trend: 'up', change: 44 },
];

const maxRecurringCount = Math.max(...recurringIncidents.map(i => i.count));

const categoryBadge: Record<string, string> = {
    Network: 'bg-blue-500/15 text-blue-400 border-blue-500/25',
    Hardware: 'bg-purple-500/15 text-purple-400 border-purple-500/25',
    Software: 'bg-orange-500/15 text-orange-400 border-orange-500/25',
    Access: 'bg-green-500/15 text-green-400 border-green-500/25',
    Security: 'bg-rose-500/15 text-rose-400 border-rose-500/25',
};

const recentActivity = [
    {
        id: 1,
        title: 'New high priority incident reported',
        time: '2 hours ago',
        team: 'Technical Team',
        priority: 'Critical',
    },
    {
        id: 2,
        title: 'Network maintenance completed',
        time: '4 hours ago',
        team: 'Infrastructure',
        priority: 'Normal',
    },
    {
        id: 3,
        title: 'User access requests processed',
        time: '5 hours ago',
        team: 'Security Team',
        priority: 'High',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
            <div>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Dashboard</h2>
                <p class="text-sm text-muted-foreground">Overview of incidents and system activity.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card v-for="stat in stats" :key="stat.title" :class="cn('shadow-none border', stat.cardBg)">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle :class="cn('text-sm font-medium opacity-80', stat.textColor)">
                            {{ stat.title }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pb-4">
                        <div class="flex items-end justify-between">
                            <div>
                                <div class="text-2xl font-bold tracking-tight md:text-3xl">{{ stat.value }}</div>
                            </div>
                            <!-- Sparkline -->
                            <div class="h-12 w-24">
                                <Sparkline :data="stat.sparkline" :stroke="stat.stroke" :width="96" :height="48" />
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ stat.description }}</span>
                            <Badge variant="outline" class="bg-background/50 border-none px-1.5 py-0.5 text-[10px] font-bold h-5 shadow-sm">
                                {{ stat.trend }}
                                <TrendingUp class="ml-0.5 h-3.5 w-3.5" :class="stat.isUp ? '' : 'rotate-180'" />
                            </Badge>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Row 1: Incidents Over Time + Incidents by Severity -->
            <div class="grid gap-4 sm:gap-6 lg:grid-cols-7">
                <!-- Incidents Over Time -->
                <Card class="col-span-full lg:col-span-4 shadow-none flex flex-col group/chart border border-border/50 overflow-hidden min-w-0">
                    <CardHeader class="flex flex-col items-start gap-1 pb-2">
                        <CardTitle class="text-lg font-semibold">Incidents Over Time</CardTitle>
                        <p class="text-sm text-muted-foreground">Showing reported incidents for the last 7 days</p>
                    </CardHeader>
                    <CardContent class="flex-1 pb-0 min-w-0">
                        <svg width="0" height="0" class="block">
                            <defs>
                                <linearGradient id="emeraldGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stop-color="#10b981" stop-opacity="0.3"/>
                                    <stop offset="95%" stop-color="#10b981" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="h-[180px] w-full pt-4 min-w-0 sm:h-[250px]">
                            <VisXYContainer :data="trendData" class="h-full w-full" :duration="600">
                                <VisAxis type="x" :x="(d) => d.x" :grid-line="false" :tick-line="false" :domain-line="false" :tick-format="(x) => trendData[x]?.day" />
                                <VisAxis type="y" :grid-line="true" :tick-line="false" :domain-line="false" />
                                <VisArea :x="(d) => d.x" :y="(d) => d.Incidents" color="url(#emeraldGradient)" :opacity="1" :curve-type="CurveType.MonotoneX" />
                                <VisLine :x="(d) => d.x" :y="(d) => d.Incidents" color="#10b981" :stroke-width="2.5" :curve-type="CurveType.MonotoneX" />
                                <VisScatter :x="(d) => d.x" :y="(d) => d.Incidents" color="#10b981" :size="5" :stroke-width="2" stroke-color="hsl(var(--background))" />
                                <ChartCrosshair :index="'x'" :colors="['#10b981']" :items="[{ name: 'Incidents', color: '#10b981' }]" />
                            </VisXYContainer>
                        </div>
                    </CardContent>
                    <div class="p-4 pt-0 mt-4 flex flex-col gap-1 border-t border-border/50 pt-5 sm:p-6">
                        <div class="flex flex-wrap items-center gap-2 font-medium text-sm">
                            Trending up by 5.2% this week <TrendingUp class="h-4 w-4 text-emerald-500" />
                        </div>
                        <p class="text-xs text-muted-foreground">Compared to previous week (Monday - Sunday)</p>
                    </div>
                </Card>

                <!-- Incidents by Severity -->
                <Card class="col-span-full lg:col-span-3 shadow-none border border-border/50 overflow-hidden min-w-0">
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle class="text-lg font-semibold">Incidents by Severity</CardTitle>
                                <p class="text-sm text-muted-foreground">Breakdown by priority level</p>
                            </div>
                            <div class="flex items-center gap-0.5 rounded-lg bg-muted p-1 shrink-0">
                                <button @click="severityChartType = 'bar'" :class="['flex items-center justify-center rounded-md p-1.5 transition-all', severityChartType === 'bar' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']" title="Bar Chart">
                                    <BarChart2 class="h-4 w-4" />
                                </button>
                                <button @click="severityChartType = 'donut'" :class="['flex items-center justify-center rounded-md p-1.5 transition-all', severityChartType === 'donut' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']" title="Donut Chart">
                                    <PieChart class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="severityChartType === 'bar'" class="space-y-3 sm:space-y-5">
                            <div v-for="sev in severities" :key="sev.name" class="group relative cursor-pointer">
                                <div class="flex items-center justify-between text-sm mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div :class="cn('w-2 h-2 rounded-full shrink-0', sev.color)"></div>
                                        <span class="font-medium transition-colors group-hover:text-foreground truncate">{{ sev.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 ml-2">
                                        <span class="text-xs font-semibold text-muted-foreground group-hover:text-foreground transition-colors">{{ Math.round((sev.count / totalSeverityCount) * 100) }}%</span>
                                        <span class="text-muted-foreground w-6 text-right">{{ sev.count }}</span>
                                    </div>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                    <div :class="cn('h-full transition-all duration-500 ease-out group-hover:opacity-80', sev.color)" :style="{ width: `${(sev.count / totalSeverityCount) * 100}%` }"></div>
                                </div>
                                <div class="absolute -inset-x-2 -inset-y-2.5 z-[-1] rounded-lg bg-muted/50 opacity-0 transition-opacity group-hover:opacity-100"></div>
                            </div>
                        </div>
                        <DonutChart v-else :data="severities" :total="totalSeverityCount" />
                    </CardContent>
                </Card>
            </div>

            <!-- Row 2: Recent Activity (full width) -->
            <Card class="shadow-none border border-border/50 overflow-hidden">
                <CardHeader class="pb-3">
                    <CardTitle class="text-lg font-semibold">Recent Activity</CardTitle>
                    <p class="text-sm text-muted-foreground">Latest updates from the incident response teams</p>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="divide-y divide-border/50 border-t border-border/50">
                        <div
                            v-for="item in recentActivity"
                            :key="item.id"
                            class="group relative flex items-start gap-3 p-4 transition-colors hover:bg-muted/20 sm:items-center sm:gap-4 sm:p-6"
                        >
                            <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted/50 transition-colors group-hover:bg-background border border-border/30 sm:flex">
                                <AlertCircle v-if="item.priority === 'Critical'" class="h-5 w-5 text-destructive" />
                                <Clock v-else-if="item.priority === 'High'" class="h-5 w-5 text-orange-500" />
                                <CheckCircle2 v-else class="h-5 w-5 text-emerald-500" />
                            </div>
                            <div class="flex-1 min-w-0 space-y-1.5 sm:space-y-1">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <p class="text-sm font-semibold truncate text-foreground leading-tight">{{ item.title }}</p>
                                    <div class="flex items-center gap-2 shrink-0 self-start sm:self-center">
                                        <Badge :variant="item.priority === 'Critical' ? 'destructive' : (item.priority === 'High' ? 'default' : 'secondary')" class="text-[10px] font-bold h-5 px-1.5 uppercase tracking-wider shadow-sm">
                                            {{ item.priority }}
                                        </Badge>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[11px] sm:text-xs text-muted-foreground">
                                    <div class="flex items-center gap-1.5">
                                        <Clock class="h-3.5 w-3.5 opacity-70" />
                                        <span>{{ item.time }}</span>
                                    </div>
                                    <span class="hidden sm:inline opacity-30">•</span>
                                    <div class="font-medium text-foreground/70">{{ item.team }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Row 3: Incidents by Category + Top Recurring Incidents -->
            <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                <!-- Incidents by Category -->
                <Card class="shadow-none border border-border/50 overflow-hidden min-w-0">
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle class="text-lg font-semibold">Incidents by Category</CardTitle>
                                <p class="text-sm text-muted-foreground">Distribution of reported issues</p>
                            </div>
                            <div class="flex items-center gap-0.5 rounded-lg bg-muted p-1 shrink-0">
                                <button @click="categoryChartType = 'bar'" :class="['flex items-center justify-center rounded-md p-1.5 transition-all', categoryChartType === 'bar' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']" title="Bar Chart">
                                    <BarChart2 class="h-4 w-4" />
                                </button>
                                <button @click="categoryChartType = 'donut'" :class="['flex items-center justify-center rounded-md p-1.5 transition-all', categoryChartType === 'donut' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']" title="Donut Chart">
                                    <PieChart class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="categoryChartType === 'bar'" class="space-y-3 sm:space-y-5">
                            <div v-for="cat in categories" :key="cat.name" class="group relative cursor-pointer">
                                <div class="flex items-center justify-between text-sm mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div :class="cn('w-2 h-2 rounded-full shrink-0', cat.color)"></div>
                                        <span class="font-medium transition-colors group-hover:text-foreground truncate">{{ cat.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 ml-2">
                                        <span class="text-xs font-semibold text-muted-foreground group-hover:text-foreground transition-colors">{{ Math.round((cat.count / totalCategoriesCount) * 100) }}%</span>
                                        <span class="text-muted-foreground w-6 text-right">{{ cat.count }}</span>
                                    </div>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                    <div :class="cn('h-full transition-all duration-500 ease-out group-hover:opacity-80', cat.color)" :style="{ width: `${(cat.count / totalCategoriesCount) * 100}%` }"></div>
                                </div>
                                <div class="absolute -inset-x-2 -inset-y-2.5 z-[-1] rounded-lg bg-muted/50 opacity-0 transition-opacity group-hover:opacity-100"></div>
                            </div>
                        </div>
                        <DonutChart v-else :data="categories" :total="totalCategoriesCount" />
                    </CardContent>
                </Card>

                <!-- Top Recurring Incidents -->
                <Card class="shadow-none border border-border/50 overflow-hidden min-w-0">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle class="text-lg font-semibold">Top Recurring Incidents</CardTitle>
                                <p class="text-sm text-muted-foreground">Most frequent issues this month</p>
                            </div>
                            <div class="flex items-center gap-1 rounded-md bg-rose-500/10 border border-rose-500/20 px-2 py-1">
                                <Flame class="h-3.5 w-3.5 text-rose-400" />
                                <span class="text-[10px] font-bold text-rose-400 uppercase tracking-wide">This Month</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="divide-y divide-border/40">
                            <div
                                v-for="incident in recurringIncidents"
                                :key="incident.rank"
                                class="group flex items-center gap-2 px-3 py-3 transition-colors hover:bg-muted/30 sm:gap-4 sm:px-6 sm:py-3.5"
                            >
                                <!-- Rank -->
                                <span class="text-sm font-bold tabular-nums w-5 shrink-0 text-muted-foreground/50 group-hover:text-muted-foreground transition-colors">
                                    {{ incident.rank }}
                                </span>

                                <!-- Mini bar + title -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <p class="text-sm font-medium text-foreground truncate leading-none">{{ incident.title }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 flex-1 rounded-full bg-secondary overflow-hidden">
                                            <div
                                                class="h-full rounded-full bg-primary/60 group-hover:bg-primary transition-colors duration-300"
                                                :style="{ width: `${(incident.count / maxRecurringCount) * 100}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-[10px] font-bold tabular-nums text-muted-foreground w-8 text-right shrink-0">{{ incident.count }}x</span>
                                    </div>
                                </div>

                                <!-- Category + trend -->
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded border', categoryBadge[incident.category] ?? 'bg-muted text-muted-foreground border-border']">
                                        {{ incident.category }}
                                    </span>
                                    <div :class="['hidden items-center gap-0.5 text-[10px] font-semibold sm:flex', incident.trend === 'up' ? 'text-rose-400' : 'text-emerald-400']">
                                        <TrendingUp v-if="incident.trend === 'up'" class="h-3 w-3" />
                                        <TrendingDown v-else class="h-3 w-3" />
                                        {{ incident.change }}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.unovis-xy-container) {
    --vis-crosshair-line-stroke-color: hsl(var(--border));
    --vis-crosshair-circle-stroke-color: #10b981;
    --vis-tooltip-background-color: transparent;
    --vis-tooltip-border-color: transparent;
    --vis-tooltip-padding: 0;
}

:deep(.unovis-tooltip) {
    background-color: transparent;
    border: none;
    box-shadow: none;
    padding: 0;
}

:deep(.unovis-scatter-point) {
    transition: all 0.2s ease;
    cursor: pointer;
}

:deep(.unovis-scatter-point:hover) {
    transform: scale(1.4);
    filter: brightness(1.1);
}

:deep(.unovis-axis-grid-line) {
    stroke: hsl(var(--border));
    stroke-opacity: 0.15;
}

:deep(.unovis-axis-domain-line) {
    stroke: transparent;
}

:deep(.unovis-axis-tick-text) {
    fill: hsl(var(--muted-foreground));
    font-size: 11px;
}

</style>
