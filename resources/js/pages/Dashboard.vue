<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { VisAxis, VisLine, VisXYContainer, VisArea, VisScatter } from '@unovis/vue';
import { CurveType } from '@unovis/ts';
import { AlertCircle, CheckCircle2, Clock, TrendingUp } from 'lucide-vue-next';
import { ChartCrosshair } from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import Sparkline from '@/components/Sparkline.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const stats = [
    {
        title: 'Total Incidents',
        value: '128',
        description: 'Since last week',
        trend: '13.4%',
        isUp: true,
        icon: AlertCircle,
        textColor: 'text-blue-600 dark:text-blue-400',
        cardBg: 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-100/50 dark:border-blue-900/50',
        sparkline: [10, 15, 12, 25, 20, 30, 28, 40, 35, 45, 42],
        stroke: '#3b82f6',
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
        textColor: 'text-rose-600 dark:text-rose-400',
        cardBg: 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-100/50 dark:border-rose-900/50',
        sparkline: [5, 12, 10, 15, 20, 18, 28, 25, 35, 32, 40],
        stroke: '#f43f5e',
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
    { name: 'Network', count: 45, color: 'bg-blue-500' },
    { name: 'Hardware', count: 32, color: 'bg-purple-500' },
    { name: 'Software', count: 28, color: 'bg-orange-500' },
    { name: 'Access', count: 15, color: 'bg-green-500' },
    { name: 'Security', count: 8, color: 'bg-red-500' },
];

const totalCategoriesCount = categories.reduce((sum, cat) => sum + cat.count, 0);

const trendData = [
    { x: 0, day: 'Mon', Incidents: 12 },
    { x: 1, day: 'Tue', Incidents: 18 },
    { x: 2, day: 'Wed', Incidents: 15 },
    { x: 3, day: 'Thu', Incidents: 25 },
    { x: 4, day: 'Fri', Incidents: 22 },
    { x: 5, day: 'Sat', Incidents: 8 },
    { x: 6, day: 'Sun', Incidents: 5 },
];

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
            <!-- Stats Grid -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

            <div class="grid gap-6 lg:grid-cols-7">
                <!-- Incidents Over Time Line Chart -->
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
                        <div class="h-[250px] w-full pt-4 min-w-0">
                            <VisXYContainer :data="trendData" class="h-full w-full" :duration="600">
                                <VisAxis 
                                    type="x" 
                                    :x="(d) => d.x" 
                                    :grid-line="false" 
                                    :tick-line="false"
                                    :domain-line="false"
                                    :tick-format="(x) => trendData[x]?.day"
                                />
                                <VisAxis 
                                    type="y" 
                                    :grid-line="true" 
                                    :tick-line="false" 
                                    :domain-line="false" 
                                />
                                <VisArea 
                                    :x="(d) => d.x" 
                                    :y="(d) => d.Incidents" 
                                    color="url(#emeraldGradient)" 
                                    :opacity="1" 
                                    :curve-type="CurveType.MonotoneX" 
                                />
                                <VisLine 
                                    :x="(d) => d.x" 
                                    :y="(d) => d.Incidents" 
                                    color="#10b981" 
                                    :stroke-width="2.5" 
                                    :curve-type="CurveType.MonotoneX" 
                                />
                                <VisScatter
                                    :x="(d) => d.x"
                                    :y="(d) => d.Incidents"
                                    color="#10b981"
                                    :size="5"
                                    :stroke-width="2"
                                    stroke-color="hsl(var(--background))"
                                />
                                <ChartCrosshair 
                                    :index="'x'" 
                                    :colors="['#10b981']" 
                                    :items="[{ name: 'Incidents', color: '#10b981' }]"
                                />
                            </VisXYContainer>
                        </div>
                    </CardContent>
                    <div class="p-6 pt-0 mt-4 flex flex-col gap-1 border-t border-border/50 pt-5">
                        <div class="flex items-center gap-2 font-medium text-sm">
                            Trending up by 5.2% this week <TrendingUp class="h-4 w-4 text-emerald-500" />
                        </div>
                        <p class="text-xs text-muted-foreground">Compared to previous week (Monday - Sunday)</p>
                    </div>
                </Card>

                <!-- Incident Categories -->
                <Card class="col-span-full lg:col-span-3 shadow-none border border-border/50 overflow-hidden min-w-0">
                    <CardHeader>
                        <CardTitle class="text-lg font-semibold">Incidents by Category</CardTitle>
                        <p class="text-sm text-muted-foreground">Distribution of reported issues</p>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-5">
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
                                    <div 
                                        :class="cn('h-full transition-all duration-500 ease-out group-hover:opacity-80', cat.color)" 
                                        :style="{ width: `${(cat.count / totalCategoriesCount) * 100}%` }"
                                    ></div>
                                </div>
                                <!-- Subtle highlight background on hover - adjusted inset to prevent overflow -->
                                <div class="absolute -inset-x-2 -inset-y-2.5 z-[-1] rounded-lg bg-muted/50 opacity-0 transition-opacity group-hover:opacity-100"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Activity -->
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
                            <!-- Activity Icon (hidden on very small screens to save space) -->
                            <div 
                                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted/50 transition-colors group-hover:bg-background border border-border/30 sm:flex"
                            >
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
