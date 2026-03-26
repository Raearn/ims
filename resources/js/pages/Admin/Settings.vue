<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDown,
    ArrowUp,
    Check,
    CheckCircle2,
    GripVertical,
    HelpCircle,
    Loader2,
    Plus,
    Search,
    Settings,
    Trash2,
} from 'lucide-vue-next';
import {
    ensureLucideIconsLoaded,
    lucideAllIconMap,
    lucideIconsLoading,
    lucideStaticIconMap,
    resolveLucideIcon,
} from '@/composables/useLucideIconRegistry';
import { computed, nextTick, onUnmounted, reactive, ref } from 'vue';

// ── Types ─────────────────────────────────────────────────────────────────
interface SettingMeta {
    key: string;
    value: unknown;
    type: 'string' | 'boolean' | 'json' | 'integer';
}

interface SettingsGroup {
    [key: string]: SettingMeta;
}

interface CategoryRow {
    id?: number;
    name: string;
    icon: string;
}

interface PriorityRow {
    id?: number;
    name: string;
    icon: string;
    color: string;
}

interface StatusRow {
    id?: number;
    name: string;
}

interface Props {
    settings: {
        general?: SettingsGroup;
        tickets?: SettingsGroup;
        appearance?: SettingsGroup;
    };
    categories: CategoryRow[];
    priorities: PriorityRow[];
    statuses: StatusRow[];
}

const iconOptions = Object.keys(lucideStaticIconMap);

const props = defineProps<Props>();
const page = usePage<SharedData>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Settings', href: route('admin.settings') },
];

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

function rawVal(group: SettingsGroup | undefined, key: string, fallback: unknown = null): unknown {
    return group?.[key]?.value ?? fallback;
}

// ── Reactive lists (initialised from server props) ─────────────────────────
const categories = reactive<CategoryRow[]>(props.categories.map((c) => ({ ...c })));
const priorities = reactive<PriorityRow[]>(props.priorities.map((p) => ({ ...p })));
const statuses = reactive<StatusRow[]>(props.statuses.map((s) => ({ ...s })));

// ── Category helpers ───────────────────────────────────────────────────────
function addCategory(): void { categories.push({ name: '', icon: 'Network' }); }
function removeCategory(idx: number): void { categories.splice(idx, 1); }
function moveCategoryUp(idx: number): void {
    if (idx === 0) { return; }
    [categories[idx - 1], categories[idx]] = [categories[idx], categories[idx - 1]];
}
function moveCategoryDown(idx: number): void {
    if (idx === categories.length - 1) { return; }
    [categories[idx], categories[idx + 1]] = [categories[idx + 1], categories[idx]];
}

// ── Priority helpers ───────────────────────────────────────────────────────
function addPriority(): void { priorities.push({ name: '', icon: 'AlertCircle', color: '#6b7280' }); }
function removePriority(idx: number): void { priorities.splice(idx, 1); }
function movePriorityUp(idx: number): void {
    if (idx === 0) { return; }
    [priorities[idx - 1], priorities[idx]] = [priorities[idx], priorities[idx - 1]];
}
function movePriorityDown(idx: number): void {
    if (idx === priorities.length - 1) { return; }
    [priorities[idx], priorities[idx + 1]] = [priorities[idx + 1], priorities[idx]];
}

// ── Status helpers ─────────────────────────────────────────────────────────
function addStatus(): void { statuses.push({ name: '' }); }
function removeStatus(idx: number): void { statuses.splice(idx, 1); }
function moveStatusUp(idx: number): void {
    if (idx === 0) { return; }
    [statuses[idx - 1], statuses[idx]] = [statuses[idx], statuses[idx - 1]];
}
function moveStatusDown(idx: number): void {
    if (idx === statuses.length - 1) { return; }
    [statuses[idx], statuses[idx + 1]] = [statuses[idx + 1], statuses[idx]];
}

// ── Icon picker (teleported to body, lazy-loaded Lucide module) ───────────
interface PickerState {
    type: 'cat' | 'pri';
    idx: number;
    x: number;
    y: number;
}

const activePicker = ref<PickerState | null>(null);
const iconSearch = ref('');
const searchInputRef = ref<InstanceType<typeof Input> | null>(null);

function resolveIcon(name: string) {
    return resolveLucideIcon(name, HelpCircle);
}

const filteredIconNames = computed((): string[] => {
    const q = iconSearch.value.toLowerCase().trim();
    const names = Object.keys(lucideAllIconMap.value).sort();
    if (! q) { return names.slice(0, 25); }
    return names.filter((n) => n.toLowerCase().includes(q)).slice(0, 100);
});
async function openPicker(e: MouseEvent, type: 'cat' | 'pri', idx: number): Promise<void> {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const pickerWidth = 272;
    const pickerEstHeight = 360;

    let x = rect.left;
    let y = rect.bottom + 6;

    // Prevent horizontal overflow
    if (x + pickerWidth > window.innerWidth - 8) {
        x = window.innerWidth - pickerWidth - 8;
    }
    // Prefer below; flip above if not enough room
    if (y + pickerEstHeight > window.innerHeight - 8) {
        y = rect.top - pickerEstHeight - 6;
    }

    iconSearch.value = '';
    activePicker.value = { type, idx, x, y };

    await ensureLucideIconsLoaded();
    await nextTick();
    (searchInputRef.value as HTMLInputElement | null)?.focus?.();
}

function closePicker(): void {
    activePicker.value = null;
    iconSearch.value = '';
}

function selectIcon(icon: string): void {
    if (! activePicker.value) { return; }
    if (activePicker.value.type === 'cat') {
        categories[activePicker.value.idx].icon = icon;
    } else {
        priorities[activePicker.value.idx].icon = icon;
    }
    closePicker();
}

function currentPickerIcon(): string {
    if (! activePicker.value) { return ''; }
    return activePicker.value.type === 'cat'
        ? (categories[activePicker.value.idx]?.icon ?? '')
        : (priorities[activePicker.value.idx]?.icon ?? '');
}

// Close picker on Escape key or scroll
function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape') { closePicker(); }
}
function onScroll(): void {
    closePicker();
}
window.addEventListener('keydown', onKeydown);
window.addEventListener('scroll', onScroll, true);
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', onScroll, true);
});

// ── Forms ─────────────────────────────────────────────────────────────────
const generalForm = useForm({
    settings: {
        app_name: rawVal(props.settings.general, 'app_name', '') as string,
        app_description: rawVal(props.settings.general, 'app_description', '') as string,
        contact_email: rawVal(props.settings.general, 'contact_email', '') as string,
        maintenance_mode: rawVal(props.settings.general, 'maintenance_mode', false) as boolean,
        allow_registration: rawVal(props.settings.general, 'allow_registration', true) as boolean,
    },
});

const categoriesForm = useForm({ categories: categories as CategoryRow[] });
const prioritiesForm = useForm({ priorities: priorities as PriorityRow[] });
const statusesForm = useForm({ statuses: statuses as StatusRow[] });

const ticketDefaultsForm = useForm({
    settings: {
        auto_close_resolved_after_days: rawVal(props.settings.tickets, 'auto_close_resolved_after_days', 7) as number,
    },
});

const appearanceForm = useForm({
    settings: {
        default_theme: rawVal(props.settings.appearance, 'default_theme', 'system') as string,
        sidebar_collapsed_default: rawVal(props.settings.appearance, 'sidebar_collapsed_default', false) as boolean,
    },
});

// ── Submit handlers ───────────────────────────────────────────────────────
function submitGeneral(): void {
    generalForm.put(route('admin.settings.update'));
}

function submitCategories(): void {
    categoriesForm.categories = [...categories] as CategoryRow[];
    categoriesForm.put(route('admin.ticket-categories.update'));
}

function submitPriorities(): void {
    prioritiesForm.priorities = [...priorities] as PriorityRow[];
    prioritiesForm.put(route('admin.ticket-priorities.update'));
}

function submitStatuses(): void {
    statusesForm.statuses = [...statuses] as StatusRow[];
    statusesForm.put(route('admin.ticket-statuses.update'));
}

function submitTicketDefaults(): void {
    ticketDefaultsForm.put(route('admin.settings.update'));
}

function submitAppearance(): void {
    appearanceForm.put(route('admin.settings.update'));
}

// ── Priority name choices (for the defaults dropdown) ─────────────────────
const priorityChoices = computed(() => priorities.map((p) => p.name).filter(Boolean));
</script>

<template>
    <Head title="Admin Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 pb-20 md:p-6">

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
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground">
                    <Settings class="h-6 w-6 text-primary" />
                    Settings
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage global application configuration, ticket options, and UI preferences.
                </p>
            </div>

            <!-- Tabs -->
            <Tabs default-value="general">
                <TabsList class="mb-4 grid w-full grid-cols-3">
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="tickets">Ticket Config</TabsTrigger>
                    <TabsTrigger value="appearance">Appearance</TabsTrigger>
                </TabsList>

                <!-- ══ GENERAL TAB ═══════════════════════════════════════ -->
                <TabsContent value="general">
                    <form @submit.prevent="submitGeneral">
                        <Card>
                            <CardHeader>
                                <CardTitle>General Settings</CardTitle>
                                <CardDescription>Basic application identity and access configuration.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">

                                <div class="grid gap-2">
                                    <Label for="app_name">Application Name</Label>
                                    <Input id="app_name" v-model="generalForm.settings.app_name" placeholder="My Application" />
                                    <p v-if="generalForm.errors['settings.app_name']" class="text-xs text-destructive">
                                        {{ generalForm.errors['settings.app_name'] }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="app_description">Description</Label>
                                    <Input id="app_description" v-model="generalForm.settings.app_description" placeholder="Short description of this application" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="contact_email">Contact Email</Label>
                                    <Input id="contact_email" v-model="generalForm.settings.contact_email" type="email" placeholder="support@example.com" />
                                    <p v-if="generalForm.errors['settings.contact_email']" class="text-xs text-destructive">
                                        {{ generalForm.errors['settings.contact_email'] }}
                                    </p>
                                </div>

                                <Separator />

                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <Checkbox
                                            id="maintenance_mode"
                                            :checked="generalForm.settings.maintenance_mode"
                                            @update:checked="(v) => (generalForm.settings.maintenance_mode = v)"
                                        />
                                        <div>
                                            <Label for="maintenance_mode" class="cursor-pointer font-medium">Maintenance Mode</Label>
                                            <p class="text-xs text-muted-foreground">Temporarily disable access for non-admin users.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Checkbox
                                            id="allow_registration"
                                            :checked="generalForm.settings.allow_registration"
                                            @update:checked="(v) => (generalForm.settings.allow_registration = v)"
                                        />
                                        <div>
                                            <Label for="allow_registration" class="cursor-pointer font-medium">Allow Self-Registration</Label>
                                            <p class="text-xs text-muted-foreground">Allow new users to create their own accounts.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-2">
                                    <Button type="submit" :disabled="generalForm.processing">
                                        <Check class="mr-2 h-4 w-4" />
                                        {{ generalForm.processing ? 'Saving…' : 'Save General Settings' }}
                                    </Button>
                                </div>

                            </CardContent>
                        </Card>
                    </form>
                </TabsContent>

                <!-- ══ TICKETS TAB ════════════════════════════════════════ -->
                <TabsContent value="tickets">
                    <div class="space-y-6">

                        <!-- Categories -->
                        <form @submit.prevent="submitCategories">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ticket Categories</CardTitle>
                                    <CardDescription>Categories available when creating a ticket. Each entry can have a Lucide icon.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <div
                                        v-for="(cat, idx) in categories"
                                        :key="idx"
                                        class="group flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 transition-colors hover:bg-muted/40"
                                    >
                                        <GripVertical class="h-4 w-4 shrink-0 cursor-grab text-muted-foreground/40 group-hover:text-muted-foreground" />

                                        <!-- Icon trigger -->
                                        <button
                                            type="button"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-input bg-background text-foreground shadow-sm transition-all hover:border-primary/50 hover:ring-2 hover:ring-primary/20 focus:outline-none"
                                            :class="activePicker?.type === 'cat' && activePicker.idx === idx ? 'border-primary ring-2 ring-primary/20' : ''"
                                            :title="`Change icon (${cat.icon})`"
                                            @click="openPicker($event, 'cat', idx)"
                                        >
                                            <component :is="resolveIcon(cat.icon)" class="h-4 w-4" />
                                        </button>

                                        <Input v-model="cat.name" class="flex-1 border-0 bg-transparent shadow-none focus-visible:ring-0 focus-visible:ring-offset-0 px-0 font-medium" placeholder="Category name" />

                                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-30" :disabled="idx === 0" @click="moveCategoryUp(idx)">
                                                <ArrowUp class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-30" :disabled="idx === categories.length - 1" @click="moveCategoryDown(idx)">
                                                <ArrowDown class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive" @click="removeCategory(idx)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-1">
                                        <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5 text-xs" @click="addCategory">
                                            <Plus class="h-3.5 w-3.5" /> Add Category
                                        </Button>
                                        <Button type="submit" size="sm" :disabled="categoriesForm.processing">
                                            <Check class="mr-1.5 h-3.5 w-3.5" />
                                            {{ categoriesForm.processing ? 'Saving…' : 'Save Categories' }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>

                        <!-- Priorities -->
                        <form @submit.prevent="submitPriorities">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ticket Priorities</CardTitle>
                                    <CardDescription>Priority levels for tickets. Each has a Lucide icon and a badge colour.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <div
                                        v-for="(pri, idx) in priorities"
                                        :key="idx"
                                        class="group flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 transition-colors hover:bg-muted/40"
                                    >
                                        <GripVertical class="h-4 w-4 shrink-0 cursor-grab text-muted-foreground/40 group-hover:text-muted-foreground" />

                                        <!-- Icon trigger -->
                                        <button
                                            type="button"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-input bg-background shadow-sm transition-all hover:border-primary/50 hover:ring-2 hover:ring-primary/20 focus:outline-none"
                                            :class="activePicker?.type === 'pri' && activePicker.idx === idx ? 'border-primary ring-2 ring-primary/20' : ''"
                                            :style="{ color: pri.color }"
                                            :title="`Change icon (${pri.icon})`"
                                            @click="openPicker($event, 'pri', idx)"
                                        >
                                            <component :is="resolveIcon(pri.icon)" class="h-4 w-4" />
                                        </button>

                                        <Input v-model="pri.name" class="flex-1 border-0 bg-transparent shadow-none focus-visible:ring-0 focus-visible:ring-offset-0 px-0 font-medium" placeholder="Priority name" />

                                        <!-- Color swatch -->
                                        <label class="relative flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md border border-input bg-background shadow-sm transition-all hover:border-primary/50 hover:ring-2 hover:ring-primary/20" :title="pri.color">
                                            <span class="h-4 w-4 rounded-full border border-border/50 shadow-sm" :style="{ backgroundColor: pri.color }" />
                                            <input v-model="pri.color" type="color" class="sr-only" />
                                        </label>

                                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-30" :disabled="idx === 0" @click="movePriorityUp(idx)">
                                                <ArrowUp class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-30" :disabled="idx === priorities.length - 1" @click="movePriorityDown(idx)">
                                                <ArrowDown class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive" @click="removePriority(idx)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-1">
                                        <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5 text-xs" @click="addPriority">
                                            <Plus class="h-3.5 w-3.5" /> Add Priority
                                        </Button>
                                        <Button type="submit" size="sm" :disabled="prioritiesForm.processing">
                                            <Check class="mr-1.5 h-3.5 w-3.5" />
                                            {{ prioritiesForm.processing ? 'Saving…' : 'Save Priorities' }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>

                        <!-- Statuses -->
                        <form @submit.prevent="submitStatuses">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ticket Statuses</CardTitle>
                                    <CardDescription>Define the status workflow for tickets.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div v-for="(status, idx) in statuses" :key="idx" class="flex items-center gap-2">
                                        <GripVertical class="h-4 w-4 shrink-0 cursor-grab text-muted-foreground" />
                                        <Input v-model="statuses[idx].name" class="flex-1" placeholder="Status label" />
                                        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent disabled:opacity-40" :disabled="idx === 0" @click="moveStatusUp(idx)">
                                            <ArrowUp class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent disabled:opacity-40" :disabled="idx === statuses.length - 1" @click="moveStatusDown(idx)">
                                            <ArrowDown class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" @click="removeStatus(idx)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <Button type="button" variant="outline" size="sm" class="mt-1" @click="addStatus">
                                        <Plus class="mr-1 h-4 w-4" /> Add Status
                                    </Button>

                                    <div class="flex justify-end pt-2">
                                        <Button type="submit" :disabled="statusesForm.processing">
                                            <Check class="mr-2 h-4 w-4" />
                                            {{ statusesForm.processing ? 'Saving…' : 'Save Statuses' }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>

                        <!-- Ticket Defaults -->
                        <form @submit.prevent="submitTicketDefaults">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ticket Defaults</CardTitle>
                                    <CardDescription>Default values applied when creating new tickets.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid gap-2">
                                        <Label for="auto_close_days">Auto-Close Resolved Tickets After (days)</Label>
                                        <Input
                                            id="auto_close_days"
                                            v-model.number="ticketDefaultsForm.settings.auto_close_resolved_after_days"
                                            type="number"
                                            min="0"
                                            class="w-32"
                                        />
                                        <p class="text-xs text-muted-foreground">Set to 0 to disable auto-closing.</p>
                                    </div>
                                    <div class="flex justify-end pt-2">
                                        <Button type="submit" :disabled="ticketDefaultsForm.processing">
                                            <Check class="mr-2 h-4 w-4" />
                                            {{ ticketDefaultsForm.processing ? 'Saving…' : 'Save Defaults' }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>

                    </div>
                </TabsContent>

                <!-- ══ APPEARANCE TAB ══════════════════════════════════════ -->
                <TabsContent value="appearance">
                    <form @submit.prevent="submitAppearance">
                        <Card>
                            <CardHeader>
                                <CardTitle>Appearance</CardTitle>
                                <CardDescription>Default UI preferences for the application.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">

                                <div class="grid gap-2">
                                    <Label for="default_theme">Default Theme</Label>
                                    <Select
                                        :model-value="appearanceForm.settings.default_theme"
                                        @update:model-value="(v) => (appearanceForm.settings.default_theme = v)"
                                    >
                                        <SelectTrigger id="default_theme" class="w-48">
                                            <SelectValue placeholder="Select theme" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="system">System</SelectItem>
                                            <SelectItem value="light">Light</SelectItem>
                                            <SelectItem value="dark">Dark</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-muted-foreground">The theme applied on first visit for new users.</p>
                                </div>

                                <Separator />

                                <div class="flex items-center gap-3">
                                    <Checkbox
                                        id="sidebar_collapsed_default"
                                        :checked="appearanceForm.settings.sidebar_collapsed_default"
                                        @update:checked="(v) => (appearanceForm.settings.sidebar_collapsed_default = v)"
                                    />
                                    <div>
                                        <Label for="sidebar_collapsed_default" class="cursor-pointer font-medium">
                                            Collapse Sidebar by Default
                                        </Label>
                                        <p class="text-xs text-muted-foreground">New users will see the sidebar in collapsed/icon-only mode.</p>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-2">
                                    <Button type="submit" :disabled="appearanceForm.processing">
                                        <Check class="mr-2 h-4 w-4" />
                                        {{ appearanceForm.processing ? 'Saving…' : 'Save Appearance Settings' }}
                                    </Button>
                                </div>

                            </CardContent>
                        </Card>
                    </form>
                </TabsContent>

            </Tabs>
        </div>
    </AppLayout>

    <!-- ── Teleported icon picker ──────────────────────────────────────── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="scale-95 opacity-0"
            leave-active-class="transition duration-75 ease-in"
            leave-to-class="scale-95 opacity-0"
        >
            <div v-if="activePicker" class="fixed inset-0 z-50" @click="closePicker">
                <div
                    class="absolute flex flex-col overflow-hidden rounded-xl border border-border bg-popover shadow-2xl shadow-black/20"
                    :style="{ top: activePicker.y + 'px', left: activePicker.x + 'px', width: '272px' }"
                    @click.stop
                >
                    <!-- Search bar -->
                    <div class="flex items-center gap-2 border-b border-border px-3 py-2.5">
                        <Search class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        <input
                            ref="searchInputRef"
                            v-model="iconSearch"
                            type="text"
                            placeholder="Search icons…"
                            class="flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground"
                        />
                        <span class="text-[10px] tabular-nums text-muted-foreground/60">
                            {{ filteredIconNames.length }}{{ ! iconSearch ? '+' : '' }}
                        </span>
                    </div>

                    <!-- Icon grid -->
                    <div class="h-60 overflow-y-auto p-2">
                        <div class="grid grid-cols-5 gap-1">
                            <button
                                v-for="iconName in filteredIconNames"
                                :key="iconName"
                                type="button"
                                class="flex flex-col items-center gap-1 rounded-lg p-1.5 transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                                :class="currentPickerIcon() === iconName
                                    ? 'bg-primary/10 text-primary ring-1 ring-primary/40'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                                :title="iconName"
                                @click="selectIcon(iconName)"
                            >
                                <component :is="lucideAllIconMap[iconName]" class="h-4 w-4 shrink-0" />
                                <span class="w-full truncate text-center text-[8px] leading-tight">{{ iconName }}</span>
                            </button>

                            <div v-if="filteredIconNames.length === 0" class="col-span-5 py-8 text-center text-xs text-muted-foreground">
                                No icons match "{{ iconSearch }}"
                            </div>
                        </div>
                    </div>

                    <!-- Footer hint -->
                    <div class="flex items-center justify-between border-t border-border px-3 py-1.5">
                        <span class="text-[10px] text-muted-foreground/60">
                            <template v-if="lucideIconsLoading">Loading all icons…</template>
                            <template v-else-if="! iconSearch">Showing first 25 · type to search all {{ Object.keys(lucideAllIconMap).length }} icons</template>
                        </span>
                        <Loader2 v-if="lucideIconsLoading" class="h-3 w-3 animate-spin text-muted-foreground/50" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
