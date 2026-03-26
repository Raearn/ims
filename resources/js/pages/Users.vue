<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, ArrowUpCircle, Ban, CheckCircle2, ChevronDown, ChevronUp, ChevronsUpDown, Circle, Clock, Crown, Headset, Pause, Pencil, Play, Plus, Search, ShieldCheck, Trash2, UserRound, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TicketSummary {
    id: number;
    tktId: string;
    title: string;
    status: string;
    priority: string;
    category: string;
    createdAt: string;
}

interface UserItem {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'supervisor' | 'technical';
    createdAt: string;
    ticketsReported: number;
    ticketsHandled: number;
    reportedTickets: TicketSummary[];
    handledTickets: TicketSummary[];
}

const props = defineProps<{
    users: UserItem[];
    currentUserId: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('users') },
];

// ── Stats ──────────────────────────────────────────────────────────────────
const totalCount      = computed(() => props.users.length);
const adminCount      = computed(() => props.users.filter(u => u.role === 'admin').length);
const supervisorCount = computed(() => props.users.filter(u => u.role === 'supervisor').length);
const technicalCount  = computed(() => props.users.filter(u => u.role === 'technical').length);

// ── Filter / search / sort ─────────────────────────────────────────────────
const search     = ref('');
const activeRole = ref<'all' | 'admin' | 'supervisor' | 'technical'>('all');

type SortKey = 'name' | 'email' | 'role' | 'ticketsReported' | 'ticketsHandled' | 'createdAt';
const sortKey = ref<SortKey | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');

const roleOrder: Record<string, number> = { admin: 1, supervisor: 2, technical: 3 };

const filteredUsers = computed(() => {
    let list = props.users;
    if (activeRole.value !== 'all') {
        list = list.filter(u => u.role === activeRole.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(u =>
            u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q),
        );
    }
    if (!sortKey.value) { return list; }
    return [...list].sort((a, b) => {
        let aVal: any = a[sortKey.value!];
        let bVal: any = b[sortKey.value!];
        if (sortKey.value === 'role') { aVal = roleOrder[aVal] ?? 99; bVal = roleOrder[bVal] ?? 99; }
        if (aVal < bVal) { return sortDir.value === 'asc' ? -1 : 1; }
        if (aVal > bVal) { return sortDir.value === 'asc' ? 1 : -1; }
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

// ── Helpers ────────────────────────────────────────────────────────────────
const getInitials = (name: string) =>
    name.split(' ').map((n: string) => n[0]).join('').substring(0, 2).toUpperCase();

const getRoleBadgeClass = (role: string) => {
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

const ROLES = [
    { value: 'admin',      label: 'Admin',      badgeClass: 'bg-rose-500/15 text-rose-500 border border-rose-500/30' },
    { value: 'supervisor', label: 'Supervisor',  badgeClass: 'bg-amber-500/15 text-amber-500 border border-amber-500/30' },
    { value: 'technical',  label: 'Technical',   badgeClass: 'bg-blue-500/15 text-blue-500 border border-blue-500/30' },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Open':        return 'bg-rose-500/15 text-rose-500 border-rose-500/30';
        case 'In Progress': return 'bg-blue-500/15 text-blue-500 border-blue-500/30';
        case 'On Hold':     return 'bg-amber-500/15 text-amber-500 border-amber-500/30';
        case 'Resolved':    return 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30';
        case 'Closed':      return 'bg-slate-500/15 text-slate-500 border-slate-500/30';
        default:            return 'bg-secondary text-secondary-foreground';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'Open':        return AlertTriangle;
        case 'In Progress': return Play;
        case 'On Hold':     return Pause;
        case 'Resolved':    return CheckCircle2;
        case 'Closed':      return Ban;
        default:            return Circle;
    }
};

const getPriorityBadge = (priority: string) => {
    switch (priority) {
        case 'Critical': return 'bg-rose-500/15 text-rose-500 border border-rose-500/25';
        case 'High':     return 'bg-orange-500/15 text-orange-500 border border-orange-500/25';
        case 'Medium':   return 'bg-blue-500/15 text-blue-500 border border-blue-500/25';
        case 'Low':      return 'bg-slate-500/10 text-slate-500 border border-slate-500/20';
        default:         return 'bg-muted text-muted-foreground border border-border';
    }
};

const getPriorityIcon = (priority: string) => {
    switch (priority) {
        case 'Critical': return AlertCircle;
        case 'High':     return AlertTriangle;
        case 'Medium':   return ArrowUpCircle;
        default:         return Circle;
    }
};

// ── User detail modal ──────────────────────────────────────────────────────
const isDetailModalOpen = ref(false);
const detailUser        = ref<UserItem | null>(null);
const detailTab         = ref<'reported' | 'handled'>('reported');

const openDetailModal = (user: UserItem) => {
    detailUser.value        = user;
    detailTab.value         = 'reported';
    isDetailModalOpen.value = true;
};

watch(isDetailModalOpen, (val) => {
    if (!val) { detailUser.value = null; }
});

// ── Create modal ───────────────────────────────────────────────────────────
const isCreateModalOpen = ref(false);

const createForm = useForm({
    name: '',
    email: '',
    role: 'technical' as 'admin' | 'supervisor' | 'technical',
    password: '',
    password_confirmation: '',
});

const openCreateModal = () => {
    createForm.reset();
    isCreateModalOpen.value = true;
};

const submitCreate = () => {
    createForm.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            createForm.reset();
        },
    });
};

watch(isCreateModalOpen, (val) => {
    if (!val) { createForm.reset(); }
});

// ── Edit modal ─────────────────────────────────────────────────────────────
const isEditModalOpen = ref(false);
const editingUser     = ref<UserItem | null>(null);

const editForm = useForm({
    name: '',
    email: '',
    role: 'technical' as 'admin' | 'supervisor' | 'technical',
    password: '',
    password_confirmation: '',
});

const openEditModal = (user: UserItem) => {
    editingUser.value              = user;
    editForm.name                  = user.name;
    editForm.email                 = user.email;
    editForm.role                  = user.role;
    editForm.password              = '';
    editForm.password_confirmation = '';
    isEditModalOpen.value          = true;
};

const submitEdit = () => {
    if (!editingUser.value) { return; }
    editForm.patch(route('users.update', { user: editingUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            editingUser.value     = null;
        },
    });
};

watch(isEditModalOpen, (val) => {
    if (!val) {
        editingUser.value = null;
        editForm.reset();
    }
});

// ── Delete modal ───────────────────────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const deletingUser      = ref<UserItem | null>(null);
const isDeleting        = ref(false);

const openDeleteModal = (user: UserItem) => {
    deletingUser.value      = user;
    isDeleteModalOpen.value = true;
};

const submitDelete = () => {
    if (!deletingUser.value) { return; }
    isDeleting.value = true;
    router.delete(route('users.destroy', { user: deletingUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            deletingUser.value      = null;
        },
        onFinish: () => { isDeleting.value = false; },
    });
};

watch(isDeleteModalOpen, (val) => {
    if (!val) { deletingUser.value = null; }
});
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">

            <!-- Page header -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Users class="h-6 w-6 text-primary" />
                        Users
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">Manage system accounts and roles</p>
                </div>
                <Button @click="openCreateModal" class="gap-1.5 text-xs font-bold shadow-sm">
                    <Plus class="h-4 w-4" />
                    Create User
                </Button>
            </div>

            <!-- Stat cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card class="shadow-none border border-border/50">
                    <CardContent class="px-5 pb-4 pt-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Total Users</p>
                                <p class="mt-1 text-2xl font-bold">{{ totalCount }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                                <Users class="h-5 w-5 text-primary" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card class="shadow-none border border-border/50">
                    <CardContent class="px-5 pb-4 pt-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Admins</p>
                                <p class="mt-1 text-2xl font-bold text-rose-500">{{ adminCount }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10">
                                <Crown class="h-5 w-5 text-rose-500" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card class="shadow-none border border-border/50">
                    <CardContent class="px-5 pb-4 pt-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Supervisors</p>
                                <p class="mt-1 text-2xl font-bold text-amber-500">{{ supervisorCount }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10">
                                <ShieldCheck class="h-5 w-5 text-amber-500" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card class="shadow-none border border-border/50">
                    <CardContent class="px-5 pb-4 pt-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Technical</p>
                                <p class="mt-1 text-2xl font-bold text-blue-500">{{ technicalCount }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10">
                                <Headset class="h-5 w-5 text-blue-500" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Table card -->
            <Card class="flex flex-col overflow-hidden shadow-none border border-border/50">
                <!-- Toolbar -->
                <div class="flex flex-col gap-3 border-b border-border/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <!-- Search -->
                    <div class="relative w-full max-w-xs">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name or email…"
                            class="w-full rounded-lg border border-input bg-background py-2 pl-9 pr-8 text-sm shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <button
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <!-- Role filter tabs -->
                    <div class="flex shrink-0 items-center gap-1 rounded-lg border border-border/50 bg-muted/60 p-1">
                        <button
                            v-for="tab in [{ value: 'all', label: 'All' }, ...ROLES]"
                            :key="tab.value"
                            type="button"
                            @click="activeRole = tab.value as typeof activeRole"
                            :class="[
                                'rounded-md px-3 py-1 text-xs font-semibold transition-all',
                                activeRole === tab.value
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                        >{{ tab.label }}</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/20 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                <th class="px-5 py-3.5 text-left">
                                    <button @click="toggleSort('name')" class="inline-flex items-center gap-1 transition-colors hover:text-foreground">
                                        User
                                        <ChevronUp v-if="sortKey === 'name' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'name' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                        <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3.5 text-left sm:table-cell">
                                    <button @click="toggleSort('email')" class="inline-flex items-center gap-1 transition-colors hover:text-foreground">
                                        Email
                                        <ChevronUp v-if="sortKey === 'email' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'email' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                        <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="px-4 py-3.5 text-left">
                                    <button @click="toggleSort('role')" class="inline-flex items-center gap-1 transition-colors hover:text-foreground">
                                        Role
                                        <ChevronUp v-if="sortKey === 'role' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'role' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                        <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3.5 text-left lg:table-cell">
                                    <button @click="toggleSort('ticketsReported')" class="inline-flex items-center gap-1 transition-colors hover:text-foreground">
                                        Reported
                                        <ChevronUp v-if="sortKey === 'ticketsReported' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'ticketsReported' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                        <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3.5 text-left lg:table-cell">
                                    <button @click="toggleSort('ticketsHandled')" class="inline-flex items-center gap-1 transition-colors hover:text-foreground">
                                        Handled
                                        <ChevronUp v-if="sortKey === 'ticketsHandled' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'ticketsHandled' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                        <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3.5 text-left md:table-cell">
                                    <button @click="toggleSort('createdAt')" class="inline-flex items-center gap-1 transition-colors hover:text-foreground">
                                        Member Since
                                        <ChevronUp v-if="sortKey === 'createdAt' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'createdAt' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                        <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="px-5 py-3.5 text-right">
                                    <span class="font-normal normal-case tracking-normal text-muted-foreground/40">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="user in filteredUsers"
                                :key="user.id"
                                class="group cursor-pointer transition-colors hover:bg-muted/30"
                                @click="openDetailModal(user)"
                            >
                                <!-- Name + avatar -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            :class="[
                                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold',
                                                user.id === currentUserId
                                                    ? 'border-primary/40 bg-primary/20 text-primary'
                                                    : 'border-border/50 bg-muted text-foreground',
                                            ]"
                                        >{{ getInitials(user.name) }}</div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-foreground">{{ user.name }}</p>
                                            <p v-if="user.id === currentUserId" class="text-[10px] font-medium text-primary">You</p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Email -->
                                <td class="hidden px-4 py-3.5 text-muted-foreground sm:table-cell">{{ user.email }}</td>
                                <!-- Role -->
                                <td class="px-4 py-3.5">
                                    <Badge variant="outline" :class="['inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold', getRoleBadgeClass(user.role)]">
                                        <component :is="getRoleIcon(user.role)" class="h-3 w-3 shrink-0" />
                                        {{ user.role.charAt(0).toUpperCase() + user.role.slice(1) }}
                                    </Badge>
                                </td>
                                <!-- Tickets reported -->
                                <td class="hidden px-4 py-3.5 text-sm lg:table-cell">
                                    <span class="font-semibold text-foreground">{{ user.ticketsReported }}</span>
                                </td>
                                <!-- Tickets handled -->
                                <td class="hidden px-4 py-3.5 text-sm lg:table-cell">
                                    <span class="font-semibold text-foreground">{{ user.ticketsHandled }}</span>
                                </td>
                                <!-- Member since -->
                                <td class="hidden px-4 py-3.5 text-xs text-muted-foreground md:table-cell">{{ user.createdAt }}</td>
                                <!-- Actions -->
                                <td class="px-5 py-3.5 text-right" @click.stop="$event.stopPropagation()">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openEditModal(user)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground/0 transition-all duration-150 hover:bg-blue-500/10 group-hover:text-blue-500"
                                            title="Edit User"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            @click="openDeleteModal(user)"
                                            :disabled="user.id === currentUserId"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground/0 transition-all duration-150 hover:bg-rose-500/10 group-hover:text-rose-500 disabled:pointer-events-none disabled:opacity-30"
                                            title="Delete User"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty state -->
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="7" class="px-5 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted/50">
                                            <Users class="h-6 w-6 text-muted-foreground/40" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">No users found</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">Try adjusting your search or filter.</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table footer -->
                <div class="flex items-center justify-between border-t border-border/50 px-5 py-3">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-semibold text-foreground">{{ filteredUsers.length }}</span>
                        of <span class="font-semibold text-foreground">{{ totalCount }}</span> users
                    </p>
                </div>
            </Card>
        </div>

        <!-- ── Create User Modal ──────────────────────────────────────────── -->
        <Dialog v-model:open="isCreateModalOpen">
            <DialogContent class="flex max-h-[90dvh] flex-col overflow-hidden border-none p-0 shadow-2xl sm:max-w-[440px]">
                <div class="border-b border-primary/10 bg-primary/5 px-5 pb-4 pt-5">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-base font-bold tracking-tight">
                            <Plus class="h-4 w-4 text-primary" />
                            Create User
                        </DialogTitle>
                        <DialogDescription class="mt-0.5 text-xs text-muted-foreground/80">
                            Add a new system account and assign a role.
                        </DialogDescription>
                    </DialogHeader>
                </div>
                <div class="modal-body flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Name <span class="text-destructive">*</span></Label>
                        <Input v-model="createForm.name" placeholder="Full name" />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Email <span class="text-destructive">*</span></Label>
                        <Input v-model="createForm.email" type="email" placeholder="user@example.com" />
                        <p v-if="createForm.errors.email" class="text-xs text-destructive">{{ createForm.errors.email }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Role <span class="text-destructive">*</span></Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="r in ROLES"
                                :key="r.value"
                                type="button"
                                @click="createForm.role = r.value as typeof createForm.role"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-1.5 text-[11px] font-bold transition-all',
                                    createForm.role === r.value
                                        ? [r.badgeClass, 'border-current shadow-sm scale-[1.03]']
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50',
                                ]"
                            >
                                <component :is="getRoleIcon(r.value)" class="h-3 w-3" />
                                {{ r.label }}
                            </button>
                        </div>
                        <p v-if="createForm.errors.role" class="text-xs text-destructive">{{ createForm.errors.role }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Password <span class="text-destructive">*</span></Label>
                        <Input v-model="createForm.password" type="password" placeholder="Min. 8 characters" />
                        <p v-if="createForm.errors.password" class="text-xs text-destructive">{{ createForm.errors.password }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Confirm Password <span class="text-destructive">*</span></Label>
                        <Input v-model="createForm.password_confirmation" type="password" placeholder="Repeat password" />
                    </div>
                </div>
                <DialogFooter class="border-t border-border/50 bg-muted/20 px-5 py-4">
                    <Button type="button" variant="outline" @click="isCreateModalOpen = false" class="text-xs font-bold">Cancel</Button>
                    <Button
                        type="button"
                        :disabled="createForm.processing"
                        @click="submitCreate"
                        class="gap-1.5 text-xs font-bold shadow-sm shadow-primary/20"
                    >
                        <span v-if="!createForm.processing" class="flex items-center gap-1.5">
                            <Plus class="h-3.5 w-3.5" /> Create User
                        </span>
                        <span v-else class="flex items-center gap-1.5">
                            Creating… <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        </span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Edit User Modal ────────────────────────────────────────────── -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent v-if="editingUser" class="flex max-h-[90dvh] flex-col overflow-hidden border-none p-0 shadow-2xl sm:max-w-[440px]">
                <div class="border-b border-primary/10 bg-primary/5 px-5 pb-4 pt-5">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-base font-bold tracking-tight">
                            <Pencil class="h-4 w-4 text-primary" />
                            Edit User
                        </DialogTitle>
                        <DialogDescription class="mt-0.5 text-xs text-muted-foreground/80">
                            Update account details for <span class="font-semibold text-foreground">{{ editingUser.name }}</span>.
                        </DialogDescription>
                    </DialogHeader>
                </div>
                <div class="modal-body flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Name <span class="text-destructive">*</span></Label>
                        <Input v-model="editForm.name" placeholder="Full name" />
                        <p v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Email <span class="text-destructive">*</span></Label>
                        <Input v-model="editForm.email" type="email" placeholder="user@example.com" />
                        <p v-if="editForm.errors.email" class="text-xs text-destructive">{{ editForm.errors.email }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Role <span class="text-destructive">*</span></Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="r in ROLES"
                                :key="r.value"
                                type="button"
                                :disabled="editingUser.id === currentUserId"
                                @click="editingUser.id !== currentUserId && (editForm.role = r.value as typeof editForm.role)"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-1.5 text-[11px] font-bold transition-all',
                                    editingUser.id === currentUserId
                                        ? 'cursor-not-allowed opacity-50'
                                        : '',
                                    editForm.role === r.value
                                        ? [r.badgeClass, 'border-current shadow-sm scale-[1.03]']
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50',
                                ]"
                            >
                                <component :is="getRoleIcon(r.value)" class="h-3 w-3" />
                                {{ r.label }}
                            </button>
                        </div>
                        <p v-if="editingUser.id === currentUserId" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            You cannot change your own role.
                        </p>
                        <p v-else-if="editForm.errors.role" class="text-xs text-destructive">{{ editForm.errors.role }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            New Password
                            <span class="ml-1 font-normal normal-case text-muted-foreground/50">(optional)</span>
                        </Label>
                        <Input v-model="editForm.password" type="password" placeholder="Leave blank to keep current" />
                        <p v-if="editForm.errors.password" class="text-xs text-destructive">{{ editForm.errors.password }}</p>
                    </div>
                    <div v-if="editForm.password" class="grid gap-1.5">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Confirm New Password</Label>
                        <Input v-model="editForm.password_confirmation" type="password" placeholder="Repeat new password" />
                    </div>
                </div>
                <DialogFooter class="border-t border-border/50 bg-muted/20 px-5 py-4">
                    <Button type="button" variant="outline" @click="isEditModalOpen = false" class="text-xs font-bold">Cancel</Button>
                    <Button
                        type="button"
                        :disabled="editForm.processing"
                        @click="submitEdit"
                        class="gap-1.5 text-xs font-bold shadow-sm shadow-primary/20"
                    >
                        <span v-if="!editForm.processing" class="flex items-center gap-1.5">
                            <Pencil class="h-3.5 w-3.5" /> Save Changes
                        </span>
                        <span v-else class="flex items-center gap-1.5">
                            Saving… <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        </span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Delete Confirmation ────────────────────────────────────────── -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogContent v-if="deletingUser" class="overflow-hidden border-none p-0 shadow-2xl sm:max-w-[400px]">
                <div class="border-b border-destructive/10 bg-destructive/5 px-5 pb-4 pt-5">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-base font-bold tracking-tight text-destructive">
                            <Trash2 class="h-4 w-4" />
                            Delete User
                        </DialogTitle>
                        <DialogDescription class="mt-0.5 text-xs text-muted-foreground/80">
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm text-foreground">
                        Are you sure you want to delete
                        <span class="font-semibold">{{ deletingUser.name }}</span>?
                        Their account will be permanently removed.
                    </p>
                    <div v-if="deletingUser.id === currentUserId" class="mt-3 flex items-center gap-2 rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2.5 text-xs font-medium text-destructive">
                        You cannot delete your own account.
                    </div>
                </div>
                <DialogFooter class="border-t border-border/50 bg-muted/20 px-5 py-4">
                    <Button type="button" variant="outline" @click="isDeleteModalOpen = false" class="text-xs font-bold">Cancel</Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="isDeleting || deletingUser.id === currentUserId"
                        @click="submitDelete"
                        class="gap-1.5 text-xs font-bold"
                    >
                        <span v-if="!isDeleting" class="flex items-center gap-1.5">
                            <Trash2 class="h-3.5 w-3.5" /> Delete
                        </span>
                        <span v-else class="flex items-center gap-1.5">
                            Deleting… <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        </span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── User Detail Modal ─────────────────────────────────────────── -->
        <Dialog v-model:open="isDetailModalOpen">
            <DialogContent v-if="detailUser" class="flex max-h-[92dvh] flex-col overflow-hidden border-none p-0 shadow-2xl sm:max-w-[560px]">

                <!-- Header -->
                <div class="border-b border-border/50 bg-muted/30 px-6 pb-5 pt-6">
                    <div class="flex items-start gap-4">
                        <!-- Avatar -->
                        <div
                            :class="[
                                'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border-2 text-lg font-bold',
                                detailUser.id === currentUserId
                                    ? 'border-primary/40 bg-primary/15 text-primary'
                                    : 'border-border/60 bg-muted text-foreground',
                            ]"
                        >{{ getInitials(detailUser.name) }}</div>

                        <!-- Name / email / role -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold leading-tight text-foreground">{{ detailUser.name }}</h2>
                                <span v-if="detailUser.id === currentUserId" class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">You</span>
                            </div>
                            <p class="mt-0.5 text-sm text-muted-foreground">{{ detailUser.email }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" :class="['inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold', getRoleBadgeClass(detailUser.role)]">
                                    <component :is="getRoleIcon(detailUser.role)" class="h-3 w-3 shrink-0" />
                                    {{ detailUser.role.charAt(0).toUpperCase() + detailUser.role.slice(1) }}
                                </Badge>
                                <span class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <Clock class="h-3 w-3 opacity-60" />
                                    Member since {{ detailUser.createdAt }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Stat chips -->
                    <div class="mt-4 flex gap-3">
                        <div class="flex flex-1 flex-col items-center gap-0.5 rounded-xl border border-border/40 bg-background/60 py-2.5">
                            <span class="text-xl font-bold text-foreground">{{ detailUser.ticketsReported }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Reported</span>
                        </div>
                        <div class="flex flex-1 flex-col items-center gap-0.5 rounded-xl border border-border/40 bg-background/60 py-2.5">
                            <span class="text-xl font-bold text-foreground">{{ detailUser.ticketsHandled }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Handled</span>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex gap-0 border-b border-border/50">
                    <button
                        type="button"
                        @click="detailTab = 'reported'"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 py-3 text-xs font-bold transition-colors',
                            detailTab === 'reported'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Reported
                        <span class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-bold text-muted-foreground">{{ detailUser.reportedTickets.length }}</span>
                    </button>
                    <button
                        type="button"
                        @click="detailTab = 'handled'"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 py-3 text-xs font-bold transition-colors',
                            detailTab === 'handled'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Handled
                        <span class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-bold text-muted-foreground">{{ detailUser.handledTickets.length }}</span>
                    </button>
                </div>

                <!-- Ticket list -->
                <div class="modal-body flex-1 overflow-y-auto">
                    <template v-if="detailTab === 'reported'">
                        <div v-if="detailUser.reportedTickets.length === 0" class="flex flex-col items-center gap-2 py-14 text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-muted/50">
                                <Users class="h-5 w-5 text-muted-foreground/40" />
                            </div>
                            <p class="text-sm text-muted-foreground">No reported tickets.</p>
                        </div>
                        <div v-else class="divide-y divide-border/40">
                            <div
                                v-for="ticket in detailUser.reportedTickets"
                                :key="ticket.id"
                                class="flex items-start gap-3 px-5 py-3.5 transition-colors hover:bg-muted/30"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-semibold text-foreground leading-snug">{{ ticket.title }}</p>
                                        <span :class="['shrink-0 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase', getPriorityBadge(ticket.priority)]">
                                            <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                            {{ ticket.priority }}
                                        </span>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                        <span class="font-mono text-muted-foreground/50 uppercase tracking-wider">{{ ticket.tktId }}</span>
                                        <span class="opacity-30">·</span>
                                        <Badge variant="outline" :class="['inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold border', getStatusColor(ticket.status)]">
                                            <component :is="getStatusIcon(ticket.status)" class="h-2.5 w-2.5 shrink-0" />
                                            {{ ticket.status }}
                                        </Badge>
                                        <span class="opacity-30">·</span>
                                        <span class="inline-flex items-center gap-1">
                                            <Clock class="h-3 w-3 opacity-60" />
                                            {{ ticket.createdAt }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-if="detailTab === 'handled'">
                        <div v-if="detailUser.handledTickets.length === 0" class="flex flex-col items-center gap-2 py-14 text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-muted/50">
                                <Users class="h-5 w-5 text-muted-foreground/40" />
                            </div>
                            <p class="text-sm text-muted-foreground">No handled tickets.</p>
                        </div>
                        <div v-else class="divide-y divide-border/40">
                            <div
                                v-for="ticket in detailUser.handledTickets"
                                :key="ticket.id"
                                class="flex items-start gap-3 px-5 py-3.5 transition-colors hover:bg-muted/30"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-semibold text-foreground leading-snug">{{ ticket.title }}</p>
                                        <span :class="['shrink-0 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase', getPriorityBadge(ticket.priority)]">
                                            <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                            {{ ticket.priority }}
                                        </span>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                        <span class="font-mono text-muted-foreground/50 uppercase tracking-wider">{{ ticket.tktId }}</span>
                                        <span class="opacity-30">·</span>
                                        <Badge variant="outline" :class="['inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold border', getStatusColor(ticket.status)]">
                                            <component :is="getStatusIcon(ticket.status)" class="h-2.5 w-2.5 shrink-0" />
                                            {{ ticket.status }}
                                        </Badge>
                                        <span class="opacity-30">·</span>
                                        <span class="inline-flex items-center gap-1">
                                            <Clock class="h-3 w-3 opacity-60" />
                                            {{ ticket.createdAt }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <DialogFooter class="border-t border-border/50 bg-muted/20 px-5 py-3.5">
                    <div class="flex w-full items-center justify-between">
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" size="sm" @click="openEditModal(detailUser); isDetailModalOpen = false" class="gap-1.5 text-xs font-bold">
                                <Pencil class="h-3.5 w-3.5" /> Edit
                            </Button>
                            <Button
                                v-if="detailUser.id !== currentUserId"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openDeleteModal(detailUser); isDetailModalOpen = false"
                                class="gap-1.5 text-xs font-bold text-destructive hover:text-destructive"
                            >
                                <Trash2 class="h-3.5 w-3.5" /> Delete
                            </Button>
                        </div>
                        <Button type="button" variant="ghost" size="sm" @click="isDetailModalOpen = false" class="text-xs font-bold">
                            Close
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- ──────────────────────────────────────────────────────────────── -->

    </AppLayout>
</template>

<style scoped>
.modal-body {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--border)) transparent;
}
.modal-body::-webkit-scrollbar { width: 4px; }
.modal-body::-webkit-scrollbar-track { background: transparent; }
.modal-body::-webkit-scrollbar-thumb { background-color: hsl(var(--border)); border-radius: 9999px; }
.modal-body::-webkit-scrollbar-thumb:hover { background-color: hsl(var(--muted-foreground) / 0.4); }
</style>
