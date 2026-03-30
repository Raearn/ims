<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
    resolveLucideIcon,
} from '@/composables/useLucideIconRegistry';
import { TransitionGroup, computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

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
    /** Stable key for new rows before save (drag + :key). */
    clientKey?: string;
}

interface PriorityRow {
    id?: number;
    name: string;
    icon: string;
    color: string;
    clientKey?: string;
}

type StatusHandlerRequirement = 'none' | 'optional' | 'required';

interface StatusRow {
    id?: number;
    name: string;
    icon: string;
    color: string;
    /** Set from server; new rows default in UI. */
    handler_requirement?: StatusHandlerRequirement;
    clientKey?: string;
}

interface Props {
    settings: {
        general?: SettingsGroup;
        appearance?: SettingsGroup;
    };
    categories: CategoryRow[];
    priorities: PriorityRow[];
    statuses: StatusRow[];
    /** Names that cannot be removed from the list (server-enforced). Categories omit optional "Others". */
    ticketConfigProtectedNames: {
        categories: string[];
        priorities: string[];
        statuses: string[];
    };
    /** Ticket counts per persisted row id (JSON keys are strings). */
    categoryTicketCountsById?: Record<string, number>;
    priorityTicketCountsById?: Record<string, number>;
    statusTicketCountsById?: Record<string, number>;
}

type InUseKind = 'category' | 'priority' | 'status';

const props = defineProps<Props>();
const page = usePage<SharedData>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Settings', href: route('admin.settings') },
];

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const protectedCategoryNameSet = computed(() => new Set(props.ticketConfigProtectedNames.categories));
const protectedPriorityNameSet = computed(() => new Set(props.ticketConfigProtectedNames.priorities));
const protectedStatusNameSet = computed(() => new Set(props.ticketConfigProtectedNames.statuses));

function isProtectedCategoryRow(row: CategoryRow): boolean {
    const n = row.name.trim();
    return n !== '' && protectedCategoryNameSet.value.has(n);
}

function isProtectedPriorityRow(row: PriorityRow): boolean {
    const n = row.name.trim();
    return n !== '' && protectedPriorityNameSet.value.has(n);
}

function isProtectedStatusRow(row: StatusRow): boolean {
    const n = row.name.trim();
    return n !== '' && protectedStatusNameSet.value.has(n);
}

function rawVal(group: SettingsGroup | undefined, key: string, fallback: unknown = null): unknown {
    return group?.[key]?.value ?? fallback;
}

// ── Reactive lists (initialised from server props) ─────────────────────────
const categories = reactive<CategoryRow[]>(props.categories.map((c) => ({ ...c })));
const priorities = reactive<PriorityRow[]>(props.priorities.map((p) => ({ ...p })));
const statuses = reactive<StatusRow[]>(
    props.statuses.map((s) => ({
        ...s,
        handler_requirement: (s.handler_requirement ?? 'optional') as StatusHandlerRequirement,
    })),
);

// ── Category helpers ───────────────────────────────────────────────────────
function addCategory(): void {
    categories.push({ name: '', icon: 'Network', clientKey: crypto.randomUUID() });
}

function ticketCountForCategoryRow(row: CategoryRow): number {
    if (row.id == null) {
        return 0;
    }
    const map = props.categoryTicketCountsById ?? {};
    const raw = map[String(row.id)];
    const n = typeof raw === 'number' ? raw : Number(raw);
    return Number.isFinite(n) ? n : 0;
}

function requestRemoveCategory(idx: number): void {
    const row = categories[idx];
    if (! row) {
        return;
    }
    if (isProtectedCategoryRow(row)) {
        return;
    }
    const count = ticketCountForCategoryRow(row);
    if (count > 0) {
        const label = row.name.trim() || 'This category';
        inUseModalMessage.value =
            count === 1
                ? `Cannot remove "${label}" because 1 ticket still uses it. Reassign that ticket first, or delete it below.`
                : `Cannot remove "${label}" because ${count} tickets still use it. Reassign those tickets first, or delete them below.`;
        inUseModalKind.value = 'category';
        inUseModalEntityId.value = row.id ?? null;
        inUseModalTicketCount.value = count;
        pendingRemoveAfterTicketDelete.value = row.id != null ? { kind: 'category', id: row.id } : null;
        inUseModalStep.value = 'info';
        inUseModalOpen.value = true;
        return;
    }
    categories.splice(idx, 1);
}
function moveCategoryUp(idx: number): void {
    if (idx === 0) { return; }
    [categories[idx - 1], categories[idx]] = [categories[idx], categories[idx - 1]];
}
function moveCategoryDown(idx: number): void {
    if (idx === categories.length - 1) { return; }
    [categories[idx], categories[idx + 1]] = [categories[idx + 1], categories[idx]];
}

// ── Priority helpers ───────────────────────────────────────────────────────
function addPriority(): void {
    priorities.push({ name: '', icon: 'AlertCircle', color: '#6b7280', clientKey: crypto.randomUUID() });
}

function ticketCountForPriorityRow(row: PriorityRow): number {
    if (row.id == null) {
        return 0;
    }
    const map = props.priorityTicketCountsById ?? {};
    const raw = map[String(row.id)];
    const n = typeof raw === 'number' ? raw : Number(raw);
    return Number.isFinite(n) ? n : 0;
}

function requestRemovePriority(idx: number): void {
    const row = priorities[idx];
    if (! row) {
        return;
    }
    if (isProtectedPriorityRow(row)) {
        return;
    }
    const count = ticketCountForPriorityRow(row);
    if (count > 0) {
        const label = row.name.trim() || 'This priority';
        inUseModalMessage.value =
            count === 1
                ? `Cannot remove "${label}" because 1 ticket still uses it. Reassign that ticket first, or delete it below.`
                : `Cannot remove "${label}" because ${count} tickets still use it. Reassign those tickets first, or delete them below.`;
        inUseModalKind.value = 'priority';
        inUseModalEntityId.value = row.id ?? null;
        inUseModalTicketCount.value = count;
        pendingRemoveAfterTicketDelete.value = row.id != null ? { kind: 'priority', id: row.id } : null;
        inUseModalStep.value = 'info';
        inUseModalOpen.value = true;
        return;
    }
    priorities.splice(idx, 1);
}
function movePriorityUp(idx: number): void {
    if (idx === 0) { return; }
    [priorities[idx - 1], priorities[idx]] = [priorities[idx], priorities[idx - 1]];
}
function movePriorityDown(idx: number): void {
    if (idx === priorities.length - 1) { return; }
    [priorities[idx], priorities[idx + 1]] = [priorities[idx + 1], priorities[idx]];
}

// ── Status helpers ─────────────────────────────────────────────────────────
function addStatus(): void {
    statuses.push({
        name: '',
        icon: 'Circle',
        color: '#64748b',
        handler_requirement: 'optional',
        clientKey: crypto.randomUUID(),
    });
}

function ticketCountForStatusRow(row: StatusRow): number {
    if (row.id == null) {
        return 0;
    }
    const map = props.statusTicketCountsById ?? {};
    const raw = map[String(row.id)];
    const n = typeof raw === 'number' ? raw : Number(raw);
    return Number.isFinite(n) ? n : 0;
}

function requestRemoveStatus(idx: number): void {
    const row = statuses[idx];
    if (! row) {
        return;
    }
    if (isProtectedStatusRow(row)) {
        return;
    }
    const count = ticketCountForStatusRow(row);
    if (count > 0) {
        const label = row.name.trim() || 'This status';
        inUseModalMessage.value =
            count === 1
                ? `Cannot remove "${label}" because 1 ticket still uses it. Reassign that ticket first, or delete it below.`
                : `Cannot remove "${label}" because ${count} tickets still use it. Reassign those tickets first, or delete them below.`;
        inUseModalKind.value = 'status';
        inUseModalEntityId.value = row.id ?? null;
        inUseModalTicketCount.value = count;
        pendingRemoveAfterTicketDelete.value = row.id != null ? { kind: 'status', id: row.id } : null;
        inUseModalStep.value = 'info';
        inUseModalOpen.value = true;
        return;
    }
    statuses.splice(idx, 1);
}
function moveStatusUp(idx: number): void {
    if (idx === 0) { return; }
    [statuses[idx - 1], statuses[idx]] = [statuses[idx], statuses[idx - 1]];
}
function moveStatusDown(idx: number): void {
    if (idx === statuses.length - 1) { return; }
    [statuses[idx], statuses[idx + 1]] = [statuses[idx + 1], statuses[idx]];
}

// ── Drag-and-drop reorder (grip handle → HTML5 DnD) ───────────────────────
type ConfigListKind = 'categories' | 'priorities' | 'statuses';

const dragConfig = ref<{ kind: ConfigListKind; from: number } | null>(null);
const dragOverConfig = ref<{ kind: ConfigListKind; index: number } | null>(null);

/** DOM clone shown under the cursor during drag; removed on dragend */
let configDragGhostEl: HTMLElement | null = null;

function rowKeyCategory(cat: CategoryRow, idx: number): string {
    return cat.id != null ? `cat-id-${cat.id}` : (cat.clientKey ?? `cat-fallback-${idx}`);
}

function rowKeyPriority(pri: PriorityRow, idx: number): string {
    return pri.id != null ? `pri-id-${pri.id}` : (pri.clientKey ?? `pri-fallback-${idx}`);
}

function rowKeyStatus(st: StatusRow, idx: number): string {
    return st.id != null ? `st-id-${st.id}` : (st.clientKey ?? `st-fallback-${idx}`);
}

function onConfigDragStart(kind: ConfigListKind, idx: number, e: DragEvent): void {
    dragConfig.value = { kind, from: idx };
    e.dataTransfer?.setData('text/plain', `${kind}:${idx}`);
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
    }

    const handle = e.currentTarget as HTMLElement | null;
    const row = handle?.closest('[data-config-dnd-row]') as HTMLElement | null;
    if (! row || ! e.dataTransfer) {
        return;
    }

    if (configDragGhostEl) {
        configDragGhostEl.remove();
        configDragGhostEl = null;
    }

    const clone = row.cloneNode(true) as HTMLElement;
    const width = row.getBoundingClientRect().width;
    clone.style.boxSizing = 'border-box';
    clone.style.width = `${width}px`;
    clone.style.maxWidth = `${width}px`;
    clone.style.opacity = '0.96';
    clone.style.pointerEvents = 'none';
    clone.style.position = 'fixed';
    clone.style.left = '-10000px';
    clone.style.top = '0';
    clone.style.zIndex = '10000';
    clone.style.boxShadow = '0 25px 50px -12px rgb(0 0 0 / 0.35)';
    clone.style.borderRadius = '0.5rem';
    clone.classList.add('border-2', 'border-primary/40', 'bg-card');

    clone.querySelectorAll('[draggable]').forEach((el) => {
        (el as HTMLElement).removeAttribute('draggable');
    });

    document.body.appendChild(clone);
    void clone.offsetWidth;

    const rect = row.getBoundingClientRect();
    const offsetX = Math.min(Math.max(e.clientX - rect.left, 8), rect.width - 8);
    const offsetY = Math.min(Math.max(e.clientY - rect.top, 8), rect.height - 8);
    e.dataTransfer.setDragImage(clone, offsetX, offsetY);
    configDragGhostEl = clone;
}

function onConfigDragEnd(): void {
    if (configDragGhostEl) {
        configDragGhostEl.remove();
        configDragGhostEl = null;
    }
    dragConfig.value = null;
    dragOverConfig.value = null;
}

function onConfigDragOver(kind: ConfigListKind, idx: number, e: DragEvent): void {
    e.preventDefault();
    if (dragConfig.value?.kind !== kind) {
        return;
    }
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
    dragOverConfig.value = { kind, index: idx };
}

function onConfigDragLeave(kind: ConfigListKind, idx: number, e: DragEvent): void {
    const el = e.currentTarget as HTMLElement;
    const related = e.relatedTarget as Node | null;
    if (related && el.contains(related)) {
        return;
    }
    if (dragOverConfig.value?.kind === kind && dragOverConfig.value.index === idx) {
        dragOverConfig.value = null;
    }
}

function onConfigDrop(kind: ConfigListKind, toIdx: number, e: DragEvent): void {
    e.preventDefault();
    const state = dragConfig.value;
    dragConfig.value = null;
    dragOverConfig.value = null;
    if (! state || state.kind !== kind) {
        return;
    }
    const from = state.from;
    if (from === toIdx) {
        return;
    }
    if (kind === 'categories') {
        const [moved] = categories.splice(from, 1);
        categories.splice(toIdx, 0, moved);
    } else if (kind === 'priorities') {
        const [moved] = priorities.splice(from, 1);
        priorities.splice(toIdx, 0, moved);
    } else {
        const [moved] = statuses.splice(from, 1);
        statuses.splice(toIdx, 0, moved);
    }
}

function configRowDragOverClass(kind: ConfigListKind, idx: number): string {
    if (dragOverConfig.value?.kind !== kind || dragOverConfig.value.index !== idx) {
        return '';
    }
    return 'ring-2 ring-primary/50 border-primary/40 bg-primary/5';
}

function configRowDraggingClass(kind: ConfigListKind, idx: number): string {
    if (dragConfig.value?.kind !== kind || dragConfig.value.from !== idx) {
        return '';
    }
    return 'opacity-[0.38] scale-[0.985] shadow-inner';
}

// ── Icon picker (teleported to body, lazy-loaded Lucide module) ───────────
interface PickerState {
    type: 'cat' | 'pri' | 'stat';
    idx: number;
    x: number;
    y: number;
}

const activePicker = ref<PickerState | null>(null);
const iconSearch = ref('');
const searchInputRef = ref<InstanceType<typeof Input> | null>(null);
/** Scroll container for the icon grid — ignore window scroll events that originate here */
const iconPickerScrollRef = ref<HTMLElement | null>(null);

function resolveIcon(name: string) {
    return resolveLucideIcon(name, HelpCircle);
}

const filteredIconNames = computed((): string[] => {
    const q = iconSearch.value.toLowerCase().trim();
    const names = Object.keys(lucideAllIconMap.value).sort();
    if (! q) {
        return names;
    }
    return names.filter((n) => n.toLowerCase().includes(q));
});

/** How many icon buttons are mounted (rest are filled on following animation frames). */
const iconGridDisplayLimit = ref(0);
let iconGridRevealFrameId: number | null = null;

const displayedPickerIconNames = computed((): string[] => {
    const all = filteredIconNames.value;
    const cap = iconGridDisplayLimit.value;
    if (cap <= 0) {
        return [];
    }
    return all.slice(0, cap);
});

function stopIconGridReveal(): void {
    if (iconGridRevealFrameId !== null) {
        cancelAnimationFrame(iconGridRevealFrameId);
        iconGridRevealFrameId = null;
    }
}

/**
 * Mount icons in chunks so the popover stays responsive (full list is thousands of VNodes).
 */
function startIconGridReveal(): void {
    stopIconGridReveal();
    const total = filteredIconNames.value.length;
    if (total === 0) {
        iconGridDisplayLimit.value = 0;
        return;
    }
    const firstChunk = 60;
    const perFrame = 80;
    iconGridDisplayLimit.value = Math.min(firstChunk, total);
    let shown = iconGridDisplayLimit.value;
    const step = (): void => {
        if (! activePicker.value) {
            iconGridRevealFrameId = null;
            return;
        }
        if (shown >= total) {
            iconGridRevealFrameId = null;
            return;
        }
        shown = Math.min(shown + perFrame, total);
        iconGridDisplayLimit.value = shown;
        iconGridRevealFrameId = requestAnimationFrame(step);
    };
    if (shown < total) {
        iconGridRevealFrameId = requestAnimationFrame(step);
    }
}

watch(iconSearch, () => {
    if (activePicker.value && ! lucideIconsLoading.value) {
        startIconGridReveal();
    }
});

watch(lucideIconsLoading, (loading) => {
    if (! loading && activePicker.value) {
        startIconGridReveal();
    }
});

async function openPicker(e: MouseEvent, type: 'cat' | 'pri' | 'stat', idx: number): Promise<void> {
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
    iconGridDisplayLimit.value = 0;
    stopIconGridReveal();
    activePicker.value = { type, idx, x, y };

    await nextTick();

    void ensureLucideIconsLoaded().then(async () => {
        await nextTick();
        if (activePicker.value) {
            startIconGridReveal();
        }
        (searchInputRef.value as HTMLInputElement | null)?.focus?.();
    });
}

function closePicker(): void {
    stopIconGridReveal();
    iconGridDisplayLimit.value = 0;
    activePicker.value = null;
    iconSearch.value = '';
}

function selectIcon(icon: string): void {
    if (! activePicker.value) { return; }
    const { type, idx } = activePicker.value;
    if (type === 'cat') {
        categories[idx].icon = icon;
    } else if (type === 'pri') {
        priorities[idx].icon = icon;
    } else {
        statuses[idx].icon = icon;
    }
    closePicker();
}

function currentPickerIcon(): string {
    if (! activePicker.value) { return ''; }
    const { type, idx } = activePicker.value;
    if (type === 'cat') {
        return categories[idx]?.icon ?? '';
    }
    if (type === 'pri') {
        return priorities[idx]?.icon ?? '';
    }
    return statuses[idx]?.icon ?? '';
}

// Close picker on Escape key or page scroll (not when scrolling inside the picker list)
function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape') { closePicker(); }
}
function onScroll(e: Event): void {
    if (! activePicker.value) {
        return;
    }
    const target = e.target;
    if (target instanceof Node && iconPickerScrollRef.value?.contains(target)) {
        return;
    }
    closePicker();
}
window.addEventListener('keydown', onKeydown);
window.addEventListener('scroll', onScroll, true);
onMounted(() => {
    void ensureLucideIconsLoaded();
});
onUnmounted(() => {
    stopIconGridReveal();
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', onScroll, true);
    if (configDragGhostEl) {
        configDragGhostEl.remove();
        configDragGhostEl = null;
    }
});

// ── Forms ─────────────────────────────────────────────────────────────────
const generalForm = useForm({
    settings: {
        app_name: rawVal(props.settings.general, 'app_name', '') as string,
    },
});

const categoriesForm = useForm({ categories: categories as CategoryRow[] });
const prioritiesForm = useForm({ priorities: priorities as PriorityRow[] });
const statusesForm = useForm({ statuses: statuses as StatusRow[] });

type TicketConfigSaveKind = 'categories' | 'priorities' | 'statuses';

const emptyLabelWarningOpen = ref(false);
const emptyLabelWarningContext = ref<TicketConfigSaveKind | null>(null);

function onEmptyLabelWarningOpen(open: boolean): void {
    emptyLabelWarningOpen.value = open;
    if (! open) {
        emptyLabelWarningContext.value = null;
    }
}

function openEmptyLabelWarning(context: TicketConfigSaveKind): void {
    emptyLabelWarningContext.value = context;
    emptyLabelWarningOpen.value = true;
}

const emptyLabelWarningBody = computed((): string => {
    const ctx = emptyLabelWarningContext.value;
    if (ctx === 'categories') {
        return 'One or more categories have an empty name. Enter a label for each row before saving.';
    }
    if (ctx === 'priorities') {
        return 'One or more priorities have an empty name. Enter a label for each row before saving.';
    }
    if (ctx === 'statuses') {
        return 'One or more statuses have an empty name. Enter a label for each row before saving.';
    }
    return 'One or more rows have an empty name. Enter a label for each row before saving.';
});

function hasEmptyTicketConfigLabel(rows: { name: string }[]): boolean {
    return rows.some((row) => row.name.trim() === '');
}

const inUseModalOpen = ref(false);
const inUseModalKind = ref<InUseKind | null>(null);
const inUseModalMessage = ref('');
const inUseModalStep = ref<'info' | 'confirm'>('info');
const inUseModalEntityId = ref<number | null>(null);
const inUseModalTicketCount = ref(0);
const pendingRemoveAfterTicketDelete = ref<{ kind: InUseKind; id: number } | null>(null);
const deletingInUseTickets = ref(false);

const inUseModalInfoTitle = computed((): string => {
    if (inUseModalKind.value === null) {
        return 'Cannot save changes';
    }
    if (inUseModalKind.value === 'category') {
        return 'Category still in use';
    }
    if (inUseModalKind.value === 'priority') {
        return 'Priority still in use';
    }
    return 'Status still in use';
});

const inUseConfirmScopeLabel = computed((): string => {
    if (inUseModalKind.value === 'category') {
        return 'this category';
    }
    if (inUseModalKind.value === 'priority') {
        return 'this priority';
    }
    return 'this status';
});

const appearanceForm = useForm({
    settings: {
        default_theme: rawVal(props.settings.appearance, 'default_theme', 'system') as string,
    },
});

// ── Submit handlers ───────────────────────────────────────────────────────
function submitGeneral(): void {
    generalForm.put(route('admin.settings.update'));
}

function submitCategories(): void {
    if (hasEmptyTicketConfigLabel(categories)) {
        openEmptyLabelWarning('categories');
        return;
    }
    categoriesForm.categories = [...categories] as CategoryRow[];
    categoriesForm.put(route('admin.ticket-categories.update'), {
        onError: (errors) => {
            const raw = errors.categories;
            if (raw === undefined || raw === null || raw === '') {
                return;
            }
            const message = Array.isArray(raw) ? raw[0] : String(raw);
            inUseModalMessage.value = message;
            inUseModalKind.value = null;
            inUseModalEntityId.value = null;
            inUseModalTicketCount.value = 0;
            pendingRemoveAfterTicketDelete.value = null;
            inUseModalStep.value = 'info';
            inUseModalOpen.value = true;
            categoriesForm.clearErrors('categories');
        },
    });
}

function submitPriorities(): void {
    if (hasEmptyTicketConfigLabel(priorities)) {
        openEmptyLabelWarning('priorities');
        return;
    }
    prioritiesForm.priorities = [...priorities] as PriorityRow[];
    prioritiesForm.put(route('admin.ticket-priorities.update'), {
        onError: (errors) => {
            const raw = errors.priorities;
            if (raw === undefined || raw === null || raw === '') {
                return;
            }
            const message = Array.isArray(raw) ? raw[0] : String(raw);
            inUseModalMessage.value = message;
            inUseModalKind.value = null;
            inUseModalEntityId.value = null;
            inUseModalTicketCount.value = 0;
            pendingRemoveAfterTicketDelete.value = null;
            inUseModalStep.value = 'info';
            inUseModalOpen.value = true;
            prioritiesForm.clearErrors('priorities');
        },
    });
}

function submitStatuses(): void {
    if (hasEmptyTicketConfigLabel(statuses)) {
        openEmptyLabelWarning('statuses');
        return;
    }
    statusesForm.statuses = statuses.map((s) => ({
        ...s,
        handler_requirement: s.handler_requirement ?? 'optional',
    })) as StatusRow[];
    statusesForm.put(route('admin.ticket-statuses.update'), {
        onError: (errors) => {
            const raw = errors.statuses;
            if (raw === undefined || raw === null || raw === '') {
                return;
            }
            const message = Array.isArray(raw) ? raw[0] : String(raw);
            inUseModalMessage.value = message;
            inUseModalKind.value = null;
            inUseModalEntityId.value = null;
            inUseModalTicketCount.value = 0;
            pendingRemoveAfterTicketDelete.value = null;
            inUseModalStep.value = 'info';
            inUseModalOpen.value = true;
            statusesForm.clearErrors('statuses');
        },
    });
}

function onInUseModalOpen(open: boolean): void {
    inUseModalOpen.value = open;
    if (! open) {
        inUseModalMessage.value = '';
        inUseModalKind.value = null;
        inUseModalStep.value = 'info';
        inUseModalEntityId.value = null;
        inUseModalTicketCount.value = 0;
        pendingRemoveAfterTicketDelete.value = null;
        deletingInUseTickets.value = false;
    }
}

function goInUseModalConfirm(): void {
    inUseModalStep.value = 'confirm';
}

function goInUseModalInfo(): void {
    inUseModalStep.value = 'info';
}

function executeDeleteTicketsForInUse(): void {
    const id = inUseModalEntityId.value;
    const kind = inUseModalKind.value;
    if (id == null || kind == null) {
        return;
    }
    deletingInUseTickets.value = true;
    let url: string;
    if (kind === 'status') {
        url = route('admin.ticket-statuses.tickets.destroy', { ticketStatus: id });
    } else if (kind === 'category') {
        url = route('admin.ticket-categories.tickets.destroy', { ticketCategory: id });
    } else {
        url = route('admin.ticket-priorities.tickets.destroy', { ticketPriority: id });
    }
    router.delete(url, {
        preserveScroll: true,
        onFinish: () => {
            deletingInUseTickets.value = false;
        },
        onSuccess: () => {
            const pending = pendingRemoveAfterTicketDelete.value;
            if (pending != null) {
                if (pending.kind === 'status') {
                    const i = statuses.findIndex((s) => s.id === pending.id);
                    if (i !== -1) {
                        statuses.splice(i, 1);
                    }
                } else if (pending.kind === 'category') {
                    const i = categories.findIndex((c) => c.id === pending.id);
                    if (i !== -1) {
                        categories.splice(i, 1);
                    }
                } else {
                    const i = priorities.findIndex((p) => p.id === pending.id);
                    if (i !== -1) {
                        priorities.splice(i, 1);
                    }
                }
            }
            pendingRemoveAfterTicketDelete.value = null;
            onInUseModalOpen(false);
        },
    });
}

function submitAppearance(): void {
    appearanceForm.put(route('admin.settings.update'));
}
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
                        <Card class="shadow-sm border border-border/60 transition-all duration-300 hover:shadow-md">
                            <CardHeader>
                                <CardTitle>General Settings</CardTitle>
                                <CardDescription>Application name as shown across the system.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">

                                <div class="grid gap-2">
                                    <Label for="app_name">Application Name</Label>
                                    <Input id="app_name" v-model="generalForm.settings.app_name" placeholder="My Application" />
                                    <p v-if="generalForm.errors['settings.app_name']" class="text-xs text-destructive">
                                        {{ generalForm.errors['settings.app_name'] }}
                                    </p>
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
                            <Card class="shadow-sm border border-border/60 transition-all duration-300 hover:shadow-md">
                                <CardHeader>
                                    <CardTitle>Ticket Categories</CardTitle>
                                    <CardDescription>
                                        Categories available when creating a ticket. Each entry can have an icon. Core defaults cannot be removed; the "Others" category and any extra rows you add can be deleted.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="flex flex-col gap-2">
                                    <TransitionGroup name="ticket-config-dnd" tag="div" class="flex flex-col gap-2">
                                    <div
                                        v-for="(cat, idx) in categories"
                                        :key="rowKeyCategory(cat, idx)"
                                        data-config-dnd-row
                                        class="group flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 transition-[transform,opacity,box-shadow,background-color] duration-300 ease-out hover:bg-muted/40"
                                        :class="[configRowDragOverClass('categories', idx), configRowDraggingClass('categories', idx)]"
                                        @dragover.prevent="onConfigDragOver('categories', idx, $event)"
                                        @dragleave="onConfigDragLeave('categories', idx, $event)"
                                        @drop.prevent="onConfigDrop('categories', idx, $event)"
                                    >
                                        <span
                                            class="inline-flex shrink-0 touch-none cursor-grab select-none text-muted-foreground/40 active:cursor-grabbing group-hover:text-muted-foreground"
                                            draggable="true"
                                            title="Drag to reorder"
                                            @dragstart.stop="onConfigDragStart('categories', idx, $event)"
                                            @dragend="onConfigDragEnd"
                                        >
                                            <GripVertical class="h-4 w-4 pointer-events-none" />
                                        </span>

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
                                            <button
                                                v-if="!isProtectedCategoryRow(cat)"
                                                type="button"
                                                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                title="Remove row"
                                                @click="requestRemoveCategory(idx)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    </TransitionGroup>

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
                            <Card class="shadow-sm border border-border/60 transition-all duration-300 hover:shadow-md">
                                <CardHeader>
                                    <CardTitle>Ticket Priorities</CardTitle>
                                    <CardDescription>
                                        Priority levels for tickets. Each has an icon and a badge colour. Default priorities cannot be removed; you can still add custom levels.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="flex flex-col gap-2">
                                    <TransitionGroup name="ticket-config-dnd" tag="div" class="flex flex-col gap-2">
                                    <div
                                        v-for="(pri, idx) in priorities"
                                        :key="rowKeyPriority(pri, idx)"
                                        data-config-dnd-row
                                        class="group flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 transition-[transform,opacity,box-shadow,background-color] duration-300 ease-out hover:bg-muted/40"
                                        :class="[configRowDragOverClass('priorities', idx), configRowDraggingClass('priorities', idx)]"
                                        @dragover.prevent="onConfigDragOver('priorities', idx, $event)"
                                        @dragleave="onConfigDragLeave('priorities', idx, $event)"
                                        @drop.prevent="onConfigDrop('priorities', idx, $event)"
                                    >
                                        <span
                                            class="inline-flex shrink-0 touch-none cursor-grab select-none text-muted-foreground/40 active:cursor-grabbing group-hover:text-muted-foreground"
                                            draggable="true"
                                            title="Drag to reorder"
                                            @dragstart.stop="onConfigDragStart('priorities', idx, $event)"
                                            @dragend="onConfigDragEnd"
                                        >
                                            <GripVertical class="h-4 w-4 pointer-events-none" />
                                        </span>

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
                                            <button
                                                v-if="!isProtectedPriorityRow(pri)"
                                                type="button"
                                                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                title="Remove row"
                                                @click="requestRemovePriority(idx)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    </TransitionGroup>

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
                            <Card class="shadow-sm border border-border/60 transition-all duration-300 hover:shadow-md">
                                <CardHeader>
                                    <CardTitle>Ticket Statuses</CardTitle>
                                    <CardDescription>
                                        Workflow labels with icon and colour. For each status, choose whether the new-ticket and edit flows hide handlers, require at least one, or show them as optional (e.g. closed tickets). Default statuses cannot be removed; their handler rules are fixed.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="flex flex-col gap-2">
                                    <TransitionGroup name="ticket-config-dnd" tag="div" class="flex flex-col gap-2">
                                    <div
                                        v-for="(st, idx) in statuses"
                                        :key="rowKeyStatus(st, idx)"
                                        data-config-dnd-row
                                        class="group flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 transition-[transform,opacity,box-shadow,background-color] duration-300 ease-out hover:bg-muted/40"
                                        :class="[configRowDragOverClass('statuses', idx), configRowDraggingClass('statuses', idx)]"
                                        @dragover.prevent="onConfigDragOver('statuses', idx, $event)"
                                        @dragleave="onConfigDragLeave('statuses', idx, $event)"
                                        @drop.prevent="onConfigDrop('statuses', idx, $event)"
                                    >
                                        <span
                                            class="inline-flex shrink-0 touch-none cursor-grab select-none text-muted-foreground/40 active:cursor-grabbing group-hover:text-muted-foreground"
                                            draggable="true"
                                            title="Drag to reorder"
                                            @dragstart.stop="onConfigDragStart('statuses', idx, $event)"
                                            @dragend="onConfigDragEnd"
                                        >
                                            <GripVertical class="h-4 w-4 pointer-events-none" />
                                        </span>
                                        <button
                                            type="button"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-input bg-background shadow-sm transition-all hover:border-primary/50 hover:ring-2 hover:ring-primary/20 focus:outline-none"
                                            :class="activePicker?.type === 'stat' && activePicker.idx === idx ? 'border-primary ring-2 ring-primary/20' : ''"
                                            :style="{ color: st.color }"
                                            :title="`Change icon (${st.icon})`"
                                            @click="openPicker($event, 'stat', idx)"
                                        >
                                            <component :is="resolveIcon(st.icon)" class="h-4 w-4" />
                                        </button>
                                        <Input v-model="statuses[idx].name" class="min-w-0 flex-1 border-0 bg-transparent px-0 font-medium shadow-none focus-visible:ring-0 focus-visible:ring-offset-0" placeholder="Status label" />
                                        <Select
                                            :model-value="st.handler_requirement ?? 'optional'"
                                            :disabled="isProtectedStatusRow(st)"
                                            @update:model-value="(v) => (statuses[idx].handler_requirement = v as StatusHandlerRequirement)"
                                        >
                                            <SelectTrigger
                                                class="h-8 w-[9.75rem] shrink-0 text-xs"
                                                :title="isProtectedStatusRow(st) ? 'Built-in statuses cannot change handler rules' : undefined"
                                            >
                                                <SelectValue placeholder="Handlers" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">No handlers</SelectItem>
                                                <SelectItem value="optional">Handlers optional</SelectItem>
                                                <SelectItem value="required">Handlers required</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <label class="relative flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md border border-input bg-background shadow-sm transition-all hover:border-primary/50 hover:ring-2 hover:ring-primary/20" :title="st.color">
                                            <span class="h-4 w-4 rounded-full border border-border/50 shadow-sm" :style="{ backgroundColor: st.color }" />
                                            <input v-model="st.color" type="color" class="sr-only" />
                                        </label>
                                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-30" :disabled="idx === 0" @click="moveStatusUp(idx)">
                                                <ArrowUp class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-30" :disabled="idx === statuses.length - 1" @click="moveStatusDown(idx)">
                                                <ArrowDown class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                v-if="!isProtectedStatusRow(st)"
                                                type="button"
                                                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                title="Remove row"
                                                @click="requestRemoveStatus(idx)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    </TransitionGroup>

                                    <div class="flex items-center justify-between pt-1">
                                        <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5 text-xs" @click="addStatus">
                                            <Plus class="h-3.5 w-3.5" /> Add Status
                                        </Button>
                                        <Button type="submit" size="sm" :disabled="statusesForm.processing">
                                            <Check class="mr-1.5 h-3.5 w-3.5" />
                                            {{ statusesForm.processing ? 'Saving…' : 'Save Statuses' }}
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
                        <Card class="shadow-sm border border-border/60 transition-all duration-300 hover:shadow-md">
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

        <Dialog :open="inUseModalOpen" @update:open="onInUseModalOpen">
            <DialogContent
                class="gap-0 overflow-hidden border border-border/40 bg-card p-0 shadow-2xl sm:rounded-2xl sm:max-w-[432px]"
            >
                <div class="relative px-6 pb-6 pt-7">
                    <div
                        v-if="inUseModalStep === 'info'"
                        class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-[radial-gradient(ellipse_80%_100%_at_50%_-20%,rgba(245,158,11,0.22),transparent)] dark:bg-[radial-gradient(ellipse_80%_100%_at_50%_-20%,rgba(245,158,11,0.14),transparent)]"
                        aria-hidden="true"
                    />
                    <div
                        v-else
                        class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-[radial-gradient(ellipse_80%_100%_at_50%_-20%,rgba(244,63,94,0.18),transparent)] dark:bg-[radial-gradient(ellipse_80%_100%_at_50%_-20%,rgba(244,63,94,0.12),transparent)]"
                        aria-hidden="true"
                    />
                    <DialogHeader class="relative space-y-4 text-left">
                        <div
                            v-if="inUseModalStep === 'info'"
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/[0.14] text-amber-600 shadow-inner shadow-amber-900/5 ring-1 ring-amber-500/25 dark:bg-amber-500/[0.12] dark:text-amber-400 dark:ring-amber-400/20"
                        >
                            <AlertCircle class="h-7 w-7" stroke-width="2" />
                        </div>
                        <div
                            v-else
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-500/15 text-rose-600 shadow-inner shadow-rose-900/10 ring-1 ring-rose-500/35 dark:bg-rose-950/55 dark:text-rose-400 dark:shadow-black/20 dark:ring-rose-400/30"
                        >
                            <Trash2 class="h-7 w-7" stroke-width="2" />
                        </div>
                        <div class="space-y-3 pr-8">
                            <DialogTitle class="text-xl font-semibold tracking-tight text-foreground">
                                <template v-if="inUseModalStep === 'info'">{{ inUseModalInfoTitle }}</template>
                                <template v-else>Delete these tickets?</template>
                            </DialogTitle>
                            <DialogDescription
                                v-if="inUseModalStep === 'info'"
                                class="text-[15px] leading-relaxed text-foreground antialiased"
                            >
                                {{ inUseModalMessage }}
                            </DialogDescription>
                            <DialogDescription
                                v-else
                                class="text-[15px] leading-relaxed text-foreground antialiased"
                            >
                                This will permanently delete
                                {{ inUseModalTicketCount === 1 ? '1 ticket' : `${inUseModalTicketCount} tickets` }}
                                that currently match {{ inUseConfirmScopeLabel }}, including comments and history. This cannot be undone.
                            </DialogDescription>
                        </div>
                    </DialogHeader>
                </div>

                <DialogFooter class="border-t border-border/60 bg-muted/25 px-6 py-4 sm:gap-2">
                    <template v-if="inUseModalStep === 'info'">
                        <Button
                            v-if="inUseModalEntityId != null"
                            type="button"
                            variant="outline"
                            class="h-10 w-full border-rose-500/40 text-rose-700 shadow-sm hover:bg-rose-500/[0.12] hover:text-rose-800 dark:border-rose-400/35 dark:bg-transparent dark:text-rose-300 dark:hover:bg-rose-950/60 dark:hover:text-rose-100 sm:w-auto"
                            @click="goInUseModalConfirm"
                        >
                            <Trash2 class="mr-2 h-4 w-4 opacity-90 dark:opacity-100" />
                            Delete
                            {{ inUseModalTicketCount === 1 ? '1 ticket' : `${inUseModalTicketCount} tickets` }}
                        </Button>
                        <Button
                            type="button"
                            class="h-10 w-full font-semibold sm:w-auto sm:min-w-[8.5rem]"
                            @click="onInUseModalOpen(false)"
                        >
                            {{ inUseModalEntityId != null ? 'Cancel' : 'Got it' }}
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full sm:w-auto"
                            :disabled="deletingInUseTickets"
                            @click="goInUseModalInfo"
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            class="h-10 w-full font-semibold shadow-sm dark:bg-rose-600 dark:text-white dark:shadow-rose-950/40 dark:hover:bg-rose-500 dark:hover:text-white sm:w-auto sm:min-w-[10rem]"
                            :disabled="deletingInUseTickets"
                            @click="executeDeleteTicketsForInUse"
                        >
                            <Loader2 v-if="deletingInUseTickets" class="mr-2 h-4 w-4 animate-spin" />
                            <Trash2 v-else class="mr-2 h-4 w-4" />
                            {{ deletingInUseTickets ? 'Deleting…' : 'Delete permanently' }}
                        </Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="emptyLabelWarningOpen" @update:open="onEmptyLabelWarningOpen">
            <DialogContent
                class="gap-0 overflow-hidden border border-border/40 bg-card p-0 shadow-2xl sm:rounded-2xl sm:max-w-[432px]"
            >
                <div class="relative px-6 pb-6 pt-7">
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-[radial-gradient(ellipse_80%_100%_at_50%_-20%,rgba(245,158,11,0.22),transparent)] dark:bg-[radial-gradient(ellipse_80%_100%_at_50%_-20%,rgba(245,158,11,0.14),transparent)]"
                        aria-hidden="true"
                    />
                    <DialogHeader class="relative space-y-4 text-left">
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/[0.14] text-amber-600 shadow-inner shadow-amber-900/5 ring-1 ring-amber-500/25 dark:bg-amber-500/[0.12] dark:text-amber-400 dark:ring-amber-400/20"
                        >
                            <AlertCircle class="h-7 w-7" stroke-width="2" />
                        </div>
                        <div class="space-y-3 pr-8">
                            <DialogTitle class="text-xl font-semibold tracking-tight text-foreground">
                                Missing labels
                            </DialogTitle>
                            <DialogDescription class="text-[15px] leading-relaxed text-foreground antialiased">
                                {{ emptyLabelWarningBody }}
                            </DialogDescription>
                        </div>
                    </DialogHeader>
                </div>
                <DialogFooter class="border-t border-border/60 bg-muted/25 px-6 py-4 sm:gap-2">
                    <Button
                        type="button"
                        class="h-10 w-full font-semibold sm:w-auto sm:min-w-[8.5rem]"
                        @click="onEmptyLabelWarningOpen(false)"
                    >
                        Got it
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
                            :placeholder="lucideIconsLoading ? 'Loading icons…' : 'Search icons…'"
                            :disabled="lucideIconsLoading"
                            class="flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground disabled:cursor-wait disabled:opacity-60"
                            @focus="ensureLucideIconsLoaded()"
                        />
                        <span class="text-[10px] tabular-nums text-muted-foreground/60">
                            <Loader2 v-if="lucideIconsLoading" class="inline h-3 w-3 animate-spin opacity-70" />
                            <template v-else>{{ filteredIconNames.length }}</template>
                        </span>
                    </div>

                    <!-- Icon grid -->
                    <div
                        ref="iconPickerScrollRef"
                        class="relative h-60 overflow-y-auto overscroll-y-contain p-2"
                    >
                        <div
                            v-if="lucideIconsLoading"
                            class="flex min-h-[13.5rem] flex-col items-center justify-center gap-3 px-4 text-center"
                        >
                            <Loader2 class="h-9 w-9 animate-spin text-muted-foreground" />
                            <p class="text-xs text-muted-foreground">Loading icon library…</p>
                        </div>
                        <div v-else class="grid grid-cols-5 gap-1">
                            <button
                                v-for="iconName in displayedPickerIconNames"
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

                            <div
                                v-if="filteredIconNames.length === 0"
                                class="col-span-5 py-8 text-center text-xs text-muted-foreground"
                            >
                                No icons match "{{ iconSearch }}"
                            </div>
                            <div
                                v-else-if="displayedPickerIconNames.length < filteredIconNames.length"
                                class="col-span-5 flex items-center justify-center gap-1.5 py-2 text-[10px] text-muted-foreground"
                            >
                                <Loader2 class="h-3 w-3 shrink-0 animate-spin" />
                                <span>Rendering icons…</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer hint -->
                    <div class="border-t border-border px-3 py-1.5">
                        <span class="text-[10px] text-muted-foreground/60">
                            <template v-if="lucideIconsLoading">This may take a moment on first open.</template>
                            <template v-else>{{ Object.keys(lucideAllIconMap).length }} icons · scroll to browse</template>
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* List reorder after drag-drop: smooth FLIP move */
.ticket-config-dnd-move {
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
</style>
