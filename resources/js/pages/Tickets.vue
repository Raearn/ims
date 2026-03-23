<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuLabel, 
    DropdownMenuSeparator, 
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { 
    Search, 
    Filter, 
    MoreHorizontal, 
    Plus, 
    Clock, 
    AlertCircle, 
    AlertTriangle, 
    ArrowUpCircle, 
    Circle,
    CheckCircle2,
    Trash2,
    ExternalLink,
    UserPlus,
    Network,
    HardDrive,
    Code,
    Key,
    Shield,
    Info,
    HelpCircle
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Ticket, TicketCheck, Loader, Pause, Play, X, Ban, ChevronsUpDown, ChevronUp, ChevronDown } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tickets',
        href: '/tickets',
    },
];

const props = defineProps<{
    tickets: {
        id: string;
        title: string;
        status: string;
        priority: string;
        category: string;
        assignedTo: string;
        createdAt: string;
    }[];
    filters: {
        search: string;
        status: string;
    };
}>();

const ticketStats = computed(() => [
    {
        label: 'Total',
        status: 'All',
        value: props.tickets.length,
        icon: Ticket,
        colorClass: 'text-primary',
        bgClass: 'bg-primary/10',
        borderActive: 'border-primary/40',
        glowClass: 'shadow-primary/20',
    },
    {
        label: 'Open',
        status: 'Open',
        value: props.tickets.filter(t => t.status === 'Open').length,
        icon: AlertTriangle,
        colorClass: 'text-rose-400',
        bgClass: 'bg-rose-500/15',
        borderActive: 'border-rose-500/40',
        glowClass: 'shadow-rose-500/20',
    },
    {
        label: 'In Progress',
        status: 'In Progress',
        value: props.tickets.filter(t => t.status === 'In Progress').length,
        icon: Play,
        colorClass: 'text-blue-400',
        bgClass: 'bg-blue-500/15',
        borderActive: 'border-blue-500/40',
        glowClass: 'shadow-blue-500/20',
    },
    {
        label: 'Resolved',
        status: 'Resolved',
        value: props.tickets.filter(t => t.status === 'Resolved').length,
        icon: CheckCircle2,
        colorClass: 'text-emerald-400',
        bgClass: 'bg-emerald-500/15',
        borderActive: 'border-emerald-500/40',
        glowClass: 'shadow-emerald-500/20',
    },
    {
        label: 'Closed',
        status: 'Closed',
        value: props.tickets.filter(t => t.status === 'Closed').length,
        icon: Ban,
        colorClass: 'text-slate-400',
        bgClass: 'bg-slate-500/15',
        borderActive: 'border-slate-500/40',
        glowClass: 'shadow-slate-500/20',
    },
]);

const isCreateModalOpen = ref(false);
const currentStep = ref(1);
const form = useForm({
    title: '',
    description: '',
    priority: 'Medium',
    category: 'Software',
});

const submit = () => {
    form.post(route('tickets.store'), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
            currentStep.value = 1;
        },
    });
};

watch(isCreateModalOpen, (val) => {
    if (val) {
        currentStep.value = 1;
        form.reset();
    }
});

const search = ref(props.filters.search || '');
const currentStatus = ref(props.filters.status || 'All');
const selectedIds = ref<string[]>([]);
const statuses = ['All', 'Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'];

const categories = [
    { name: 'Network', icon: Network },
    { name: 'Hardware', icon: HardDrive },
    { name: 'Software', icon: Code },
    { name: 'Access', icon: Key },
    { name: 'Security', icon: Shield },
    { name: 'Others', icon: HelpCircle },
];

const priorities = [
    { name: 'Low', icon: Circle, color: 'text-muted-foreground' },
    { name: 'Medium', icon: ArrowUpCircle, color: 'text-blue-500' },
    { name: 'High', icon: AlertTriangle, color: 'text-orange-500' },
    { name: 'Critical', icon: AlertCircle, color: 'text-destructive' },
];
let searchTimeout: any = null;

const isAllSelected = computed(() => 
    props.tickets.length > 0 && selectedIds.value.length === props.tickets.length
);

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.tickets.map(t => t.id);
    }
};

type SortKey = 'id' | 'title' | 'assignedTo' | 'status' | 'priority' | 'createdAt';
const sortKey = ref<SortKey | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');

const priorityOrder: Record<string, number> = { Critical: 4, High: 3, Medium: 2, Low: 1 };
const statusOrder: Record<string, number> = { Open: 1, 'In Progress': 2, 'On Hold': 3, Resolved: 4, Closed: 5 };

const sortedTickets = computed(() => {
    if (!sortKey.value) return props.tickets;
    return [...props.tickets].sort((a, b) => {
        let aVal: any = a[sortKey.value!];
        let bVal: any = b[sortKey.value!];
        if (sortKey.value === 'priority') { aVal = priorityOrder[aVal] ?? 0; bVal = priorityOrder[bVal] ?? 0; }
        if (sortKey.value === 'status') { aVal = statusOrder[aVal] ?? 0; bVal = statusOrder[bVal] ?? 0; }
        if (aVal < bVal) return sortDir.value === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const toggleSort = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
};

const filterTickets = () => {
    router.get(
        route('tickets'),
        { search: search.value, status: currentStatus.value },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        }
    );
};

watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filterTickets();
    }, 300);
});

watch(currentStatus, () => {
    filterTickets();
});

const getInitials = (name: string) => {
    if (name === 'Unassigned') return 'UN';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Open': return 'bg-rose-500/15 text-rose-400 border-rose-500/30';
        case 'In Progress': return 'bg-blue-500/15 text-blue-400 border-blue-500/30';
        case 'On Hold': return 'bg-amber-500/15 text-amber-400 border-amber-500/30';
        case 'Resolved': return 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
        case 'Closed': return 'bg-slate-500/15 text-slate-400 border-slate-500/30';
        default: return 'bg-secondary text-secondary-foreground';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'Open': return AlertTriangle;
        case 'In Progress': return Play;
        case 'On Hold': return Pause;
        case 'Resolved': return CheckCircle2;
        case 'Closed': return Ban;
        default: return Circle;
    }
};

const getPriorityIcon = (priority: string) => {
    switch (priority) {
        case 'Critical': return AlertCircle;
        case 'High': return AlertTriangle;
        case 'Medium': return ArrowUpCircle;
        default: return Circle;
    }
};

const getPriorityBadge = (priority: string) => {
    switch (priority) {
        case 'Critical': return 'bg-rose-500/15 text-rose-400 border border-rose-500/25 dark:bg-rose-500/20 dark:text-rose-300';
        case 'High':     return 'bg-orange-500/15 text-orange-400 border border-orange-500/25 dark:bg-orange-500/20 dark:text-orange-300';
        case 'Medium':   return 'bg-blue-500/15 text-blue-500 border border-blue-500/25 dark:bg-blue-500/20 dark:text-blue-300';
        case 'Low':      return 'bg-slate-500/10 text-slate-500 border border-slate-500/20 dark:bg-slate-500/15 dark:text-slate-400';
        default:         return 'bg-muted text-muted-foreground border border-border';
    }
};
</script>

<template>
    <Head title="Tickets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight md:text-2xl">Tickets</h2>
                    <p class="text-sm text-muted-foreground">Manage and track all incident tickets.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 min-w-0">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                        <input 
                            v-model="search"
                            type="search" 
                            placeholder="Search tickets..." 
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-9 py-1 text-sm shadow-sm transition-all focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                    
                    <Dialog v-model:open="isCreateModalOpen">
                        <DialogTrigger as-child>
                            <Button class="shrink-0 h-9 px-3 shadow-sm hover:shadow-md transition-all active:scale-95 sm:px-4">
                                <Plus class="h-4 w-4 sm:mr-2" />
                                <span class="hidden sm:inline">New Ticket</span>
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[550px] p-0 overflow-hidden border-none shadow-2xl">
                            <form @submit.prevent="submit" class="flex flex-col">
                                <div class="bg-primary/5 px-6 py-6 border-b border-primary/10">
                                    <DialogHeader>
                                        <div class="flex items-center justify-between mb-2">
                                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                                Incident Report
                                            </Badge>
                                            <div class="flex gap-1">
                                                <div 
                                                    v-for="i in 2" 
                                                    :key="i" 
                                                    class="h-1.5 w-8 rounded-full transition-all duration-300"
                                                    :class="currentStep >= i ? 'bg-primary' : 'bg-primary/20'"
                                                ></div>
                                            </div>
                                        </div>
                                        <DialogTitle class="text-xl font-bold tracking-tight">
                                            {{ currentStep === 1 ? 'What\'s the issue?' : 'Set Importance' }}
                                        </DialogTitle>
                                        <DialogDescription class="text-muted-foreground/80">
                                            {{ currentStep === 1 ? 'Describe the incident so we can help you resolve it.' : 'Categorize and prioritize this ticket for the team.' }}
                                        </DialogDescription>
                                    </DialogHeader>
                                </div>

                                <div class="px-6 py-6 min-h-[320px]">
                                    <!-- Step 1: Basic Info -->
                                    <div v-if="currentStep === 1" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                                        <div class="grid gap-2">
                                            <Label for="title" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Ticket Title</Label>
                                            <div class="relative">
                                                <Input 
                                                    id="title" 
                                                    v-model="form.title" 
                                                    placeholder="e.g., Network outage in Office A" 
                                                    required 
                                                    class="pl-3 py-5 text-base border-muted-foreground/20 focus:border-primary/50 transition-all"
                                                />
                                            </div>
                                            <span v-if="form.errors.title" class="text-xs text-destructive font-medium">{{ form.errors.title }}</span>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Description</Label>
                                            <RichTextEditor
                                                v-model="form.description"
                                                placeholder="Provide more context about the issue, including steps to reproduce if applicable..."
                                            />
                                            <span v-if="form.errors.description" class="text-xs text-destructive font-medium">{{ form.errors.description }}</span>
                                        </div>
                                    </div>

                                    <!-- Step 2: Category & Priority -->
                                    <div v-if="currentStep === 2" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                                        <div class="grid gap-3">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Category</Label>
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                <button 
                                                    v-for="cat in categories" 
                                                    :key="cat.name"
                                                    type="button"
                                                    @click="form.category = cat.name"
                                                    :class="[
                                                        'flex flex-col items-center justify-center gap-2 p-3 rounded-lg border-2 transition-all group relative',
                                                        form.category === cat.name 
                                                            ? 'border-primary bg-primary/5 text-primary' 
                                                            : 'border-muted hover:border-primary/30 hover:bg-muted/50 text-muted-foreground'
                                                    ]"
                                                >
                                                    <component :is="cat.icon" class="h-5 w-5" :class="form.category === cat.name ? 'scale-110' : 'group-hover:scale-110 transition-transform'" />
                                                    <span class="text-[10px] font-bold uppercase truncate w-full text-center">{{ cat.name }}</span>
                                                    <div v-if="form.category === cat.name" class="absolute -top-1 -right-1">
                                                        <CheckCircle2 class="h-4 w-4 fill-primary text-white" />
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="grid gap-3">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Priority Level</Label>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <button 
                                                    v-for="prio in priorities" 
                                                    :key="prio.name"
                                                    type="button"
                                                    @click="form.priority = prio.name"
                                                    :class="[
                                                        'flex items-center gap-3 p-3 rounded-lg border-2 transition-all relative overflow-hidden group',
                                                        form.priority === prio.name 
                                                            ? 'border-primary bg-primary/5 shadow-sm' 
                                                            : 'border-muted hover:border-primary/30 hover:bg-muted/50'
                                                    ]"
                                                >
                                                    <div :class="['p-2 rounded-full', form.priority === prio.name ? 'bg-white shadow-sm' : 'bg-muted/50']">
                                                        <component :is="prio.icon" class="h-4 w-4" :class="prio.color" />
                                                    </div>
                                                    <div class="flex flex-col items-start">
                                                        <span :class="['text-xs font-bold uppercase tracking-tight', form.priority === prio.name ? 'text-primary' : 'text-muted-foreground']">
                                                            {{ prio.name }}
                                                        </span>
                                                        <span class="text-[10px] text-muted-foreground/70 leading-none mt-0.5">
                                                            {{ prio.name === 'Critical' ? 'Immediate action' : prio.name === 'High' ? 'Fast response' : prio.name === 'Medium' ? 'Standard' : 'Non-urgent' }}
                                                        </span>
                                                    </div>
                                                    <div v-if="form.priority === prio.name" class="ml-auto">
                                                        <CheckCircle2 class="h-4 w-4 fill-primary text-white" />
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-2 p-4 rounded-lg bg-muted/30 border border-muted flex items-start gap-3">
                                            <Info class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                            <div class="text-xs text-muted-foreground leading-relaxed">
                                                Your ticket will be assigned to the <span class="font-bold text-foreground">{{ form.category }}</span> support team with a <span class="font-bold text-foreground">{{ form.priority }}</span> priority flag.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter class="px-6 py-4 bg-muted/30 border-t border-border/50 gap-2 sm:gap-0">
                                    <div class="flex flex-col-reverse sm:flex-row w-full justify-between items-center gap-2 sm:gap-0">
                                        <Button 
                                            v-if="currentStep > 1"
                                            type="button" 
                                            variant="ghost" 
                                            @click="currentStep--"
                                            class="w-full sm:w-auto text-xs font-bold uppercase tracking-widest text-muted-foreground hover:text-foreground"
                                        >
                                            Back
                                        </Button>
                                        <div v-else class="hidden sm:block"></div>

                                        <div class="flex flex-col-reverse sm:flex-row gap-2 w-full sm:w-auto">
                                            <Button type="button" variant="outline" @click="isCreateModalOpen = false" class="w-full sm:w-auto border-muted-foreground/20 hover:bg-white text-xs font-bold uppercase tracking-widest">
                                                Cancel
                                            </Button>
                                            
                                            <Button 
                                                v-if="currentStep < 2"
                                                type="button" 
                                                @click="currentStep++"
                                                :disabled="!form.title"
                                                class="w-full sm:w-auto bg-primary text-primary-foreground text-xs font-bold uppercase tracking-widest px-6"
                                            >
                                                Next Details
                                            </Button>
                                            
                                            <Button 
                                                v-else
                                                type="submit" 
                                                :disabled="form.processing"
                                                class="w-full sm:w-auto bg-primary text-primary-foreground text-xs font-bold uppercase tracking-widest px-8 shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 transition-all"
                                            >
                                                <span v-if="!form.processing" class="flex items-center gap-2">
                                                    Launch Ticket <Plus class="h-3.5 w-3.5" />
                                                </span>
                                                <span v-else class="flex items-center gap-2">
                                                    Processing <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                </span>
                                            </Button>
                                        </div>
                                    </div>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Stat Widgets -->
            <div class="flex gap-3 overflow-x-auto pb-0.5 no-scrollbar snap-x snap-mandatory md:grid md:grid-cols-3 md:overflow-visible lg:grid-cols-5 md:snap-none">
                <button
                    v-for="stat in ticketStats"
                    :key="stat.label"
                    @click="currentStatus = stat.status"
                    :class="[
                        'snap-start group relative flex shrink-0 w-[calc(70vw-2rem)] sm:w-[calc(50vw-2rem)] items-center gap-2 rounded-lg border bg-card px-3 py-3 text-left transition-all duration-200 active:scale-[0.97] md:w-full md:gap-3 md:px-4',
                        currentStatus === stat.status
                            ? ['border shadow-md', stat.borderActive, stat.glowClass]
                            : 'border-border/50 hover:border-border shadow-none hover:shadow-sm'
                    ]"
                >
                    <!-- Active indicator strip -->
                    <div
                        :class="[
                            'absolute left-0 top-3 bottom-3 w-[3px] rounded-full transition-all duration-200',
                            currentStatus === stat.status ? stat.bgClass.replace('/15', '') : 'opacity-0'
                        ]"
                    ></div>

                    <div :class="[
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition-transform duration-200 group-hover:scale-110 md:h-9 md:w-9',
                        stat.bgClass
                    ]">
                        <component :is="stat.icon" :class="['h-4 w-4 transition-all', stat.colorClass]" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-muted-foreground leading-none mb-1.5 uppercase tracking-wide">
                            {{ stat.label }}
                        </p>
                        <p :class="['text-xl font-bold leading-none tabular-nums transition-colors md:text-2xl', stat.colorClass]">
                            {{ stat.value }}
                        </p>
                    </div>
                </button>
            </div>

            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <!-- Status Tabs -->
                <div class="inline-flex h-9 items-center justify-start sm:justify-center rounded-lg bg-muted border border-border p-1 text-muted-foreground w-full max-w-full overflow-x-auto no-scrollbar shadow-sm md:h-11 md:w-fit">
                    <button
                        v-for="status in statuses"
                        :key="status"
                        @click="currentStatus = status"
                        :class="[
                            'inline-flex items-center justify-center whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 md:px-4 md:py-2 md:text-sm',
                            currentStatus === status
                                ? 'bg-primary text-primary-foreground shadow-md scale-[1.02]'
                                : 'hover:bg-background hover:text-foreground text-muted-foreground/70'
                        ]"
                    >
                        {{ status }}
                    </button>
                </div>

                <!-- Bulk Actions (Visible when selection exists) -->
                <div
                    v-show="selectedIds.length > 0"
                    class="flex items-center gap-2 transition-all duration-200"
                >
                    <span class="text-xs font-medium text-muted-foreground mr-2">{{ selectedIds.length }} selected</span>
                    <button class="inline-flex h-8 items-center justify-center rounded-md border border-input px-3 text-xs font-medium hover:bg-muted transition-colors">
                        Assign
                    </button>
                    <button class="inline-flex h-8 items-center justify-center rounded-md border border-destructive/20 bg-destructive/5 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 transition-colors">
                        Delete
                    </button>
                </div>
            </div>

            <Card class="shadow-none border border-border/50 overflow-hidden flex flex-col">
                <CardContent class="p-0 flex-1 overflow-auto">
                    <!-- Mobile Card List (shown on small screens) -->
                    <div class="md:hidden">
                        <div v-if="tickets.length === 0" class="px-6 py-20 text-center text-muted-foreground">
                            <div class="flex flex-col items-center gap-3">
                                <div class="h-12 w-12 rounded-full bg-muted/50 flex items-center justify-center">
                                    <Search class="h-6 w-6 opacity-40" />
                                </div>
                                <div class="space-y-1">
                                    <p class="font-medium text-foreground">No tickets found</p>
                                    <p class="text-sm">Try adjusting your search or filters.</p>
                                </div>
                                <button
                                    v-if="search || currentStatus !== 'All'"
                                    @click="search = ''; currentStatus = 'All'"
                                    class="mt-2 text-primary font-medium text-xs hover:underline"
                                >
                                    Clear all filters
                                </button>
                            </div>
                        </div>
                        <div v-else class="divide-y divide-border/50">
                            <div
                                v-for="ticket in sortedTickets"
                                :key="ticket.id"
                                class="group flex items-start gap-3 p-4 transition-colors hover:bg-muted/30"
                                :class="[selectedIds.includes(ticket.id) ? 'bg-primary/5 hover:bg-primary/10' : '']"
                            >
                                <Checkbox
                                    :checked="selectedIds.includes(ticket.id)"
                                    @update:checked="(val) => {
                                        if (val) selectedIds.push(ticket.id);
                                        else selectedIds = selectedIds.filter(id => id !== ticket.id);
                                    }"
                                    class="mt-1 shrink-0"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                <span class="text-xs font-mono text-foreground/40">#{{ ticket.id }}</span>
                                                <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 border shadow-sm', getStatusColor(ticket.status)]">
                                                    <component :is="getStatusIcon(ticket.status)" class="h-3 w-3" />
                                                    {{ ticket.status }}
                                                </Badge>
                                            </div>
                                            <p class="text-sm font-semibold text-foreground truncate group-hover:text-primary transition-colors">{{ ticket.title }}</p>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5">
                                                <span :class="['inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide', getPriorityBadge(ticket.priority)]">
                                                    <component :is="getPriorityIcon(ticket.priority)" class="h-3 w-3" />
                                                    {{ ticket.priority }}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground/40">•</span>
                                                <span class="text-[10px] font-medium text-muted-foreground/70 uppercase tracking-tight">{{ ticket.category }}</span>
                                                <span class="text-[10px] text-muted-foreground/40">•</span>
                                                <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                    <Clock class="h-3 w-3" />
                                                    {{ ticket.createdAt }}
                                                </div>
                                            </div>
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button class="h-8 w-8 shrink-0 inline-flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground transition-all outline-none focus:ring-2 focus:ring-primary/20">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuLabel>Ticket Actions</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem>
                                                    <ExternalLink class="mr-2 h-4 w-4" />
                                                    View Details
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <UserPlus class="mr-2 h-4 w-4" />
                                                    Assign Agent
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                                    Mark as Resolved
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem class="text-destructive focus:bg-destructive/10 focus:text-destructive">
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Delete Ticket
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table (hidden on mobile) -->
                    <div class="relative w-full hidden md:block">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead class="sticky top-0 z-10 bg-muted/30 backdrop-blur-md">
                                <tr class="border-b border-border/50 text-[11px] uppercase tracking-wider text-muted-foreground font-bold">
                                    <th class="px-6 py-4 w-12">
                                        <Checkbox
                                            :checked="isAllSelected"
                                            @update:checked="toggleSelectAll"
                                            aria-label="Select all"
                                        />
                                    </th>
                                    <th class="px-3 py-4 w-24 text-center">
                                        <button @click="toggleSort('id')" class="inline-flex items-center justify-center gap-1 w-full hover:text-foreground transition-colors">
                                            ID
                                            <ChevronUp v-if="sortKey === 'id' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'id' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                                        </button>
                                    </th>
                                    <th class="px-4 py-4">
                                        <button @click="toggleSort('title')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Ticket Info
                                            <ChevronUp v-if="sortKey === 'title' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'title' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 hidden md:table-cell w-48">
                                        <button @click="toggleSort('assignedTo')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Assigned To
                                            <ChevronUp v-if="sortKey === 'assignedTo' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'assignedTo' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 w-32">
                                        <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Status
                                            <ChevronUp v-if="sortKey === 'status' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'status' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 text-right w-20 pr-8">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50">
                                <tr v-if="tickets.length === 0">
                                    <td colspan="6" class="px-6 py-20 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 rounded-full bg-muted/50 flex items-center justify-center">
                                                <Search class="h-6 w-6 opacity-40" />
                                            </div>
                                            <div class="space-y-1">
                                                <p class="font-medium text-foreground">No tickets found</p>
                                                <p class="text-sm">Try adjusting your search or filters.</p>
                                            </div>
                                            <button
                                                v-if="search || currentStatus !== 'All'"
                                                @click="search = ''; currentStatus = 'All'"
                                                class="mt-2 text-primary font-medium text-xs hover:underline"
                                            >
                                                Clear all filters
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="ticket in sortedTickets"
                                    :key="ticket.id"
                                    class="group transition-all hover:bg-muted/30 relative"
                                    :class="[selectedIds.includes(ticket.id) ? 'bg-primary/5 hover:bg-primary/10' : '']"
                                >
                                    <td class="px-6 py-4">
                                        <Checkbox
                                            :checked="selectedIds.includes(ticket.id)"
                                            @update:checked="(val) => {
                                                if (val) selectedIds.push(ticket.id);
                                                else selectedIds = selectedIds.filter(id => id !== ticket.id);
                                            }"
                                        />
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <span class="text-xs font-mono font-bold text-foreground/40 group-hover:text-foreground/70 transition-colors">{{ ticket.id }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col max-w-[400px]">
                                            <span class="text-sm font-semibold text-foreground group-hover:text-primary transition-colors truncate">
                                                {{ ticket.title }}
                                            </span>
                                            <div class="flex items-center gap-x-2 mt-1.5 flex-wrap gap-y-1">
                                                <span :class="['inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide', getPriorityBadge(ticket.priority)]">
                                                    <component
                                                        :is="getPriorityIcon(ticket.priority)"
                                                        class="h-3 w-3"
                                                    />
                                                    {{ ticket.priority }}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground/40">•</span>
                                                <span class="text-[10px] font-medium text-muted-foreground/70 uppercase tracking-tight">{{ ticket.category }}</span>
                                                <span class="text-[10px] text-muted-foreground/40">•</span>
                                                <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                    <Clock class="h-3 w-3" />
                                                    {{ ticket.createdAt }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 hidden md:table-cell">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-7 w-7 rounded-full bg-muted flex items-center justify-center text-[10px] font-bold border border-border/50 overflow-hidden shrink-0 group-hover:border-primary/20 transition-colors">
                                                {{ getInitials(ticket.assignedTo) }}
                                            </div>
                                            <span class="text-xs font-medium text-muted-foreground truncate">{{ ticket.assignedTo }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <Badge variant="outline" :class="['inline-flex items-center gap-1 whitespace-nowrap text-[10px] font-bold px-2 py-1 border shadow-sm transition-all group-hover:shadow-md', getStatusColor(ticket.status)]">
                                            <component :is="getStatusIcon(ticket.status)" class="h-3 w-3" />
                                            {{ ticket.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 text-right pr-8">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button class="h-8 w-8 inline-flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground transition-all focus:ring-2 focus:ring-primary/20 outline-none group-hover:text-foreground">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuLabel>Ticket Actions</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem>
                                                    <ExternalLink class="mr-2 h-4 w-4" />
                                                    View Details
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <UserPlus class="mr-2 h-4 w-4" />
                                                    Assign Agent
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                                    Mark as Resolved
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem class="text-destructive focus:bg-destructive/10 focus:text-destructive">
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Delete Ticket
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
                <div class="px-4 py-3 border-t border-border/50 bg-muted/10 text-xs text-muted-foreground sm:px-6 sm:py-4">
                    Showing {{ tickets.length }} results • Use filters to narrow down your view
                </div>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
