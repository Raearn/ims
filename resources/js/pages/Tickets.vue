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
        case 'Open': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'In Progress': return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
        case 'On Hold': return 'bg-purple-500/10 text-purple-500 border-purple-500/20';
        case 'Resolved': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
        case 'Closed': return 'bg-slate-500/10 text-slate-500 border-slate-500/20';
        default: return 'bg-secondary text-secondary-foreground';
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

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'Critical': return 'text-destructive';
        case 'High': return 'text-orange-500';
        case 'Medium': return 'text-blue-500';
        default: return 'text-muted-foreground';
    }
};
</script>

<template>
    <Head title="Tickets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Tickets</h2>
                    <p class="text-sm text-muted-foreground">Manage and track all incident tickets.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative w-full sm:w-64">
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
                            <Button class="h-9 px-4 shadow-sm hover:shadow-md transition-all active:scale-95">
                                <Plus class="mr-2 h-4 w-4" /> New Ticket
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
                                            <Label for="description" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Description</Label>
                                            <textarea 
                                                id="description" 
                                                v-model="form.description"
                                                rows="5"
                                                class="flex min-h-[120px] w-full rounded-md border border-muted-foreground/20 bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary/50 transition-all resize-none"
                                                placeholder="Provide more context about the issue, including steps to reproduce if applicable..."
                                            ></textarea>
                                            <span v-if="form.errors.description" class="text-xs text-destructive font-medium">{{ form.errors.description }}</span>
                                        </div>
                                    </div>

                                    <!-- Step 2: Category & Priority -->
                                    <div v-if="currentStep === 2" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                                        <div class="grid gap-3">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Category</Label>
                                            <div class="grid grid-cols-5 gap-2">
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
                                            <div class="grid grid-cols-2 gap-3">
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
                                    <div class="flex w-full justify-between items-center">
                                        <Button 
                                            v-if="currentStep > 1"
                                            type="button" 
                                            variant="ghost" 
                                            @click="currentStep--"
                                            class="text-xs font-bold uppercase tracking-widest text-muted-foreground hover:text-foreground"
                                        >
                                            Back
                                        </Button>
                                        <div v-else></div>

                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" @click="isCreateModalOpen = false" class="border-muted-foreground/20 hover:bg-white text-xs font-bold uppercase tracking-widest">
                                                Cancel
                                            </Button>
                                            
                                            <Button 
                                                v-if="currentStep < 2"
                                                type="button" 
                                                @click="currentStep++"
                                                :disabled="!form.title"
                                                class="bg-primary text-primary-foreground text-xs font-bold uppercase tracking-widest px-6"
                                            >
                                                Next Details
                                            </Button>
                                            
                                            <Button 
                                                v-else
                                                type="submit" 
                                                :disabled="form.processing"
                                                class="bg-primary text-primary-foreground text-xs font-bold uppercase tracking-widest px-8 shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 transition-all"
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

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <!-- Status Tabs -->
                <div class="inline-flex h-10 items-center justify-center rounded-md bg-muted p-1 text-muted-foreground w-fit max-w-full overflow-x-auto no-scrollbar">
                    <button 
                        v-for="status in statuses" 
                        :key="status"
                        @click="currentStatus = status"
                        :class="[
                            'inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
                            currentStatus === status ? 'bg-background text-foreground shadow-sm' : 'hover:bg-background/50 hover:text-foreground'
                        ]"
                    >
                        {{ status }}
                    </button>
                </div>

                <!-- Bulk Actions (Visible when selection exists) -->
                <div 
                    class="flex items-center gap-2 transition-all duration-200"
                    :class="[selectedIds.length > 0 ? 'opacity-100' : 'opacity-0 pointer-events-none']"
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
                    <div class="relative w-full">
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
                                    <th class="px-3 py-4 w-24 text-center">ID</th>
                                    <th class="px-4 py-4">Ticket Info</th>
                                    <th class="px-6 py-4 hidden md:table-cell w-48">Assigned To</th>
                                    <th class="px-6 py-4 w-32">Status</th>
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
                                    v-for="ticket in tickets" 
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
                                            <div class="flex items-center gap-x-2.5 mt-1.5 flex-wrap gap-y-1">
                                                <div class="flex items-center gap-1">
                                                    <component 
                                                        :is="getPriorityIcon(ticket.priority)" 
                                                        class="h-3.5 w-3.5" 
                                                        :class="getPriorityColor(ticket.priority)"
                                                    />
                                                    <span :class="['text-[10px] font-bold uppercase tracking-wide', getPriorityColor(ticket.priority)]">
                                                        {{ ticket.priority }}
                                                    </span>
                                                </div>
                                                <span class="text-[10px] text-muted-foreground opacity-30">•</span>
                                                <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-tight">{{ ticket.category }}</span>
                                                <span class="text-[10px] text-muted-foreground opacity-30">•</span>
                                                <div class="flex items-center gap-1 text-[10px] text-muted-foreground">
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
                                        <Badge variant="outline" :class="['text-[10px] font-bold px-2.5 py-0.5 h-6 border shadow-sm transition-all group-hover:shadow-md', getStatusColor(ticket.status)]">
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
                <div class="px-6 py-4 border-t border-border/50 bg-muted/10 text-xs text-muted-foreground">
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
