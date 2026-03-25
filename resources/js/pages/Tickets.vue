<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import TicketComments from '@/components/TicketComments.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
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
    HelpCircle,
    Pencil,
    Save,
    Upload,
    ImageIcon,
    SlidersHorizontal,
    ChevronRight,
    Lock,
    RefreshCcw,
    Download,
    History,
    GitBranch,
    Flag,
    MessageSquare,
    UserMinus,
    Smile,
    FilePenLine,
    Pin,
    PinOff,
} from 'lucide-vue-next';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Ticket, TicketCheck, Loader, Pause, Play, X, Ban, ChevronsUpDown, ChevronUp, ChevronDown, ChevronLeft } from 'lucide-vue-next';

interface ActivityEntry {
    id: number;
    action: string;
    oldValue: string | null;
    newValue: string | null;
    userName: string;
    createdAt: string;
    createdAtFormatted: string;
}

// ── Detail modal history tab ───────────────────────────────────────────────
const detailTab = ref<'overview' | 'history'>('overview');
const activityLog = ref<ActivityEntry[]>([]);
const activityLoading = ref(false);
const historyFetchedFor = ref<number | null>(null);

async function fetchHistory(ticketId: number): Promise<void> {
    if (historyFetchedFor.value === ticketId) return;
    activityLoading.value = true;
    try {
        const res = await fetch(route('tickets.history', { ticket: ticketId }));
        activityLog.value = await res.json();
        historyFetchedFor.value = ticketId;
    } finally {
        activityLoading.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tickets',
        href: '/tickets',
    },
];

const sessionExpiredBanner = ref(false);

const checkCsrfExpired = () => {
    if (sessionStorage.getItem('csrf_expired')) {
        sessionStorage.removeItem('csrf_expired');
        sessionExpiredBanner.value = true;
        setTimeout(() => { sessionExpiredBanner.value = false; }, 6000);
    }
};

// Fires on full page mount (hard navigation / first load)
onMounted(checkCsrfExpired);

// Fires after a preserveState reload (component stays mounted, onMounted won't re-run)
const removeFinishListener = router.on('finish', checkCsrfExpired);
onUnmounted(removeFinishListener);

const props = defineProps<{
    tickets: {
        numericId: number;
        id: string;
        title: string;
        description: string | null;
        status: string;
        priority: string;
        category: string;
        handlerIds: number[];
        handlers: { id: number; name: string }[];
        reporter: string;
        reporterId: number;
        createdAt: string;
        createdAtFormatted: string;
        createdAtRaw: string;
        solution: string | null;
        resolvedInDuration: string | null;
        resolvedAtFormatted: string | null;
        attachmentUrl: string | null;
    }[];
    users: {
        id: number;
        name: string;
    }[];
}>();

const ticketStats = computed(() => [
    {
        label: 'Total',
        status: 'All',
        value: preStatusFiltered.value.length,
        icon: Ticket,
        colorClass: 'text-primary',
        bgClass: 'bg-primary/10',
        borderActive: 'border-primary/40',
        glowClass: 'shadow-primary/20',
        ringClass: 'ring-primary/20',
    },
    {
        label: 'Open',
        status: 'Open',
        value: preStatusFiltered.value.filter(t => t.status === 'Open').length,
        icon: AlertTriangle,
        colorClass: 'text-rose-500',
        bgClass: 'bg-rose-500/10',
        borderActive: 'border-rose-500/40',
        glowClass: 'shadow-rose-500/20',
        ringClass: 'ring-rose-500/20',
    },
    {
        label: 'In Progress',
        status: 'In Progress',
        value: preStatusFiltered.value.filter(t => t.status === 'In Progress').length,
        icon: Play,
        colorClass: 'text-blue-500',
        bgClass: 'bg-blue-500/10',
        borderActive: 'border-blue-500/40',
        glowClass: 'shadow-blue-500/20',
        ringClass: 'ring-blue-500/20',
    },
    {
        label: 'On Hold',
        status: 'On Hold',
        value: preStatusFiltered.value.filter(t => t.status === 'On Hold').length,
        icon: Pause,
        colorClass: 'text-amber-500',
        bgClass: 'bg-amber-500/10',
        borderActive: 'border-amber-500/40',
        glowClass: 'shadow-amber-500/20',
        ringClass: 'ring-amber-500/20',
    },
    {
        label: 'Resolved',
        status: 'Resolved',
        value: preStatusFiltered.value.filter(t => t.status === 'Resolved').length,
        icon: CheckCircle2,
        colorClass: 'text-emerald-500',
        bgClass: 'bg-emerald-500/10',
        borderActive: 'border-emerald-500/40',
        glowClass: 'shadow-emerald-500/20',
        ringClass: 'ring-emerald-500/20',
    },
    {
        label: 'Closed',
        status: 'Closed',
        value: preStatusFiltered.value.filter(t => t.status === 'Closed').length,
        icon: Ban,
        colorClass: 'text-slate-500',
        bgClass: 'bg-slate-500/10',
        borderActive: 'border-slate-500/40',
        glowClass: 'shadow-slate-500/20',
        ringClass: 'ring-slate-500/20',
    },
]);

const isCreateModalOpen = ref(false);
const currentStep = ref(1);

const isDetailModalOpen = ref(false);
const selectedTicket = ref<typeof props.tickets[0] | null>(null);
const editingTicket = ref<typeof props.tickets[0] | null>(null);
const attachmentPreview = ref<string | null>(null);

// ── History tab watches (depend on isDetailModalOpen + selectedTicket) ─────
watch(
    () => detailTab.value,
    (tab) => {
        if (tab === 'history' && selectedTicket.value) {
            fetchHistory(selectedTicket.value.numericId);
        }
    },
);

watch(
    () => isDetailModalOpen.value,
    (open) => {
        if (!open) {
            detailTab.value = 'overview';
            historyFetchedFor.value = null;
            activityLog.value = [];
        }
    },
);

const isDraggingOver = ref(false);

const setAttachmentFile = (file: File | null) => {
    form.attachment = file;
    attachmentPreview.value = file ? URL.createObjectURL(file) : null;
};

const onAttachmentChange = (e: Event) => {
    setAttachmentFile((e.target as HTMLInputElement).files?.[0] ?? null);
};

const onAttachmentDrop = (e: DragEvent) => {
    isDraggingOver.value = false;
    const file = e.dataTransfer?.files?.[0] ?? null;
    if (file && file.type.startsWith('image/')) {
        setAttachmentFile(file);
    }
};

const removeAttachment = () => {
    form.attachment = null;
    attachmentPreview.value = null;
};

const openDetailModal = (ticket: typeof props.tickets[0]) => {
    selectedTicket.value = ticket;
    isDetailModalOpen.value = true;
};

const openEditModal = (ticket: typeof props.tickets[0]) => {
    editingTicket.value = ticket;
    form.title = ticket.title;
    form.description = ticket.description ?? '';
    form.priority = ticket.priority;
    form.category = ticket.category;
    form.status = ticket.status;
    form.handler_ids = [...ticket.handlerIds];
    form.solution = ticket.solution ?? '';
    form.attachment = null;
    attachmentPreview.value = ticket.attachmentUrl ?? null;
    currentStep.value = 1;
    isCreateModalOpen.value = true;
};

const form = useForm({
    title: '',
    description: '',
    priority: 'Medium',
    category: 'Software',
    status: 'Open',
    handler_ids: [] as number[],
    solution: '',
    attachment: null as File | null,
});

const handlerRequired = computed(() =>
    ['In Progress', 'On Hold', 'Resolved'].includes(form.status)
);

const isEmptyHtml = (html: string): boolean => !html.replace(/<[^>]*>/g, '').trim();

const handlerSearch = ref('');

// ── Assign Handler modal ───────────────────────────────────────────────────
const isAssignModalOpen = ref(false);
const assigningTicket = ref<typeof props.tickets[0] | null>(null);
const assignHandlerSearch = ref('');
// When the ticket is Open, admin must also pick a new status before saving
const assignStatusOverride = ref<string>('In Progress');

const assignForm = useForm({
    handler_ids: [] as number[],
    solution: '',
});

const filteredAssignUsers = computed(() => {
    if (!assignHandlerSearch.value.trim()) return props.users;
    const q = assignHandlerSearch.value.toLowerCase();
    return props.users.filter(u => u.name.toLowerCase().includes(q));
});

// defaultStatus is used when opening from an Open ticket (e.g. 'Resolved' via Mark as Resolved)
const openAssignModal = (ticket: typeof props.tickets[0], defaultStatus = 'In Progress') => {
    assigningTicket.value = ticket;
    assignForm.handler_ids = [...ticket.handlerIds];
    assignHandlerSearch.value = '';
    assignStatusOverride.value = defaultStatus;
    isAssignModalOpen.value = true;
};

const submitAssign = () => {
    if (!assigningTicket.value) return;
    const ticket = assigningTicket.value;
    const isOpen = ticket.status === 'Open';
    assignForm
        .transform(data => ({
            handler_ids: data.handler_ids,
            ...(isOpen ? { status: assignStatusOverride.value } : {}),
            ...(assignStatusOverride.value === 'Resolved' || ticket.status === 'Resolved'
                ? { solution: data.solution }
                : {}),
        }))
        .patch(route('tickets.handlers.update', { ticket: ticket.numericId }), {
            preserveScroll: true,
            onSuccess: () => {
                isAssignModalOpen.value = false;
                assigningTicket.value = null;
            },
        });
};

watch(isAssignModalOpen, (val) => {
    if (!val) {
        assignHandlerSearch.value = '';
        assigningTicket.value = null;
        assignStatusOverride.value = 'In Progress';
        assignForm.solution = '';
    }
});
// ──────────────────────────────────────────────────────────────────────────

// ── Quick status actions ───────────────────────────────────────────────────
const statusProcessing = ref<number | null>(null);

const updateStatus = (ticket: typeof props.tickets[0], status: string) => {
    statusProcessing.value = ticket.numericId;
    router.patch(
        route('tickets.status.update', { ticket: ticket.numericId }),
        { status },
        {
            preserveScroll: true,
            onFinish: () => { statusProcessing.value = null; },
        }
    );
};
// ──────────────────────────────────────────────────────────────────────────

// ── Bulk actions ──────────────────────────────────────────────────────────
// Bulk Change Status
const isBulkStatusModalOpen = ref(false);
const bulkStatusValue = ref('In Progress');
const bulkStatusHandlerSearch = ref('');
const bulkStatusHandlerIds = ref<number[]>([]);
const bulkStatusSolution = ref('');

const filteredBulkStatusUsers = computed(() => {
    if (!bulkStatusHandlerSearch.value.trim()) return props.users;
    const q = bulkStatusHandlerSearch.value.toLowerCase();
    return props.users.filter(u => u.name.toLowerCase().includes(q));
});

const bulkStatusForm = useForm({ status: '' });

const openBulkStatusModal = () => {
    bulkStatusValue.value = 'In Progress';
    bulkStatusHandlerIds.value = [];
    bulkStatusHandlerSearch.value = '';
    isBulkStatusModalOpen.value = true;
};

watch(bulkStatusValue, (val) => {
    if (val === 'Open') {
        bulkStatusHandlerIds.value = [];
        bulkStatusHandlerSearch.value = '';
    }
    if (val !== 'Resolved') {
        bulkStatusSolution.value = '';
    }
});

watch(isBulkStatusModalOpen, (val) => {
    if (!val) {
        bulkStatusHandlerIds.value = [];
        bulkStatusHandlerSearch.value = '';
        bulkStatusSolution.value = '';
    }
});

const submitBulkStatus = () => {
    bulkStatusForm
        .transform(() => ({
            status: bulkStatusValue.value,
            ticket_ids: selectedIds.value,
            handler_ids: bulkStatusValue.value === 'Open' ? [] : bulkStatusHandlerIds.value,
            ...(bulkStatusValue.value === 'Resolved' ? { solution: bulkStatusSolution.value } : {}),
        }))
        .patch(route('tickets.bulk.status'), {
            preserveScroll: true,
            onSuccess: () => {
                isBulkStatusModalOpen.value = false;
                selectedIds.value = [];
            },
        });
};

// Bulk Delete
const isBulkDeleteConfirmOpen = ref(false);
const bulkDeleteForm = useForm({});

const submitBulkDelete = () => {
    bulkDeleteForm
        .transform(() => ({ ticket_ids: selectedIds.value }))
        .delete(route('tickets.bulk.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                isBulkDeleteConfirmOpen.value = false;
                selectedIds.value = [];
            },
        });
};
// ──────────────────────────────────────────────────────────────────────────

// ── Single Delete modal ────────────────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const deleteTarget = ref<typeof props.tickets[0] | null>(null);
const deleteForm = useForm({});

const openDeleteModal = (ticket: typeof props.tickets[0]) => {
    deleteTarget.value = ticket;
    isDeleteModalOpen.value = true;
};

const submitDelete = () => {
    if (!deleteTarget.value) return;
    deleteForm.delete(route('tickets.destroy', deleteTarget.value.numericId), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            deleteTarget.value = null;
        },
    });
};
// ──────────────────────────────────────────────────────────────────────────

// ── Change Status modal ────────────────────────────────────────────────────
const ALL_STATUSES = ['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'] as const;

const isChangeStatusModalOpen = ref(false);
const changeStatusTicket = ref<typeof props.tickets[0] | null>(null);
const changeStatusValue = ref('');
const changeStatusHandlerSearch = ref('');

const changeStatusForm = useForm({
    handler_ids: [] as number[],
    solution: '',
});

const filteredChangeStatusUsers = computed(() => {
    if (!changeStatusHandlerSearch.value.trim()) return props.users;
    const q = changeStatusHandlerSearch.value.toLowerCase();
    return props.users.filter(u => u.name.toLowerCase().includes(q));
});

// Statuses available to pick (all except the ticket's current status)
const changeStatusOptions = computed(() =>
    ALL_STATUSES.filter(s => s !== changeStatusTicket.value?.status)
);

watch(changeStatusValue, (val) => {
    if (val === 'Open') {
        changeStatusForm.handler_ids = [];
        changeStatusHandlerSearch.value = '';
    }
    if (val !== 'Resolved') {
        changeStatusForm.solution = '';
    }
});

const openChangeStatusModal = (ticket: typeof props.tickets[0]) => {
    changeStatusTicket.value = ticket;
    changeStatusHandlerSearch.value = '';
    // Default to the first non-'Open' option so the watcher never clears pre-existing handlers
    changeStatusValue.value = changeStatusOptions.value.find(s => s !== 'Open') ?? changeStatusOptions.value[0] ?? '';
    // Set handlers AFTER status so any watcher side-effects have already resolved
    changeStatusForm.handler_ids = [...ticket.handlerIds];
    changeStatusForm.solution = '';
    isChangeStatusModalOpen.value = true;
};

const submitChangeStatus = () => {
    if (!changeStatusTicket.value) return;
    const ticket = changeStatusTicket.value;
    changeStatusForm
        .transform(data => ({
            status: changeStatusValue.value,
            ...(data.handler_ids.length > 0 ? { handler_ids: data.handler_ids } : {}),
            ...(changeStatusValue.value === 'Resolved' ? { solution: data.solution } : {}),
        }))
        .patch(route('tickets.status.update', { ticket: ticket.numericId }), {
            preserveScroll: true,
            onSuccess: () => {
                isChangeStatusModalOpen.value = false;
                changeStatusTicket.value = null;
            },
        });
};

watch(isChangeStatusModalOpen, (val) => {
    if (!val) {
        changeStatusTicket.value = null;
        changeStatusValue.value = '';
        changeStatusHandlerSearch.value = '';
        changeStatusForm.solution = '';
    }
});
// ──────────────────────────────────────────────────────────────────────────

const handlerTooltip = ref<{ name: string; x: number; y: number } | null>(null);
const showHandlerTooltip = (e: MouseEvent, name: string) => {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    handlerTooltip.value = { name, x: rect.left + rect.width / 2, y: rect.top };
};
const hideHandlerTooltip = () => { handlerTooltip.value = null; };

const reporterTooltip = ref<{ name: string; x: number; y: number } | null>(null);
const showReporterTooltip = (e: MouseEvent, name: string) => {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    reporterTooltip.value = { name, x: rect.left + rect.width / 2, y: rect.top };
};
const hideReporterTooltip = () => { reporterTooltip.value = null; };

// ── Keyboard shortcut: '/' focuses search ─────────────────────────────────
const searchInputRef = ref<HTMLInputElement | null>(null);
const handleGlobalKeydown = (e: KeyboardEvent) => {
    if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA' && !(document.activeElement as HTMLElement)?.isContentEditable) {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
};
onMounted(() => document.addEventListener('keydown', handleGlobalKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleGlobalKeydown));
const filteredUsers = computed(() => {
    if (!handlerSearch.value.trim()) return props.users;
    const q = handlerSearch.value.toLowerCase();
    return props.users.filter(u => u.name.toLowerCase().includes(q));
});

// ── Excel Export ───────────────────────────────────────────────────────────
const isExporting = ref(false);


const exportToExcel = async () => {
    if (isExporting.value) return;
    isExporting.value = true;

    try {
        const ExcelJS = (await import('exceljs')).default;
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'IMS';
        workbook.created = new Date();

        const sheet = workbook.addWorksheet('Tickets', {
            views: [{ state: 'frozen', ySplit: 1 }],
        });

        // ── Column definitions ──────────────────────────────────────────
        sheet.columns = [
            { key: 'ticketId',   header: 'Ticket ID',   width: 13 },
            { key: 'title',      header: 'Title',        width: 42 },
            { key: 'status',     header: 'Status',       width: 14 },
            { key: 'priority',   header: 'Priority',     width: 12 },
            { key: 'category',   header: 'Category',     width: 14 },
            { key: 'reporter',   header: 'Reporter',     width: 22 },
            { key: 'handlers',   header: 'Handler(s)',   width: 28 },
            { key: 'createdAt',  header: 'Created At',   width: 22 },
            { key: 'resolvedAt', header: 'Resolved At',  width: 22 },
            { key: 'solution',   header: 'Solution',     width: 50 },
        ];

        // ── Header row styling ──────────────────────────────────────────
        const headerRow = sheet.getRow(1);
        headerRow.font   = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
        headerRow.fill   = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } };
        headerRow.height = 22;
        headerRow.alignment = { vertical: 'middle', horizontal: 'left' };
        headerRow.eachCell(cell => {
            cell.border = {
                bottom: { style: 'thin', color: { argb: 'FF334155' } },
            };
        });

        // ── Status / priority color maps ────────────────────────────────
        const statusColors: Record<string, string> = {
            'Open':        'FFFEE2E2',
            'In Progress': 'FFFEF9C3',
            'On Hold':     'FFFCE7F3',
            'Resolved':    'FFD1FAE5',
            'Closed':      'FFF1F5F9',
        };
        const priorityColors: Record<string, string> = {
            'Critical': 'FFFEE2E2',
            'High':     'FFFFEEDD',
            'Medium':   'FFDBEAFE',
            'Low':      'FFF8FAFC',
        };

        // Strip HTML tags from rich-text solution field
        const stripHtml = (html: string | null | undefined): string => {
            if (!html) return '—';
            return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').trim() || '—';
        };

        // ── Data rows ──────────────────────────────────────────────────
        sortedTickets.value.forEach((t, idx) => {
            const row = sheet.addRow({
                ticketId:  t.id,
                title:     t.title,
                status:    t.status,
                priority:  t.priority,
                category:  t.category,
                reporter:  t.reporter,
                handlers:   t.handlers.map(h => h.name).join(', ') || '—',
                createdAt:  t.createdAtFormatted,
                resolvedAt: t.resolvedAtFormatted ?? '—',
                solution:   stripHtml((t as any).solution),
            });

            row.height = 18;
            row.font   = { size: 10 };
            row.alignment = { vertical: 'middle', wrapText: false };

            // Alternating row background
            const rowBg = idx % 2 === 0 ? 'FFFFFFFF' : 'FFF8FAFC';
            row.eachCell({ includeEmpty: true }, cell => {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: rowBg } };
                cell.border = {
                    bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } },
                };
            });

            // Status cell colour
            const statusCell = row.getCell('status');
            if (statusColors[t.status]) {
                statusCell.fill   = { type: 'pattern', pattern: 'solid', fgColor: { argb: statusColors[t.status] } };
                statusCell.font   = { size: 10, bold: true };
            }

            // Priority cell colour
            const priorityCell = row.getCell('priority');
            if (priorityColors[t.priority]) {
                priorityCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: priorityColors[t.priority] } };
                priorityCell.font = { size: 10, bold: true };
            }

            // Resolved at cell — subtle green tint when set
            if (t.resolvedAtFormatted) {
                const resolvedCell = row.getCell('resolvedAt');
                resolvedCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD1FAE5' } };
                resolvedCell.font = { size: 10, color: { argb: 'FF065F46' } };
            }

            // Solution cell — wrap text and subtle tint when present
            const solutionCell = row.getCell('solution');
            solutionCell.alignment = { vertical: 'top', wrapText: true };
            if ((t as any).solution) {
                solutionCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFBEB' } };
            }
        });

        // ── Auto-filter on header ───────────────────────────────────────
        sheet.autoFilter = { from: 'A1', to: 'J1' };

        // ── Download ───────────────────────────────────────────────────
        const dateStr = new Date().toISOString().slice(0, 10);
        const buffer  = await workbook.xlsx.writeBuffer();
        const blob    = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url     = URL.createObjectURL(blob);
        const a       = document.createElement('a');
        a.href        = url;
        a.download    = `tickets-${dateStr}.xlsx`;
        a.click();
        URL.revokeObjectURL(url);
    } finally {
        isExporting.value = false;
    }
};
// ──────────────────────────────────────────────────────────────────────────

const submit = () => {
    if (editingTicket.value) {
        form.transform(data => ({ ...data, _method: 'put' })).post(
            route('tickets.update', { ticket: editingTicket.value!.numericId }),
            {
                forceFormData: true,
                onSuccess: () => {
                    isCreateModalOpen.value = false;
                    form.reset();
                    attachmentPreview.value = null;
                    currentStep.value = 1;
                    editingTicket.value = null;
                },
            }
        );
    } else {
        form.post(route('tickets.store'), {
            forceFormData: true,
            onSuccess: () => {
                isCreateModalOpen.value = false;
                form.reset();
                currentStep.value = 1;
            },
        });
    }
};

watch(isCreateModalOpen, (val) => {
    if (val && !editingTicket.value) {
        currentStep.value = 1;
        form.reset();
        attachmentPreview.value = null;
    }
    if (!val) {
        editingTicket.value = null;
        attachmentPreview.value = null;
        handlerSearch.value = '';
    }
});

const search = ref('');
const currentStatus = ref('All');
const currentPriority = ref('All');
const selectedIds = ref<number[]>([]);
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
    { name: 'Low',      icon: Circle,       color: 'text-muted-foreground', activeBg: 'bg-slate-100 dark:bg-slate-800',    activeText: 'text-slate-600 dark:text-slate-300' },
    { name: 'Medium',   icon: ArrowUpCircle, color: 'text-blue-500',        activeBg: 'bg-blue-500/10',                    activeText: 'text-blue-600 dark:text-blue-400' },
    { name: 'High',     icon: AlertTriangle, color: 'text-orange-500',      activeBg: 'bg-orange-500/10',                  activeText: 'text-orange-600 dark:text-orange-400' },
    { name: 'Critical', icon: AlertCircle,   color: 'text-destructive',     activeBg: 'bg-destructive/10',                 activeText: 'text-destructive' },
];

const isAllSelected = computed(() =>
    sortedTickets.value.length > 0 &&
    sortedTickets.value.every(t => selectedIds.value.includes(t.numericId))
);

const isIndeterminate = computed(() =>
    selectedIds.value.length > 0 && !isAllSelected.value
);

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = sortedTickets.value.map(t => t.numericId);
    }
};

const toggleTicket = (numericId: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(numericId)) {
            selectedIds.value = [...selectedIds.value, numericId];
        }
    } else {
        selectedIds.value = selectedIds.value.filter(id => id !== numericId);
    }
};

type SortKey = 'id' | 'title' | 'status' | 'priority' | 'createdAt' | 'reporter' | 'handlers';
const sortKey = ref<SortKey | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');

const priorityOrder: Record<string, number> = { Critical: 4, High: 3, Medium: 2, Low: 1 };
const statusOrder: Record<string, number> = { Open: 1, 'In Progress': 2, 'On Hold': 3, Resolved: 4, Closed: 5 };

const dateFrom = ref('');
const dateTo   = ref('');

// Base: search + date filters only (no status, no priority).
// Used as the starting point for the two filter-group counts below.
const searchDateFiltered = computed(() => {
    let base = props.tickets;

    if (dateFrom.value) base = base.filter(t => t.createdAtRaw >= dateFrom.value);
    if (dateTo.value)   base = base.filter(t => t.createdAtRaw <= dateTo.value);

    if (search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        base = base.filter(t =>
            t.id.toLowerCase().includes(q) ||
            t.title.toLowerCase().includes(q) ||
            t.reporter.toLowerCase().includes(q) ||
            t.handlers.some(h => h.name.toLowerCase().includes(q))
        );
    }

    return base;
});

// Excludes status filter → drives stat-card counts (they ARE the status filter).
const preStatusFiltered = computed(() => {
    let base = searchDateFiltered.value;
    if (currentPriority.value !== 'All') {
        base = base.filter(t => t.priority === currentPriority.value);
    }
    return base;
});

// Excludes priority filter → drives priority-chip counts (they ARE the priority filter).
const prePriorityFiltered = computed(() => {
    let base = searchDateFiltered.value;
    if (currentStatus.value !== 'All') {
        base = base.filter(t => t.status === currentStatus.value);
    }
    return base;
});

const sortedTickets = computed(() => {
    let base = preStatusFiltered.value;

    // Status filter
    if (currentStatus.value !== 'All') {
        base = base.filter(t => t.status === currentStatus.value);
    }

    if (!sortKey.value) return base;
    return [...base].sort((a, b) => {
        let aVal: any = a[sortKey.value!];
        let bVal: any = b[sortKey.value!];
        if (sortKey.value === 'priority') { aVal = priorityOrder[aVal] ?? 0; bVal = priorityOrder[bVal] ?? 0; }
        if (sortKey.value === 'status') { aVal = statusOrder[aVal] ?? 0; bVal = statusOrder[bVal] ?? 0; }
        if (sortKey.value === 'handlers') { aVal = (a.handlers as any[]).length; bVal = (b.handlers as any[]).length; }
        if (aVal < bVal) return sortDir.value === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

// ── Pagination ─────────────────────────────────────────────────────────────
const PAGE_SIZE_OPTIONS = [5, 10, 25, 50] as const;
const pageSize = ref(10);
const currentPage = ref(1);

watch([search, currentStatus, currentPriority, dateFrom, dateTo, sortKey, sortDir, pageSize], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => Math.max(1, Math.ceil(sortedTickets.value.length / pageSize.value)));

const paginatedTickets = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return sortedTickets.value.slice(start, start + pageSize.value);
});

const pageRange = computed((): (number | '...')[] => {
    const total = totalPages.value;
    const cur = currentPage.value;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages: (number | '...')[] = [1];
    if (cur > 3) pages.push('...');
    for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) {
        pages.push(i);
    }
    if (cur < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});
// ──────────────────────────────────────────────────────────────────────────

// Count per priority for badge labels
const priorityCounts = computed(() => {
    // Count from prePriorityFiltered (status + search + date applied, NOT priority)
    // so the chips show accurate counts for the currently visible status.
    const base = prePriorityFiltered.value;
    const counts: Record<string, number> = { All: base.length };
    for (const t of base) {
        counts[t.priority] = (counts[t.priority] ?? 0) + 1;
    }
    return counts;
});

const toggleSort = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
};


const getInitials = (name: string) => {
    if (name === 'Unassigned') return 'UN';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

// ── Activity history helpers ───────────────────────────────────────────────
type Component = typeof History;

const ACTIVITY_CONFIG: Record<string, { icon: Component; classes: string; verb: string }> = {
    created:           { icon: FilePenLine, classes: 'bg-primary/10 border-primary/20 text-primary', verb: 'created this ticket' },
    status_changed:    { icon: GitBranch,   classes: 'bg-blue-500/10 border-blue-500/20 text-blue-500', verb: 'changed status' },
    priority_changed:  { icon: Flag,        classes: 'bg-amber-500/10 border-amber-500/20 text-amber-500', verb: 'changed priority' },
    solution_updated:  { icon: CheckCircle2, classes: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500', verb: 'updated the solution' },
    handler_assigned:  { icon: UserPlus,    classes: 'bg-violet-500/10 border-violet-500/20 text-violet-500', verb: 'assigned handler(s)' },
    handler_removed:   { icon: UserMinus,   classes: 'bg-rose-500/10 border-rose-500/20 text-rose-500', verb: 'removed handler(s)' },
    comment_posted:    { icon: MessageSquare, classes: 'bg-sky-500/10 border-sky-500/20 text-sky-500', verb: 'posted a comment' },
    comment_deleted:   { icon: Trash2,      classes: 'bg-rose-500/10 border-rose-500/20 text-rose-500', verb: 'deleted a comment' },
    comment_pinned:    { icon: Pin,         classes: 'bg-amber-500/10 border-amber-500/20 text-amber-500', verb: 'pinned a comment' },
    comment_unpinned:  { icon: PinOff,      classes: 'bg-muted border-border/50 text-muted-foreground', verb: 'unpinned a comment' },
    reaction_added:    { icon: Smile,       classes: 'bg-pink-500/10 border-pink-500/20 text-pink-500', verb: 'reacted' },
    reaction_removed:  { icon: Smile,       classes: 'bg-muted border-border/50 text-muted-foreground', verb: 'removed a reaction' },
};

const getActivityIcon = (action: string): Component =>
    (ACTIVITY_CONFIG[action]?.icon ?? History) as Component;

const getActivityIconClass = (action: string): string =>
    ACTIVITY_CONFIG[action]?.classes ?? 'bg-muted border-border/50 text-muted-foreground';

const getActivityLabel = (entry: ActivityEntry): string => {
    const verb = ACTIVITY_CONFIG[entry.action]?.verb ?? entry.action.replace(/_/g, ' ');
    if (entry.action === 'handler_assigned' && entry.newValue) {
        return `assigned ${entry.newValue}`;
    }
    if (entry.action === 'handler_removed' && entry.oldValue) {
        return `removed ${entry.oldValue}`;
    }
    if (entry.action === 'reaction_added' && entry.newValue) {
        return `reacted with ${entry.newValue}`;
    }
    if (['comment_posted', 'comment_deleted'].includes(entry.action)) {
        const snippet = (entry.newValue ?? entry.oldValue)?.trim();
        return snippet ? `${verb}: "${snippet}"` : verb;
    }
    return verb;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Open': return 'bg-rose-500/15 text-rose-500 border-rose-500/30';
        case 'In Progress': return 'bg-blue-500/15 text-blue-500 border-blue-500/30';
        case 'On Hold': return 'bg-amber-500/15 text-amber-500 border-amber-500/30';
        case 'Resolved': return 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30';
        case 'Closed': return 'bg-slate-500/15 text-slate-500 border-slate-500/30';
        default: return 'bg-secondary text-secondary-foreground';
    }
};

const getStatusLeftBorder = (status: string) => {
    switch (status) {
        case 'Open': return 'border-l-rose-500';
        case 'In Progress': return 'border-l-blue-500';
        case 'On Hold': return 'border-l-amber-500';
        case 'Resolved': return 'border-l-emerald-500';
        case 'Closed': return 'border-l-slate-400';
        default: return 'border-l-border';
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
        case 'Critical': return 'bg-rose-500/15 text-rose-500 border border-rose-500/25';
        case 'High':     return 'bg-orange-500/15 text-orange-500 border border-orange-500/25';
        case 'Medium':   return 'bg-blue-500/15 text-blue-500 border border-blue-500/25';
        case 'Low':      return 'bg-slate-500/10 text-slate-500 border border-slate-500/20';
        default:         return 'bg-muted text-muted-foreground border border-border';
    }
};
</script>

<template>
    <Head title="Tickets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 md:gap-6 md:p-6">

            <!-- ── Session Expired Banner ─────────────────────── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                leave-active-class="transition-all duration-200 ease-in"
                enter-from-class="-translate-y-2 opacity-0"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div
                    v-if="sessionExpiredBanner"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-500/25 bg-amber-50 dark:bg-amber-500/10 px-4 py-3"
                >
                    <AlertTriangle class="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">Security token refreshed</p>
                        <p class="text-xs text-amber-600/80 dark:text-amber-400/70 mt-0.5">Your form is still open — just click the button again to complete your action.</p>
                    </div>
                    <button @click="sessionExpiredBanner = false" class="shrink-0 text-amber-500 hover:text-amber-700 dark:hover:text-amber-300 transition-colors">
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </Transition>

            <!-- ── Page Header ─────────────────────────────────── -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Tickets</h2>
                        <span
                            v-if="ticketStats.find(s => s.status === 'Open')?.value ?? 0 > 0"
                            class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 border border-rose-500/20 px-2 py-0.5 text-[10px] font-bold text-rose-500"
                        >
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute h-full w-full rounded-full bg-rose-400 opacity-75" />
                                <span class="relative rounded-full h-1.5 w-1.5 bg-rose-500" />
                            </span>
                            {{ ticketStats.find(s => s.status === 'Open')?.value }} open
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground sm:text-sm">Manage and track all incident tickets.</p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Search -->
                    <div class="relative flex-1 sm:w-64 sm:flex-none">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            ref="searchInputRef"
                            v-model="search"
                            type="text"
                            placeholder="Search tickets…"
                            class="flex h-9 w-full rounded-lg border border-input bg-background py-1 pl-9 pr-8 text-sm shadow-sm placeholder:text-muted-foreground/50 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
                        />
                        <!-- Shortcut hint (empty state) -->
                        <kbd
                            v-if="!search"
                            class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 hidden sm:inline-flex items-center rounded border border-border/60 bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground/60 select-none"
                        >/</kbd>
                        <!-- Clear button (has text) -->
                        <button
                            v-else
                            type="button"
                            @click="search = ''; searchInputRef?.focus()"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 flex h-4 w-4 items-center justify-center rounded text-muted-foreground/40 hover:text-foreground transition-colors"
                            aria-label="Clear search"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Export -->
                    <button
                        @click="exportToExcel"
                        :disabled="isExporting || sortedTickets.length === 0"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border/60 bg-background px-3 text-xs font-semibold text-muted-foreground shadow-sm transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40 disabled:pointer-events-none shrink-0"
                        title="Export current view to Excel"
                    >
                        <span v-if="isExporting" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        <Download v-else class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Export</span>
                    </button>

                    <!-- New Ticket -->
                    <Dialog v-model:open="isCreateModalOpen">
                        <DialogTrigger as-child>
                            <Button class="h-9 shrink-0 gap-1.5 px-3 shadow-sm sm:px-4">
                                <Plus class="h-4 w-4" />
                                <span class="hidden sm:inline text-xs font-bold uppercase tracking-wide">New Ticket</span>
                            </Button>
                        </DialogTrigger>

                        <!-- Create / Edit Modal -->
                        <DialogContent class="sm:max-w-[550px] p-0 overflow-hidden border-none shadow-2xl flex flex-col max-h-[92dvh]">
                            <form @submit.prevent="submit" class="flex flex-col min-h-0 flex-1">
                                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                                    <DialogHeader>
                                        <div class="flex items-center justify-between mb-2">
                                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                                {{ editingTicket ? 'Edit Ticket' : 'Incident Report' }}
                                            </Badge>
                                            <div class="flex gap-1">
                                                <div
                                                    v-for="i in 2"
                                                    :key="i"
                                                    class="h-1.5 w-8 rounded-full transition-all duration-300"
                                                    :class="currentStep >= i ? 'bg-primary' : 'bg-primary/20'"
                                                />
                                            </div>
                                        </div>
                                        <DialogTitle class="text-lg font-bold tracking-tight">
                                            <template v-if="editingTicket">
                                                {{ currentStep === 1 ? 'Update Details' : 'Update Category & Status' }}
                                            </template>
                                            <template v-else>
                                                {{ currentStep === 1 ? "What's the issue?" : 'Set Importance' }}
                                            </template>
                                        </DialogTitle>
                                        <DialogDescription class="text-muted-foreground/80 text-xs">
                                            <template v-if="editingTicket">
                                                {{ currentStep === 1 ? 'Update the ticket title and description.' : 'Adjust the category, priority, and status.' }}
                                            </template>
                                            <template v-else>
                                                {{ currentStep === 1 ? 'Describe the incident so we can help you resolve it.' : 'Categorize and prioritize this ticket for the team.' }}
                                            </template>
                                        </DialogDescription>
                                    </DialogHeader>
                                </div>

                                <div class="modal-body px-5 py-5 min-h-[280px] overflow-y-auto flex-1">
                                    <!-- Step 1 -->
                                    <div v-if="currentStep === 1" class="grid gap-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                        <div class="grid gap-2">
                                            <Label for="title" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Ticket Title</Label>
                                            <Input
                                                id="title"
                                                v-model="form.title"
                                                placeholder="e.g., Network outage in Office A"
                                                required
                                                class="py-5 text-sm"
                                            />
                                            <span v-if="form.errors.title" class="text-xs text-destructive font-medium">{{ form.errors.title }}</span>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                                Description <span class="normal-case font-normal text-muted-foreground/60">(optional)</span>
                                            </Label>
                                            <RichTextEditor v-model="form.description" placeholder="Provide more context about the issue…" />
                                            <span v-if="form.errors.description" class="text-xs text-destructive font-medium">{{ form.errors.description }}</span>
                                        </div>

                                        <!-- Attachment -->
                                        <div class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                                Attachment <span class="normal-case font-normal text-muted-foreground/60">(optional · image · max 4 MB)</span>
                                            </Label>
                                            <div v-if="attachmentPreview" class="relative rounded-lg overflow-hidden border border-border/50 bg-muted/20">
                                                <img :src="attachmentPreview" alt="preview" class="w-full max-h-40 object-contain" />
                                                <button
                                                    type="button"
                                                    @click="removeAttachment"
                                                    class="absolute top-2 right-2 h-6 w-6 rounded-full bg-background/90 border border-border/60 flex items-center justify-center text-muted-foreground hover:text-destructive transition-colors shadow-sm"
                                                >
                                                    <X class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <label
                                                v-else
                                                @dragover.prevent="isDraggingOver = true"
                                                @dragleave.prevent="isDraggingOver = false"
                                                @drop.prevent="onAttachmentDrop"
                                                :class="[
                                                    'flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-5 cursor-pointer transition-all group',
                                                    isDraggingOver
                                                        ? 'border-primary bg-primary/8 scale-[1.01]'
                                                        : 'border-muted-foreground/20 bg-muted/10 hover:border-primary/40 hover:bg-primary/5'
                                                ]"
                                            >
                                                <div :class="['h-8 w-8 rounded-full flex items-center justify-center transition-colors', isDraggingOver ? 'bg-primary/15' : 'bg-muted/60 group-hover:bg-primary/10']">
                                                    <Upload :class="['h-4 w-4 transition-colors', isDraggingOver ? 'text-primary' : 'text-muted-foreground group-hover:text-primary']" />
                                                </div>
                                                <div class="text-center">
                                                    <p :class="['text-xs font-semibold transition-colors', isDraggingOver ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground']">
                                                        {{ isDraggingOver ? 'Drop to attach' : 'Click or drag & drop an image' }}
                                                    </p>
                                                    <p class="text-[10px] text-muted-foreground/60 mt-0.5">PNG, JPG, GIF, WEBP · max 4 MB</p>
                                                </div>
                                                <input type="file" accept="image/*" class="sr-only" @change="onAttachmentChange" />
                                            </label>
                                            <span v-if="form.errors.attachment" class="text-xs text-destructive font-medium">{{ form.errors.attachment }}</span>
                                        </div>
                                    </div>

                                    <!-- Step 2 -->
                                    <div v-if="currentStep === 2" class="grid gap-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                        <div class="grid gap-3">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Category</Label>
                                            <div class="grid grid-cols-3 gap-2">
                                                <button
                                                    v-for="cat in categories"
                                                    :key="cat.name"
                                                    type="button"
                                                    @click="form.category = cat.name"
                                                    :class="[
                                                        'flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl border-2 transition-all relative group',
                                                        form.category === cat.name
                                                            ? 'border-primary bg-primary/5 text-primary shadow-sm'
                                                            : 'border-muted hover:border-primary/30 hover:bg-muted/50 text-muted-foreground'
                                                    ]"
                                                >
                                                    <component :is="cat.icon" class="h-4 w-4" />
                                                    <span class="text-[10px] font-bold uppercase truncate w-full text-center">{{ cat.name }}</span>
                                                    <div v-if="form.category === cat.name" class="absolute -top-1.5 -right-1.5">
                                                        <CheckCircle2 class="h-4 w-4 fill-primary text-white" />
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="grid gap-3">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Priority Level</Label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button
                                                    v-for="prio in priorities"
                                                    :key="prio.name"
                                                    type="button"
                                                    @click="form.priority = prio.name"
                                                    :class="[
                                                        'flex items-center gap-2.5 p-3 rounded-xl border-2 transition-all relative group',
                                                        form.priority === prio.name
                                                            ? 'border-primary bg-primary/5 shadow-sm'
                                                            : 'border-muted hover:border-primary/30 hover:bg-muted/50'
                                                    ]"
                                                >
                                                    <div :class="['p-1.5 rounded-lg', form.priority === prio.name ? 'bg-background shadow-sm' : 'bg-muted/60']">
                                                        <component :is="prio.icon" class="h-3.5 w-3.5" :class="prio.color" />
                                                    </div>
                                                    <div class="flex flex-col items-start min-w-0">
                                                        <span :class="['text-xs font-bold', form.priority === prio.name ? 'text-primary' : 'text-muted-foreground']">{{ prio.name }}</span>
                                                        <span class="text-[10px] text-muted-foreground/60 leading-none mt-0.5 truncate">
                                                            {{ prio.name === 'Critical' ? 'Immediate' : prio.name === 'High' ? 'Fast response' : prio.name === 'Medium' ? 'Standard' : 'Non-urgent' }}
                                                        </span>
                                                    </div>
                                                    <CheckCircle2 v-if="form.priority === prio.name" class="ml-auto h-3.5 w-3.5 shrink-0 fill-primary text-white" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Status -->
                                        <div class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Status</Label>
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-for="s in ['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed']"
                                                    :key="s"
                                                    type="button"
                                                    @click="form.status = s"
                                                    :class="[
                                                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold border-2 transition-all',
                                                        form.status === s
                                                            ? [getStatusColor(s), 'border-current shadow-sm']
                                                            : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                                    ]"
                                                >
                                                    <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                                    {{ s }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Handlers -->
                                        <div v-if="form.status !== 'Open'" class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                                Handlers
                                                <span v-if="handlerRequired" class="ml-1 text-destructive">*</span>
                                                <span v-else class="normal-case font-normal text-muted-foreground/60">(optional)</span>
                                            </Label>
                                            <div v-if="form.handler_ids.length > 0" class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="id in form.handler_ids"
                                                    :key="id"
                                                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 border border-primary/20 pl-1.5 pr-1 py-0.5 text-[11px] font-semibold text-primary"
                                                >
                                                    <span class="h-4 w-4 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[9px] font-bold shrink-0">
                                                        {{ getInitials(users.find(u => u.id === id)?.name ?? '') }}
                                                    </span>
                                                    {{ users.find(u => u.id === id)?.name }}
                                                    <button type="button" @click="form.handler_ids = form.handler_ids.filter(i => i !== id)" class="ml-0.5 rounded-full hover:bg-primary/20 p-0.5 transition-colors">
                                                        <X class="h-2.5 w-2.5" />
                                                    </button>
                                                </span>
                                            </div>
                                            <div class="relative">
                                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                                                <input
                                                    v-model="handlerSearch"
                                                    type="text"
                                                    placeholder="Search handlers…"
                                                    class="w-full rounded-lg border border-input bg-transparent pl-8 pr-3 py-2 text-xs shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                />
                                            </div>
                                            <div class="handler-list max-h-36 overflow-y-auto rounded-lg border border-border/50 divide-y divide-border/40 bg-muted/10">
                                                <button
                                                    v-for="user in filteredUsers"
                                                    :key="user.id"
                                                    type="button"
                                                    @click="form.handler_ids = form.handler_ids.includes(user.id) ? form.handler_ids.filter(i => i !== user.id) : [...form.handler_ids, user.id]"
                                                    :class="['w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors', form.handler_ids.includes(user.id) ? 'bg-primary/10 text-primary' : 'hover:bg-muted/50 text-foreground']"
                                                >
                                                    <div :class="['h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 border', form.handler_ids.includes(user.id) ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted border-border/50']">
                                                        {{ getInitials(user.name) }}
                                                    </div>
                                                    <span class="text-xs font-medium truncate">{{ user.name }}</span>
                                                    <CheckCircle2 v-if="form.handler_ids.includes(user.id)" class="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />
                                                </button>
                                                <div v-if="filteredUsers.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground/60 italic">No handlers found.</div>
                                            </div>
                                            <span v-if="form.errors.handler_ids" class="text-xs text-destructive font-medium">{{ form.errors.handler_ids }}</span>
                                        </div>

                                        <!-- Solution (required when Resolved) -->
                                        <div v-if="form.status === 'Resolved'" class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                                Solution <span class="ml-1 text-destructive">*</span>
                                            </Label>
                                            <RichTextEditor v-model="form.solution" placeholder="Describe how the issue was resolved…" />
                                            <span v-if="form.errors.solution" class="text-xs text-destructive font-medium">{{ form.errors.solution }}</span>
                                        </div>

                                        <div class="p-3.5 rounded-xl bg-muted/30 border border-muted flex items-start gap-3">
                                            <Info class="h-4 w-4 text-primary shrink-0 mt-0.5" />
                                            <p class="text-xs text-muted-foreground leading-relaxed">
                                                <template v-if="editingTicket">
                                                    Ticket will be updated to <span class="font-bold text-foreground">{{ form.category }}</span> / <span class="font-bold text-foreground">{{ form.priority }}</span> priority / <span class="font-bold text-foreground">{{ form.status }}</span>.
                                                </template>
                                                <template v-else>
                                                    Your ticket will be assigned to the <span class="font-bold text-foreground">{{ form.category }}</span> team with <span class="font-bold text-foreground">{{ form.priority }}</span> priority.
                                                </template>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter class="px-5 py-4 bg-muted/20 border-t border-border/50">
                                    <div class="flex w-full items-center justify-between gap-2">
                                        <Button v-if="currentStep > 1" type="button" variant="ghost" @click="currentStep--" class="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                                            ← Back
                                        </Button>
                                        <div v-else />
                                        <div class="flex items-center gap-2">
                                            <Button type="button" variant="outline" @click="isCreateModalOpen = false" class="text-xs font-bold">Cancel</Button>
                                            <Button
                                                v-if="currentStep < 2"
                                                type="button"
                                                @click="currentStep++"
                                                :disabled="!form.title"
                                                class="text-xs font-bold gap-1.5"
                                            >
                                                Next <ChevronRight class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                v-else
                                                type="submit"
                                                :disabled="form.processing || (handlerRequired && form.handler_ids.length === 0) || (form.status === 'Resolved' && isEmptyHtml(form.solution))"
                                                class="text-xs font-bold gap-1.5 shadow-md shadow-primary/20"
                                            >
                                                <span v-if="!form.processing" class="flex items-center gap-1.5">
                                                    <template v-if="editingTicket">Save Changes <Save class="h-3.5 w-3.5" /></template>
                                                    <template v-else>Launch Ticket <Plus class="h-3.5 w-3.5" /></template>
                                                </span>
                                                <span v-else class="flex items-center gap-1.5">
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

            <!-- ── Stat Cards ──────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6 sm:gap-3">
                <button
                    v-for="stat in ticketStats"
                    :key="stat.label"
                    @click="currentStatus = stat.status"
                    :class="[
                        'group relative flex flex-col gap-2.5 rounded-xl border bg-card px-4 py-3.5 text-left transition-all duration-200 active:scale-[0.97] hover:shadow-md overflow-hidden',
                        currentStatus === stat.status
                            ? ['border shadow-md ring-2', stat.borderActive, stat.glowClass, stat.ringClass]
                            : 'border-border/60 hover:border-border'
                    ]"
                >
                    <!-- Active pip -->
                    <div :class="['absolute left-0 inset-y-3 w-[3px] rounded-r-full transition-all duration-200', currentStatus === stat.status ? stat.bgClass.replace('/10', '') : 'opacity-0']" />

                    <!-- Icon + value row -->
                    <div class="flex items-center gap-3">
                        <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-110 relative', stat.bgClass]">
                            <component :is="stat.icon" class="h-4 w-4" :class="stat.colorClass" />
                            <!-- Pulsing ring for Open tickets -->
                            <span
                                v-if="stat.status === 'Open' && stat.value > 0"
                                class="absolute inset-0 rounded-lg animate-ping opacity-30"
                                :class="stat.bgClass"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide truncate">{{ stat.label }}</p>
                            <p :class="['text-2xl font-bold leading-none tabular-nums mt-0.5', stat.colorClass]">{{ stat.value }}</p>
                        </div>
                    </div>

                    <!-- Percentage bar (hidden for "Total") -->
                    <template v-if="stat.status !== 'All'">
                        <div class="h-1 w-full rounded-full bg-muted/50 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-700 ease-out"
                                :class="stat.bgClass.replace('/10', '/70')"
                                :style="{ width: `${ticketStats[0].value > 0 ? Math.round((stat.value / ticketStats[0].value) * 100) : 0}%` }"
                            />
                        </div>
                        <span class="text-[10px] font-semibold tabular-nums" :class="stat.colorClass + '/70'">
                            {{ ticketStats[0].value > 0 ? Math.round((stat.value / ticketStats[0].value) * 100) : 0 }}%
                        </span>
                    </template>
                    <template v-else>
                        <div class="h-1 w-full rounded-full bg-muted/50 overflow-hidden">
                            <div class="h-full rounded-full bg-primary/40 w-full transition-all duration-700 ease-out" />
                        </div>
                        <span class="text-[10px] font-semibold text-muted-foreground/70">All tickets</span>
                    </template>
                </button>
            </div>

            <!-- ── Filters Row ─────────────────────────────────── -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Status tabs -->
                <div class="flex items-center gap-1 overflow-x-auto no-scrollbar rounded-xl bg-muted/60 border border-border/50 p-1 shrink-0">
                    <button
                        v-for="status in statuses"
                        :key="status"
                        @click="currentStatus = status"
                        :class="[
                            'inline-flex items-center whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200 shrink-0',
                            currentStatus === status
                                ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
                                : 'text-muted-foreground hover:text-foreground hover:bg-background/60'
                        ]"
                    >
                        {{ status }}
                    </button>
                </div>

                <!-- Spacer -->
                <div class="flex-1" />

                <!-- Date range filter -->
                <div class="flex items-center gap-1.5 shrink-0 rounded-xl bg-muted/60 border border-border/50 px-2.5 py-1.5">
                    <SlidersHorizontal class="h-3 w-3 text-muted-foreground shrink-0" />
                    <input
                        v-model="dateFrom"
                        type="date"
                        :max="dateTo || undefined"
                        class="w-[118px] bg-transparent text-xs font-medium text-foreground placeholder:text-muted-foreground/50 focus:outline-none cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
                        title="From date"
                    />
                    <span class="text-muted-foreground/50 text-xs select-none">–</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        :min="dateFrom || undefined"
                        class="w-[118px] bg-transparent text-xs font-medium text-foreground placeholder:text-muted-foreground/50 focus:outline-none cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
                        title="To date"
                    />
                    <button
                        v-if="dateFrom || dateTo"
                        @click="dateFrom = ''; dateTo = ''"
                        class="ml-0.5 text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>

                <!-- Priority pill group -->
                <div class="flex items-center gap-1 overflow-x-auto no-scrollbar rounded-xl bg-muted/60 border border-border/50 p-1 shrink-0">
                    <button
                        @click="currentPriority = 'All'"
                        :class="[
                            'inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200 shrink-0',
                            currentPriority === 'All'
                                ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
                                : 'text-muted-foreground hover:text-foreground hover:bg-background/60'
                        ]"
                    >
                        All
                        <span class="text-[10px] font-bold tabular-nums text-muted-foreground">{{ priorityCounts['All'] ?? 0 }}</span>
                    </button>
                    <button
                        v-for="p in priorities"
                        :key="p.name"
                        @click="currentPriority = p.name"
                        :class="[
                            'inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200 shrink-0',
                            currentPriority === p.name
                                ? [p.activeBg, p.activeText, 'shadow-sm ring-1 ring-current/20']
                                : 'text-muted-foreground hover:text-foreground hover:bg-background/60'
                        ]"
                    >
                        <component
                            :is="p.icon"
                            class="h-3 w-3 shrink-0 transition-colors"
                            :class="currentPriority === p.name ? '' : p.color"
                        />
                        {{ p.name }}
                        <span :class="[
                            'text-[10px] font-bold tabular-nums transition-colors',
                            currentPriority === p.name ? 'opacity-70' : 'text-muted-foreground'
                        ]">{{ priorityCounts[p.name] ?? 0 }}</span>
                    </button>
                </div>

                <!-- Bulk selection count -->
                <div v-if="selectedIds.length > 0" class="flex items-center gap-1.5 shrink-0">
                    <span class="inline-flex items-center rounded-lg bg-primary/10 border border-primary/20 px-2.5 py-1.5 text-xs font-bold text-primary">
                        {{ selectedIds.length }} selected
                    </span>
                    <button @click="selectedIds = []" class="text-xs text-muted-foreground hover:text-foreground transition-colors">
                        Clear
                    </button>
                </div>

                <!-- Reset filters -->
                <Transition
                    enter-active-class="transition-all duration-200"
                    leave-active-class="transition-all duration-150"
                    enter-from-class="opacity-0 translate-x-2"
                    leave-to-class="opacity-0 translate-x-2"
                >
                    <button
                        v-if="currentPriority !== 'All' || currentStatus !== 'All' || search || dateFrom || dateTo"
                        @click="currentPriority = 'All'; currentStatus = 'All'; search = ''; dateFrom = ''; dateTo = ''"
                        class="inline-flex items-center gap-1 shrink-0 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-[11px] font-semibold text-muted-foreground shadow-sm hover:text-foreground hover:border-border transition-colors"
                    >
                        <X class="h-3 w-3" />
                        Reset
                    </button>
                </Transition>
            </div>

            <!-- ── Tickets Table / Cards ───────────────────────── -->
            <Card class="shadow-none border border-border/60 overflow-hidden">
                <CardContent class="p-0">

                    <!-- Empty state (shared) -->
                    <div v-if="sortedTickets.length === 0" class="flex flex-col items-center justify-center gap-4 px-6 py-24 text-center">
                        <div class="relative">
                            <div class="h-16 w-16 rounded-2xl bg-muted/60 flex items-center justify-center">
                                <TicketCheck class="h-8 w-8 text-muted-foreground/30" />
                            </div>
                            <div class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-muted/80 border border-border/60 flex items-center justify-center">
                                <Search class="h-2.5 w-2.5 text-muted-foreground/50" />
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-foreground text-sm">No tickets found</p>
                            <p class="text-xs text-muted-foreground mt-1 max-w-[260px]">
                                {{ search ? `No results for "${search}".` : 'No tickets match the current filters.' }}
                            </p>
                        </div>
                        <button
                            v-if="search || currentStatus !== 'All' || currentPriority !== 'All' || dateFrom || dateTo"
                            @click="search = ''; currentStatus = 'All'; currentPriority = 'All'; dateFrom = ''; dateTo = ''"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background px-3 py-1.5 text-xs font-semibold text-foreground hover:bg-muted transition-colors shadow-sm"
                        >
                            <X class="h-3 w-3" />
                            Clear all filters
                        </button>
                    </div>

                    <!-- Mobile Cards (< md) -->
                    <div v-else class="md:hidden divide-y divide-border/50">
                        <div
                            v-for="ticket in paginatedTickets"
                            :key="ticket.id"
                            @click="openDetailModal(ticket)"
                            :class="[
                                'group flex items-stretch gap-0 transition-colors hover:bg-muted/30 cursor-pointer active:bg-muted/50',
                                selectedIds.includes(ticket.numericId) ? 'bg-primary/5 hover:bg-primary/8' : ''
                            ]"
                        >
                            <!-- Colored left stripe -->
                            <div :class="['w-1 shrink-0 rounded-none', 'bg-gradient-to-b', getStatusLeftBorder(ticket.status).replace('border-l-', 'from-').concat('/70 to-transparent')]" />

                            <div class="flex flex-1 items-start gap-3 px-4 py-3.5 min-w-0">
                                <!-- Checkbox -->
                                <div @click.stop>
                                    <Checkbox
                                        :checked="selectedIds.includes(ticket.numericId)"
                                        @update:checked="(val) => toggleTicket(ticket.numericId, !!val)"
                                        class="mt-0.5 shrink-0"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <!-- Top row: ID + status + actions -->
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-[10px] font-mono font-bold text-muted-foreground/60">{{ ticket.id }}</span>
                                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 border', getStatusColor(ticket.status)]">
                                                <component :is="getStatusIcon(ticket.status)" class="h-2.5 w-2.5 shrink-0" />
                                                {{ ticket.status }}
                                            </Badge>
                                        </div>
                                        <div @click.stop>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <button class="h-7 w-7 shrink-0 inline-flex items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors outline-none">
                                                        <MoreHorizontal class="h-4 w-4" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48">
                                                    <DropdownMenuLabel>Ticket Actions</DropdownMenuLabel>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem @click="openDetailModal(ticket)"><ExternalLink class="mr-2 h-4 w-4" />View Details</DropdownMenuItem>
                                                    <DropdownMenuItem @click="openEditModal(ticket)"><Pencil class="mr-2 h-4 w-4" />Edit Ticket</DropdownMenuItem>
                                                    <DropdownMenuItem @click="openAssignModal(ticket)">
                                                        <UserPlus class="mr-2 h-4 w-4" />
                                                        Assign Handler
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="openChangeStatusModal(ticket)">
                                                        <RefreshCcw class="mr-2 h-4 w-4" />
                                                        Change Status
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        v-if="ticket.status !== 'Resolved' && ticket.status !== 'Closed'"
                                                        @click="ticket.status === 'Open' ? openAssignModal(ticket, 'Resolved') : updateStatus(ticket, 'Resolved')"
                                                        :disabled="statusProcessing === ticket.numericId"
                                                        class="text-emerald-600 focus:bg-emerald-50 focus:text-emerald-700 dark:text-emerald-400 dark:focus:bg-emerald-950/40 dark:focus:text-emerald-300"
                                                    >
                                                        <span v-if="statusProcessing === ticket.numericId" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent inline-block" />
                                                        <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                                                        Mark as Resolved
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="!['Resolved', 'Closed'].includes(ticket.status)"
                                                        @click="updateStatus(ticket, 'Closed')"
                                                        :disabled="statusProcessing === ticket.numericId"
                                                        class="text-slate-500 focus:bg-slate-100 focus:text-slate-700 dark:text-slate-400 dark:focus:bg-slate-800/50 dark:focus:text-slate-300"
                                                    >
                                                        <span v-if="statusProcessing === ticket.numericId" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent inline-block" />
                                                        <Lock v-else class="mr-2 h-4 w-4" />
                                                        Close Ticket
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem @click="openDeleteModal(ticket)" class="text-destructive focus:bg-destructive/10 focus:text-destructive"><Trash2 class="mr-2 h-4 w-4" />Delete Ticket</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>

                                    <!-- Title -->
                                    <p class="text-sm font-semibold text-foreground leading-snug group-hover:text-primary transition-colors line-clamp-2">{{ ticket.title }}</p>

                                    <!-- Meta chips -->
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span :class="['inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase', getPriorityBadge(ticket.priority)]">
                                            <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                            {{ ticket.priority }}
                                        </span>
                                        <span class="inline-flex items-center rounded-md bg-muted/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase tracking-tight border border-border/40">
                                            {{ ticket.category }}
                                        </span>
                                    </div>

                                    <!-- Footer row: reporter + date -->
                                    <div class="flex items-center justify-between mt-2.5 gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <div
                                                class="h-5 w-5 rounded-full bg-muted border border-border/50 flex items-center justify-center text-[9px] font-bold shrink-0 cursor-default"
                                                @mouseenter="showReporterTooltip($event, ticket.reporter)"
                                                @mouseleave="hideReporterTooltip"
                                            >
                                                {{ getInitials(ticket.reporter) }}
                                            </div>
                                            <span class="text-[11px] text-muted-foreground truncate">{{ ticket.reporter }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60 shrink-0">
                                            <Clock class="h-3 w-3" />
                                            {{ ticket.createdAt }}
                                        </div>
                                    </div>

                                    <!-- Handlers -->
                                    <div v-if="ticket.handlers.length > 0" class="flex items-center gap-1.5 mt-2">
                                        <div class="flex -space-x-1.5">
                                            <div
                                                v-for="(h, i) in ticket.handlers.slice(0, 3)"
                                                :key="h.id"
                                                :style="{ zIndex: 3 - i }"
                                                class="h-5 w-5 rounded-full bg-muted border-2 border-background flex items-center justify-center text-[9px] font-bold shrink-0"
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

                    <!-- Desktop Table (≥ md) -->
                    <div class="relative w-full hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[760px]">
                            <thead class="sticky top-0 z-10 bg-muted/40 backdrop-blur-md border-b border-border/50">
                                <tr class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">
                                    <th class="pl-5 pr-3 py-3.5 w-10">
                                        <Checkbox :checked="isAllSelected" @update:checked="toggleSelectAll" aria-label="Select all" />
                                    </th>
                                    <th class="px-3 py-3.5 w-28">
                                        <button @click="toggleSort('id')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            ID
                                            <ChevronUp v-if="sortKey === 'id' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'id' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="px-3 py-3.5">
                                        <button @click="toggleSort('title')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Ticket
                                            <ChevronUp v-if="sortKey === 'title' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'title' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="px-4 py-3.5 w-44">
                                        <button @click="toggleSort('handlers')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Handlers
                                            <ChevronUp v-if="sortKey === 'handlers' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'handlers' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="px-4 py-3.5 w-28">
                                        <button @click="toggleSort('reporter')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Reporter
                                            <ChevronUp v-if="sortKey === 'reporter' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'reporter' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="px-4 py-3.5 w-36">
                                        <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
                                            Status
                                            <ChevronUp v-if="sortKey === 'status' && sortDir === 'asc'" class="h-3 w-3 text-primary" />
                                            <ChevronDown v-else-if="sortKey === 'status' && sortDir === 'desc'" class="h-3 w-3 text-primary" />
                                            <ChevronsUpDown v-else class="h-3 w-3 opacity-30" />
                                        </button>
                                    </th>
                                    <th class="px-4 py-3.5 w-24 text-right pr-5">
                                        <span class="sr-only">Actions</span>
                                        <span aria-hidden="true" class="text-[10px] text-muted-foreground/40 font-normal normal-case tracking-normal">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                <tr
                                    v-for="ticket in paginatedTickets"
                                    :key="ticket.id"
                                    @click="openDetailModal(ticket)"
                                    :class="[
                                        'group transition-all duration-150 cursor-pointer hover:bg-muted/40 hover:shadow-[inset_3px_0_0_0] hover:shadow-primary/30',
                                        selectedIds.includes(ticket.numericId) ? 'bg-primary/5 hover:bg-primary/8' : ''
                                    ]"
                                >
                                    <td class="pl-5 pr-3 py-3.5" @click.stop>
                                        <Checkbox
                                            :checked="selectedIds.includes(ticket.numericId)"
                                            @update:checked="(val) => toggleTicket(ticket.numericId, !!val)"
                                        />
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="text-xs font-mono font-bold text-muted-foreground/50 group-hover:text-muted-foreground transition-colors">{{ ticket.id }}</span>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-col max-w-sm">
                                            <span class="text-sm font-semibold text-foreground group-hover:text-primary transition-colors truncate">{{ ticket.title }}</span>
                                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                                <span :class="['inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase', getPriorityBadge(ticket.priority)]">
                                                    <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                                    {{ ticket.priority }}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground/40">·</span>
                                                <span class="text-[10px] font-medium text-muted-foreground/70 uppercase tracking-tight">{{ ticket.category }}</span>
                                                <span class="text-[10px] text-muted-foreground/40">·</span>
                                                <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                    <Clock class="h-2.5 w-2.5" />
                                                    {{ ticket.createdAt }}
                                                </div>
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
                                                    class="h-7 w-7 rounded-full bg-muted flex items-center justify-center text-[10px] font-bold border-2 border-background shrink-0 cursor-default"
                                                    @mouseenter="showHandlerTooltip($event, h.name)"
                                                    @mouseleave="hideHandlerTooltip"
                                                >
                                                    {{ getInitials(h.name) }}
                                                </div>
                                            </div>
                                            <span v-if="ticket.handlers.length > 3" class="text-[10px] font-bold text-muted-foreground">+{{ ticket.handlers.length - 3 }}</span>
                                            <span v-if="ticket.handlers.length === 1" class="text-xs font-medium text-muted-foreground truncate max-w-[90px]">{{ ticket.handlers[0].name }}</span>
                                        </div>
                                        <button
                                            v-else
                                            @click.stop="openAssignModal(ticket)"
                                            class="inline-flex items-center gap-1 text-[10px] font-semibold text-muted-foreground/40 hover:text-primary hover:bg-primary/8 rounded-md px-1.5 py-0.5 transition-colors border border-dashed border-border/40 hover:border-primary/30 italic"
                                        >
                                            <UserPlus class="h-2.5 w-2.5 not-italic" />
                                            Assign
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <div
                                                class="h-6 w-6 rounded-full bg-muted border border-border/40 flex items-center justify-center text-[10px] font-bold shrink-0 cursor-default"
                                                @mouseenter="showReporterTooltip($event, ticket.reporter)"
                                                @mouseleave="hideReporterTooltip"
                                            >
                                                {{ getInitials(ticket.reporter) }}
                                            </div>
                                            <span class="text-xs text-muted-foreground truncate max-w-[80px]">{{ ticket.reporter }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <Badge variant="outline" :class="['inline-flex items-center gap-1 whitespace-nowrap text-[10px] font-bold px-2 py-1 border', getStatusColor(ticket.status)]">
                                            <component :is="getStatusIcon(ticket.status)" class="h-3 w-3 shrink-0" />
                                            {{ ticket.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3.5 text-right pr-5" @click.stop>
                                        <!-- Inline quick actions (visible on hover) -->
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                v-if="ticket.status !== 'Resolved' && ticket.status !== 'Closed'"
                                                @click.stop="ticket.status === 'Open' ? openAssignModal(ticket, 'Resolved') : updateStatus(ticket, 'Resolved')"
                                                :disabled="statusProcessing === ticket.numericId"
                                                class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-muted-foreground/0 group-hover:text-emerald-500 hover:bg-emerald-500/10 transition-all duration-150 disabled:opacity-50"
                                                title="Mark as Resolved"
                                            >
                                                <span v-if="statusProcessing === ticket.numericId" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                <CheckCircle2 v-else class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                @click.stop="openAssignModal(ticket)"
                                                class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-muted-foreground/0 group-hover:text-blue-500 hover:bg-blue-500/10 transition-all duration-150"
                                                title="Assign Handler"
                                            >
                                                <UserPlus class="h-3.5 w-3.5" />
                                            </button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <button class="h-7 w-7 inline-flex items-center justify-center rounded-lg hover:bg-muted text-muted-foreground/40 group-hover:text-foreground transition-all duration-150 outline-none">
                                                        <MoreHorizontal class="h-4 w-4" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48">
                                                    <DropdownMenuLabel>Ticket Actions</DropdownMenuLabel>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem @click="openDetailModal(ticket)"><ExternalLink class="mr-2 h-4 w-4" />View Details</DropdownMenuItem>
                                                    <DropdownMenuItem @click="openEditModal(ticket)"><Pencil class="mr-2 h-4 w-4" />Edit Ticket</DropdownMenuItem>
                                                    <DropdownMenuItem @click="openAssignModal(ticket)">
                                                        <UserPlus class="mr-2 h-4 w-4" />
                                                        Assign Handler
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="openChangeStatusModal(ticket)">
                                                        <RefreshCcw class="mr-2 h-4 w-4" />
                                                        Change Status
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        v-if="ticket.status !== 'Resolved' && ticket.status !== 'Closed'"
                                                        @click="ticket.status === 'Open' ? openAssignModal(ticket, 'Resolved') : updateStatus(ticket, 'Resolved')"
                                                        :disabled="statusProcessing === ticket.numericId"
                                                        class="text-emerald-600 focus:bg-emerald-50 focus:text-emerald-700 dark:text-emerald-400 dark:focus:bg-emerald-950/40 dark:focus:text-emerald-300"
                                                    >
                                                        <span v-if="statusProcessing === ticket.numericId" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent inline-block" />
                                                        <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                                                        Mark as Resolved
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="!['Resolved', 'Closed'].includes(ticket.status)"
                                                        @click="updateStatus(ticket, 'Closed')"
                                                        :disabled="statusProcessing === ticket.numericId"
                                                        class="text-slate-500 focus:bg-slate-100 focus:text-slate-700 dark:text-slate-400 dark:focus:bg-slate-800/50 dark:focus:text-slate-300"
                                                    >
                                                        <span v-if="statusProcessing === ticket.numericId" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent inline-block" />
                                                        <Lock v-else class="mr-2 h-4 w-4" />
                                                        Close Ticket
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem @click="openDeleteModal(ticket)" class="text-destructive focus:bg-destructive/10 focus:text-destructive"><Trash2 class="mr-2 h-4 w-4" />Delete Ticket</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>

                <!-- Pagination footer -->
                <div v-if="sortedTickets.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-border/50 sm:px-5">
                    <!-- Left: result count + per-page selector -->
                    <div class="flex items-center gap-3 order-2 sm:order-1">
                        <span class="text-xs text-muted-foreground">
                            Showing
                            <span class="font-semibold text-foreground">{{ (currentPage - 1) * pageSize + 1 }}</span>
                            –
                            <span class="font-semibold text-foreground">{{ Math.min(currentPage * pageSize, sortedTickets.length) }}</span>
                            of
                            <span class="font-semibold text-foreground">{{ sortedTickets.length }}</span>
                        </span>
                        <select
                            v-model="pageSize"
                            class="h-7 rounded-lg border border-border/60 bg-background px-2 text-xs font-medium text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                        >
                            <option v-for="n in PAGE_SIZE_OPTIONS" :key="n" :value="n">{{ n }} / page</option>
                        </select>
                    </div>

                    <!-- Page controls -->
                    <div class="flex items-center gap-1 order-1 sm:order-2">
                        <!-- Prev -->
                        <button
                            @click="currentPage = Math.max(1, currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="inline-flex items-center justify-center h-7 w-7 rounded-lg border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                        </button>

                        <!-- Page numbers -->
                        <template v-for="(p, i) in pageRange" :key="i">
                            <span v-if="p === '...'" class="flex items-center justify-center h-7 w-5 text-xs text-muted-foreground/50 select-none">…</span>
                            <button
                                v-else
                                @click="currentPage = p as number"
                                :class="[
                                    'inline-flex items-center justify-center h-7 min-w-[28px] px-1 rounded-lg text-xs font-semibold border transition-all duration-150',
                                    currentPage === p
                                        ? 'bg-foreground text-background border-foreground shadow-sm'
                                        : 'border-border/60 text-muted-foreground hover:bg-muted hover:text-foreground hover:border-border'
                                ]"
                            >{{ p }}</button>
                        </template>

                        <!-- Next -->
                        <button
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="inline-flex items-center justify-center h-7 w-7 rounded-lg border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                        >
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- ── Floating Bulk Action Bar ───────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                leave-active-class="transition-all duration-200 ease-in"
                enter-from-class="translate-y-4 opacity-0"
                leave-to-class="translate-y-4 opacity-0"
            >
                <div
                    v-if="selectedIds.length > 0"
                    class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 rounded-2xl border border-border/80 bg-background/95 backdrop-blur-md px-4 py-2.5 shadow-2xl shadow-black/20 ring-1 ring-border/30"
                >
                    <!-- Count pill -->
                    <div class="flex items-center gap-2 pr-3 border-r border-border/50">
                        <div class="h-5 w-5 rounded-full bg-primary flex items-center justify-center">
                            <span class="text-[9px] font-black text-primary-foreground leading-none">{{ selectedIds.length }}</span>
                        </div>
                        <span class="text-xs font-semibold text-foreground whitespace-nowrap">
                            ticket{{ selectedIds.length !== 1 ? 's' : '' }} selected
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1.5">
                        <button
                            @click="openBulkStatusModal"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-input bg-background px-3 py-1.5 text-xs font-semibold hover:bg-muted transition-colors"
                        >
                            <RefreshCcw class="h-3 w-3" />
                            Change Status
                        </button>
                        <button
                            @click="isBulkDeleteConfirmOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs font-semibold text-destructive hover:bg-destructive/10 transition-colors"
                        >
                            <Trash2 class="h-3 w-3" />
                            Delete
                        </button>
                    </div>

                    <!-- Clear -->
                    <div class="pl-3 border-l border-border/50">
                        <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded-md hover:bg-muted">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Handler tooltip -->
        <Teleport to="body">
            <Transition name="handler-tooltip">
                <div
                    v-if="handlerTooltip"
                    class="fixed z-[9999] pointer-events-none"
                    :style="{ left: `${handlerTooltip.x}px`, top: `${handlerTooltip.y}px`, transform: 'translate(-50%, calc(-100% - 8px))' }"
                >
                    <div class="bg-popover border border-border text-popover-foreground text-[11px] font-semibold whitespace-nowrap rounded-lg px-2.5 py-1.5 shadow-lg">
                        {{ handlerTooltip.name }}
                    </div>
                    <div class="mx-auto w-0 h-0 border-x-[5px] border-x-transparent border-t-[5px] border-t-border" />
                </div>
            </Transition>
        </Teleport>

        <!-- Reporter tooltip -->
        <Teleport to="body">
            <Transition name="handler-tooltip">
                <div
                    v-if="reporterTooltip"
                    class="fixed z-[9999] pointer-events-none"
                    :style="{ left: `${reporterTooltip.x}px`, top: `${reporterTooltip.y}px`, transform: 'translate(-50%, calc(-100% - 8px))' }"
                >
                    <div class="bg-popover border border-border text-popover-foreground text-[11px] font-semibold whitespace-nowrap rounded-lg px-2.5 py-1.5 shadow-lg">
                        {{ reporterTooltip.name }}
                    </div>
                    <div class="mx-auto w-0 h-0 border-x-[5px] border-x-transparent border-t-[5px] border-t-border" />
                </div>
            </Transition>
        </Teleport>

        <!-- View Details Modal -->
        <Dialog v-model:open="isDetailModalOpen">
            <DialogContent class="sm:max-w-[580px] p-0 overflow-hidden border-none shadow-2xl max-h-[92dvh] flex flex-col" v-if="selectedTicket">
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                {{ selectedTicket.id }}
                            </Badge>
                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border', getStatusColor(selectedTicket.status)]">
                                <component :is="getStatusIcon(selectedTicket.status)" class="h-3 w-3" />
                                {{ selectedTicket.status }}
                            </Badge>
                            <span :class="['inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[10px] font-bold uppercase', getPriorityBadge(selectedTicket.priority)]">
                                <component :is="getPriorityIcon(selectedTicket.priority)" class="h-3 w-3" />
                                {{ selectedTicket.priority }}
                            </span>
                        </div>
                        <DialogTitle class="text-base font-bold tracking-tight leading-snug sm:text-lg">
                            {{ selectedTicket.title }}
                        </DialogTitle>
                        <DialogDescription class="text-muted-foreground/70 text-xs mt-0.5">
                            Submitted {{ selectedTicket.createdAtFormatted }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body (scrollable) -->
                <div class="modal-body overflow-y-auto flex-1 flex flex-col">
                    <!-- Tab switcher -->
                    <div class="flex items-center gap-1 px-5 pt-4 pb-0 border-b border-border/40">
                        <button
                            @click="detailTab = 'overview'"
                            :class="[
                                'flex items-center gap-1.5 px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors',
                                detailTab === 'overview'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            <Info class="h-3.5 w-3.5" /> Overview
                        </button>
                        <button
                            @click="detailTab = 'history'"
                            :class="[
                                'flex items-center gap-1.5 px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors',
                                detailTab === 'history'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            <History class="h-3.5 w-3.5" /> History
                        </button>
                    </div>

                    <!-- Overview tab -->
                    <div v-if="detailTab === 'overview'" class="px-5 py-5 grid gap-4">
                        <!-- Meta grid -->
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <div class="flex flex-col gap-1 rounded-xl bg-muted/40 px-3 py-2.5 border border-border/40">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Category</span>
                            <span class="text-sm font-semibold text-foreground">{{ selectedTicket.category }}</span>
                        </div>
                        <div class="flex flex-col gap-1 rounded-xl bg-muted/40 px-3 py-2.5 border border-border/40">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Reporter</span>
                            <div class="flex items-center gap-1.5">
                                <div class="h-5 w-5 rounded-full bg-muted flex items-center justify-center text-[9px] font-bold border border-border/50 shrink-0">
                                    {{ getInitials(selectedTicket.reporter) }}
                                </div>
                                <span class="text-sm font-semibold text-foreground truncate">{{ selectedTicket.reporter }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1 rounded-xl bg-muted/40 px-3 py-2.5 border border-border/40 col-span-2 sm:col-span-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Handlers</span>
                            <div v-if="selectedTicket.handlers.length > 0" class="flex flex-wrap gap-1 mt-0.5">
                                <span
                                    v-for="h in selectedTicket.handlers"
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

                    <!-- Description -->
                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Description</span>
                        <div
                            v-if="selectedTicket.description"
                            class="rounded-xl border border-border/40 bg-muted/20 px-4 py-3 text-sm text-foreground leading-relaxed prose prose-sm max-w-none dark:prose-invert"
                            v-html="selectedTicket.description"
                        />
                        <div v-else class="rounded-xl border border-dashed border-border/40 bg-muted/10 px-4 py-5 text-center text-sm text-muted-foreground/60 italic">
                            No description provided.
                        </div>
                    </div>

                    <!-- Solution -->
                    <div v-if="selectedTicket.solution" class="flex flex-col gap-2">
                        <div class="flex items-center gap-1.5">
                            <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500 dark:text-emerald-400" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Solution</span>
                        </div>
                        <div
                            class="rounded-xl border border-emerald-200 dark:border-emerald-500/25 bg-emerald-50 dark:bg-emerald-500/10 px-4 py-3 text-sm text-foreground leading-relaxed prose prose-sm max-w-none dark:prose-invert"
                            v-html="selectedTicket.solution"
                        />
                    </div>

                    <!-- Attachment -->
                    <div v-if="selectedTicket.attachmentUrl" class="flex flex-col gap-2">
                        <div class="flex items-center gap-1.5">
                            <ImageIcon class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Attachment</span>
                        </div>
                        <a :href="selectedTicket.attachmentUrl" target="_blank" class="block rounded-xl overflow-hidden border border-border/50 bg-muted/20 hover:opacity-90 transition-opacity">
                            <img :src="selectedTicket.attachmentUrl" alt="Ticket attachment" class="w-full max-h-52 object-contain" />
                        </a>
                    </div>

                    <!-- Timeline -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground/70">
                            <Clock class="h-3.5 w-3.5 shrink-0" />
                            <span>Opened {{ selectedTicket.createdAt }}</span>
                            <span class="text-muted-foreground/40">·</span>
                            <span>{{ selectedTicket.createdAtFormatted }}</span>
                        </div>
                        <div v-if="selectedTicket.resolvedAtFormatted" class="flex items-center gap-2 text-xs">
                            <CheckCircle2 class="h-3.5 w-3.5 shrink-0 text-emerald-500 dark:text-emerald-400" />
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">Resolved in {{ selectedTicket.resolvedInDuration }}</span>
                            <span class="text-muted-foreground/40">·</span>
                            <span class="text-muted-foreground/70">{{ selectedTicket.resolvedAtFormatted }}</span>
                        </div>
                    </div>

                    <!-- Comments -->
                    <TicketComments :ticket-id="selectedTicket.numericId" :reporter-id="selectedTicket.reporterId" />
                    </div><!-- end overview tab -->

                    <!-- History tab -->
                    <div v-if="detailTab === 'history'" class="px-5 py-5">
                        <!-- Loading skeleton -->
                        <div v-if="activityLoading" class="flex flex-col gap-3">
                            <div v-for="i in 5" :key="i" class="flex items-start gap-3 animate-pulse">
                                <div class="h-7 w-7 rounded-full bg-muted shrink-0 mt-0.5"></div>
                                <div class="flex-1 flex flex-col gap-1.5 pt-1">
                                    <div class="h-3 bg-muted rounded w-3/4"></div>
                                    <div class="h-2.5 bg-muted/60 rounded w-1/2"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else-if="activityLog.length === 0" class="flex flex-col items-center justify-center py-10 gap-2 text-center">
                            <History class="h-8 w-8 text-muted-foreground/30" />
                            <p class="text-sm text-muted-foreground/60">No activity recorded yet.</p>
                        </div>

                        <!-- Timeline -->
                        <div v-else class="relative">
                            <!-- vertical line -->
                            <div class="absolute left-3.5 top-4 bottom-4 w-px bg-border/50"></div>

                            <div class="flex flex-col gap-0">
                                <div
                                    v-for="entry in activityLog"
                                    :key="entry.id"
                                    class="flex items-start gap-3 relative py-2.5 group"
                                >
                                    <!-- Icon bubble -->
                                    <div :class="['h-7 w-7 rounded-full flex items-center justify-center shrink-0 z-10 border', getActivityIconClass(entry.action)]">
                                        <component :is="getActivityIcon(entry.action)" class="h-3.5 w-3.5" />
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <p class="text-xs font-medium text-foreground leading-snug">
                                            <span class="font-semibold">{{ entry.userName }}</span>
                                            {{ getActivityLabel(entry) }}
                                        </p>
                                        <div v-if="(entry.oldValue || entry.newValue) && !['comment_posted','comment_deleted','comment_pinned','comment_unpinned','reaction_added','reaction_removed'].includes(entry.action)" class="flex items-center gap-1.5 mt-1 flex-wrap">
                                            <span v-if="entry.oldValue" class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-destructive/10 text-destructive/80 line-through">{{ entry.oldValue }}</span>
                                            <ChevronRight v-if="entry.oldValue && entry.newValue" class="h-3 w-3 text-muted-foreground/50 shrink-0" />
                                            <span v-if="entry.newValue" class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">{{ entry.newValue }}</span>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground/50 mt-0.5" :title="entry.createdAtFormatted">{{ entry.createdAt }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- end history tab -->
                </div><!-- end modal-body -->

                <DialogFooter class="px-5 py-4 bg-muted/20 border-t border-border/50 flex items-center gap-2">
                    <Button variant="outline" @click="openEditModal(selectedTicket!); isDetailModalOpen = false" class="text-xs font-bold gap-1.5">
                        <Pencil class="h-3.5 w-3.5" /> Edit
                    </Button>
                    <Button variant="outline" @click="isDetailModalOpen = false" class="ml-auto text-xs font-bold">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Assign Handler Modal ───────────────────────────────── -->
        <Dialog v-model:open="isAssignModalOpen">
            <DialogContent class="sm:max-w-[460px] p-0 overflow-hidden border-none shadow-2xl flex flex-col max-h-[90dvh]" v-if="assigningTicket">
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                {{ assigningTicket.id }}
                            </Badge>
                            <!-- Current status -->
                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border', getStatusColor(assigningTicket.status)]">
                                <component :is="getStatusIcon(assigningTicket.status)" class="h-3 w-3" />
                                {{ assigningTicket.status }}
                            </Badge>
                            <!-- Arrow + new status preview (Open tickets only) -->
                            <template v-if="assigningTicket.status === 'Open'">
                                <span class="text-muted-foreground/40 text-xs">→</span>
                                <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border', getStatusColor(assignStatusOverride)]">
                                    <component :is="getStatusIcon(assignStatusOverride)" class="h-3 w-3" />
                                    {{ assignStatusOverride }}
                                </Badge>
                            </template>
                        </div>
                        <DialogTitle class="text-base font-bold tracking-tight leading-snug flex items-center gap-2">
                            <UserPlus class="h-4 w-4 text-primary shrink-0" />
                            {{ assigningTicket.status === 'Open' ? 'Assign Handler & Update Status' : 'Assign Handlers' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground/80 truncate mt-0.5">
                            {{ assigningTicket.title }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="modal-body flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-5">

                    <!-- ① Status picker — only for Open tickets -->
                    <div v-if="assigningTicket.status === 'Open'" class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Set New Status</p>
                            <span class="inline-flex items-center rounded-md bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-500">Required</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="s in ['In Progress', 'On Hold', 'Resolved']"
                                :key="s"
                                type="button"
                                @click="assignStatusOverride = s"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold border-2 transition-all',
                                    assignStatusOverride === s
                                        ? [getStatusColor(s), 'border-current shadow-sm scale-[1.03]']
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                ]"
                            >
                                <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                {{ s }}
                            </button>
                        </div>
                        <div class="h-px bg-border/50" />
                    </div>

                    <!-- ② Currently assigned tags -->
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

                    <!-- ③ Search + user list -->
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

                <!-- Solution (required when assigning to Resolved) -->
                <div v-if="assignStatusOverride === 'Resolved' || (!assigningTicket?.status.includes('Open') && assigningTicket?.status === 'Resolved')" class="px-5 grid gap-2">
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
                                :disabled="assignForm.processing || assignForm.handler_ids.length === 0 || (assignStatusOverride === 'Resolved' && isEmptyHtml(assignForm.solution))"
                                @click="submitAssign"
                                class="text-xs font-bold gap-1.5 shadow-sm shadow-primary/20"
                            >
                                <span v-if="!assignForm.processing" class="flex items-center gap-1.5">
                                    <UserPlus class="h-3.5 w-3.5" />
                                    {{ assigningTicket.status === 'Open' ? 'Assign & Update' : 'Save Handlers' }}
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

        <!-- ── Change Status Modal ───────────────────────────────── -->
        <Dialog v-model:open="isChangeStatusModalOpen">
            <DialogContent class="sm:max-w-[460px] p-0 overflow-hidden border-none shadow-2xl flex flex-col max-h-[90dvh]" v-if="changeStatusTicket">
                <!-- Header -->
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                    <DialogHeader>
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 px-2 py-0 text-[10px] font-bold uppercase tracking-wider">
                                {{ changeStatusTicket.id }}
                            </Badge>
                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border', getStatusColor(changeStatusTicket.status)]">
                                <component :is="getStatusIcon(changeStatusTicket.status)" class="h-3 w-3" />
                                {{ changeStatusTicket.status }}
                            </Badge>
                            <template v-if="changeStatusValue">
                                <span class="text-muted-foreground/40 text-xs">→</span>
                                <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border', getStatusColor(changeStatusValue)]">
                                    <component :is="getStatusIcon(changeStatusValue)" class="h-3 w-3" />
                                    {{ changeStatusValue }}
                                </Badge>
                            </template>
                        </div>
                        <DialogTitle class="text-base font-bold tracking-tight leading-snug flex items-center gap-2">
                            <RefreshCcw class="h-4 w-4 text-primary shrink-0" />
                            Change Status & Handlers
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground/80 truncate mt-0.5">
                            {{ changeStatusTicket.title }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="modal-body flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-5">

                    <!-- ① Status picker -->
                    <div class="flex flex-col gap-2.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">New Status</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="s in changeStatusOptions"
                                :key="s"
                                type="button"
                                @click="changeStatusValue = s"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold border-2 transition-all',
                                    changeStatusValue === s
                                        ? [getStatusColor(s), 'border-current shadow-sm scale-[1.03]']
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                ]"
                            >
                                <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <!-- ② Handler section — hidden when new status is Open -->
                    <div v-if="changeStatusValue !== 'Open'" class="h-px bg-border/50" />

                    <!-- Currently assigned tags -->
                    <div v-if="changeStatusValue !== 'Open'" class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            {{ changeStatusForm.handler_ids.length > 0 ? 'Currently Assigned' : 'No Handlers Yet' }}
                        </p>
                        <div v-if="changeStatusForm.handler_ids.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="id in changeStatusForm.handler_ids"
                                :key="id"
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 border border-primary/20 pl-2 pr-1 py-1 text-[11px] font-semibold text-primary"
                            >
                                <span class="h-4 w-4 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[9px] font-bold shrink-0">
                                    {{ getInitials(users.find(u => u.id === id)?.name ?? '') }}
                                </span>
                                {{ users.find(u => u.id === id)?.name }}
                                <button
                                    type="button"
                                    @click="changeStatusForm.handler_ids = changeStatusForm.handler_ids.filter(i => i !== id)"
                                    class="ml-0.5 h-4 w-4 rounded-full hover:bg-primary/20 flex items-center justify-center transition-colors"
                                >
                                    <X class="h-2.5 w-2.5" />
                                </button>
                            </span>
                        </div>
                        <div v-else class="flex items-center gap-2 rounded-xl border border-dashed border-border/50 bg-muted/10 px-4 py-3">
                            <UserPlus class="h-4 w-4 text-muted-foreground/40 shrink-0" />
                            <p class="text-xs text-muted-foreground/60 italic">
                                {{ changeStatusValue === 'Closed' ? 'No handlers assigned. Optionally add one below.' : 'At least one handler is required for this status.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Search + user list -->
                    <div v-if="changeStatusValue !== 'Open'" class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            Update Handlers
                            <span v-if="changeStatusValue === 'Closed'" class="normal-case font-normal text-muted-foreground/50">(optional)</span>
                            <span v-else class="inline-flex items-center rounded-md bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-500">Required</span>
                        </p>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                            <input
                                v-model="changeStatusHandlerSearch"
                                type="text"
                                placeholder="Search users…"
                                class="w-full rounded-lg border border-input bg-background pl-9 pr-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="handler-list overflow-y-auto rounded-xl border border-border/50 divide-y divide-border/40 bg-muted/10" style="max-height: 190px;">
                            <button
                                v-for="user in filteredChangeStatusUsers"
                                :key="user.id"
                                type="button"
                                @click="changeStatusForm.handler_ids = changeStatusForm.handler_ids.includes(user.id)
                                    ? changeStatusForm.handler_ids.filter(i => i !== user.id)
                                    : [...changeStatusForm.handler_ids, user.id]"
                                :class="[
                                    'w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                    changeStatusForm.handler_ids.includes(user.id) ? 'bg-primary/10 text-primary' : 'hover:bg-muted/60 text-foreground'
                                ]"
                            >
                                <div :class="[
                                    'h-7 w-7 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 border transition-colors',
                                    changeStatusForm.handler_ids.includes(user.id) ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted border-border/50'
                                ]">
                                    {{ getInitials(user.name) }}
                                </div>
                                <span class="text-sm font-medium truncate flex-1">{{ user.name }}</span>
                                <div :class="[
                                    'h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors',
                                    changeStatusForm.handler_ids.includes(user.id) ? 'bg-primary border-primary' : 'border-border/50'
                                ]">
                                    <CheckCircle2 v-if="changeStatusForm.handler_ids.includes(user.id)" class="h-3 w-3 text-primary-foreground" />
                                </div>
                            </button>
                            <div v-if="filteredChangeStatusUsers.length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground/60 italic">
                                No users match your search.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Solution (required when Resolved) -->
                <div v-if="changeStatusValue === 'Resolved'" class="px-5 grid gap-2">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Solution <span class="ml-1 text-destructive">*</span>
                    </Label>
                    <RichTextEditor v-model="changeStatusForm.solution" placeholder="Describe how the issue was resolved…" />
                    <span v-if="changeStatusForm.errors.solution" class="text-xs text-destructive font-medium">{{ changeStatusForm.errors.solution }}</span>
                </div>

                <!-- Footer -->
                <DialogFooter class="px-5 py-4 bg-muted/20 border-t border-border/50">
                    <div class="flex w-full items-center justify-between gap-2">
                        <p class="text-xs text-muted-foreground">
                            <span class="font-semibold text-foreground">{{ changeStatusForm.handler_ids.length }}</span>
                            handler{{ changeStatusForm.handler_ids.length !== 1 ? 's' : '' }} selected
                        </p>
                        <div class="flex items-center gap-2">
                            <Button type="button" variant="outline" @click="isChangeStatusModalOpen = false" class="text-xs font-bold">
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                :disabled="changeStatusForm.processing || !changeStatusValue || (!['Open', 'Closed'].includes(changeStatusValue) && changeStatusForm.handler_ids.length === 0) || (changeStatusValue === 'Resolved' && isEmptyHtml(changeStatusForm.solution))"
                                @click="submitChangeStatus"
                                class="text-xs font-bold gap-1.5 shadow-sm shadow-primary/20"
                            >
                                <span v-if="!changeStatusForm.processing" class="flex items-center gap-1.5">
                                    <RefreshCcw class="h-3.5 w-3.5" /> Save Changes
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

        <!-- ── Bulk Change Status Modal ──────────────────────────── -->
        <Dialog v-model:open="isBulkStatusModalOpen">
            <DialogContent class="sm:max-w-[460px] p-0 overflow-hidden border-none shadow-2xl flex flex-col max-h-[90dvh]">
                <div class="bg-primary/5 px-5 pt-5 pb-4 border-b border-primary/10">
                    <DialogHeader>
                        <DialogTitle class="text-base font-bold tracking-tight flex items-center gap-2">
                            <RefreshCcw class="h-4 w-4 text-primary shrink-0" />
                            Bulk Change Status
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground/80 mt-0.5">
                            Applies to <span class="font-semibold text-foreground">{{ selectedIds.length }}</span> selected ticket{{ selectedIds.length !== 1 ? 's' : '' }}.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="modal-body flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-5">
                    <!-- ① Status picker -->
                    <div class="flex flex-col gap-2.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">New Status</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="s in ALL_STATUSES"
                                :key="s"
                                type="button"
                                @click="bulkStatusValue = s"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold border-2 transition-all',
                                    bulkStatusValue === s
                                        ? [getStatusColor(s), 'border-current shadow-sm scale-[1.03]']
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                ]"
                            >
                                <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                {{ s }}
                            </button>
                        </div>
                        <!-- Open notice -->
                        <div v-if="bulkStatusValue === 'Open'" class="flex items-start gap-2.5 rounded-xl border border-rose-500/20 bg-rose-500/5 px-3.5 py-2.5">
                            <AlertTriangle class="h-3.5 w-3.5 text-rose-500 shrink-0 mt-0.5" />
                            <p class="text-xs text-rose-600 dark:text-rose-400 leading-relaxed">
                                All handlers will be <span class="font-semibold">removed</span> from the selected tickets when setting status to Open.
                            </p>
                        </div>
                    </div>

                    <!-- ② Handler section — hidden when new status is Open -->
                    <div v-if="bulkStatusValue !== 'Open'" class="h-px bg-border/50" />

                    <!-- Currently selected handler tags -->
                    <div v-if="bulkStatusValue !== 'Open'" class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            {{ bulkStatusHandlerIds.length > 0 ? 'Selected Handlers' : 'No Handlers Selected' }}
                        </p>
                        <div v-if="bulkStatusHandlerIds.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="id in bulkStatusHandlerIds"
                                :key="id"
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 border border-primary/20 pl-2 pr-1 py-1 text-[11px] font-semibold text-primary"
                            >
                                <span class="h-4 w-4 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[9px] font-bold shrink-0">
                                    {{ getInitials(users.find(u => u.id === id)?.name ?? '') }}
                                </span>
                                {{ users.find(u => u.id === id)?.name }}
                                <button
                                    type="button"
                                    @click="bulkStatusHandlerIds = bulkStatusHandlerIds.filter(i => i !== id)"
                                    class="ml-0.5 h-4 w-4 rounded-full hover:bg-primary/20 flex items-center justify-center transition-colors"
                                >
                                    <X class="h-2.5 w-2.5" />
                                </button>
                            </span>
                        </div>
                        <div v-else class="flex items-center gap-2 rounded-xl border border-dashed border-border/50 bg-muted/10 px-4 py-3">
                            <UserPlus class="h-4 w-4 text-muted-foreground/40 shrink-0" />
                            <p class="text-xs text-muted-foreground/60 italic">
                                {{ bulkStatusValue === 'Closed' ? 'No handlers. Optionally assign one below.' : 'At least one handler is required for this status.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Search + user list -->
                    <div v-if="bulkStatusValue !== 'Open'" class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            Assign Handlers
                            <span v-if="bulkStatusValue === 'Closed'" class="normal-case font-normal text-muted-foreground/50">(optional)</span>
                            <span v-else class="inline-flex items-center rounded-md bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-500">Required</span>
                        </p>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                            <input
                                v-model="bulkStatusHandlerSearch"
                                type="text"
                                placeholder="Search users…"
                                class="w-full rounded-lg border border-input bg-background pl-9 pr-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="handler-list overflow-y-auto rounded-xl border border-border/50 divide-y divide-border/40 bg-muted/10" style="max-height: 190px;">
                            <button
                                v-for="user in filteredBulkStatusUsers"
                                :key="user.id"
                                type="button"
                                @click="bulkStatusHandlerIds = bulkStatusHandlerIds.includes(user.id)
                                    ? bulkStatusHandlerIds.filter(i => i !== user.id)
                                    : [...bulkStatusHandlerIds, user.id]"
                                :class="[
                                    'w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                    bulkStatusHandlerIds.includes(user.id) ? 'bg-primary/10 text-primary' : 'hover:bg-muted/60 text-foreground'
                                ]"
                            >
                                <div :class="[
                                    'h-7 w-7 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 border transition-colors',
                                    bulkStatusHandlerIds.includes(user.id) ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted border-border/50'
                                ]">
                                    {{ getInitials(user.name) }}
                                </div>
                                <span class="text-sm font-medium truncate flex-1">{{ user.name }}</span>
                                <div :class="[
                                    'h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors',
                                    bulkStatusHandlerIds.includes(user.id) ? 'bg-primary border-primary' : 'border-border/50'
                                ]">
                                    <CheckCircle2 v-if="bulkStatusHandlerIds.includes(user.id)" class="h-3 w-3 text-primary-foreground" />
                                </div>
                            </button>
                            <div v-if="filteredBulkStatusUsers.length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground/60 italic">
                                No users match your search.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Solution (required when bulk resolving) -->
                <div v-if="bulkStatusValue === 'Resolved'" class="px-5 grid gap-2">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Solution <span class="ml-1 text-destructive">*</span>
                    </Label>
                    <RichTextEditor v-model="bulkStatusSolution" placeholder="Describe how the issue was resolved…" />
                </div>

                <DialogFooter class="px-5 py-4 bg-muted/20 border-t border-border/50">
                    <div class="flex w-full items-center justify-between gap-2">
                        <p class="text-xs text-muted-foreground">
                            <template v-if="bulkStatusValue !== 'Open'">
                                <span class="font-semibold text-foreground">{{ bulkStatusHandlerIds.length }}</span>
                                handler{{ bulkStatusHandlerIds.length !== 1 ? 's' : '' }} selected
                            </template>
                            <template v-else>Handlers will be cleared</template>
                        </p>
                        <div class="flex items-center gap-2">
                            <Button type="button" variant="outline" @click="isBulkStatusModalOpen = false" class="text-xs font-bold">
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                :disabled="bulkStatusForm.processing || !bulkStatusValue || (!['Open', 'Closed'].includes(bulkStatusValue) && bulkStatusHandlerIds.length === 0) || (bulkStatusValue === 'Resolved' && isEmptyHtml(bulkStatusSolution.value))"
                                @click="submitBulkStatus"
                                class="text-xs font-bold gap-1.5"
                            >
                                <span v-if="!bulkStatusForm.processing" class="flex items-center gap-1.5">
                                    <RefreshCcw class="h-3.5 w-3.5" /> Apply to {{ selectedIds.length }} Ticket{{ selectedIds.length !== 1 ? 's' : '' }}
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

        <!-- ── Single Delete Confirm Dialog ─────────────────────── -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogContent class="sm:max-w-[400px] p-0 overflow-hidden shadow-xl flex flex-col">
                <!-- Header -->
                <div class="px-5 pt-5 pb-4 border-b border-border/50">
                    <DialogHeader>
                        <DialogTitle class="text-base font-bold tracking-tight flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-500/15 ring-1 ring-rose-200 dark:ring-rose-500/25">
                                <Trash2 class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                            </div>
                            Delete Ticket?
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-1 leading-relaxed">
                            This action is permanent and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="px-5 py-4 flex flex-col gap-3">
                    <!-- Ticket preview -->
                    <div v-if="deleteTarget" class="rounded-xl border border-border bg-muted/50 px-4 py-3 flex flex-col gap-1.5">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ deleteTarget.id }}</span>
                            <Badge variant="outline" class="text-[10px] px-1.5 py-0 font-semibold">{{ deleteTarget.status }}</Badge>
                        </div>
                        <p class="text-sm font-semibold text-foreground leading-snug line-clamp-2">{{ deleteTarget.title }}</p>
                        <p class="text-[11px] text-muted-foreground">Reported by <span class="font-medium text-foreground/70">{{ deleteTarget.reporter }}</span></p>
                    </div>

                    <!-- Warning notice -->
                    <div class="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-500/25 bg-amber-50 dark:bg-amber-500/10 px-4 py-3">
                        <AlertTriangle class="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" />
                        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
                            All associated data including handlers, attachments, and history will be <span class="font-semibold">permanently removed</span>.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <DialogFooter class="px-5 py-4 border-t border-border/50">
                    <div class="flex w-full items-center justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="isDeleteModalOpen = false" class="text-xs font-semibold">
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            :disabled="deleteForm.processing"
                            @click="submitDelete"
                            class="text-xs font-semibold gap-1.5"
                        >
                            <span v-if="!deleteForm.processing" class="flex items-center gap-1.5">
                                <Trash2 class="h-3.5 w-3.5" /> Delete Ticket
                            </span>
                            <span v-else class="flex items-center gap-1.5">
                                Deleting… <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                            </span>
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Bulk Delete Confirm Dialog ────────────────────────── -->
        <Dialog v-model:open="isBulkDeleteConfirmOpen">
            <DialogContent class="sm:max-w-[400px] p-0 overflow-hidden shadow-xl flex flex-col">
                <!-- Header -->
                <div class="px-5 pt-5 pb-4 border-b border-border/50">
                    <DialogHeader>
                        <DialogTitle class="text-base font-bold tracking-tight flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-500/15 ring-1 ring-rose-200 dark:ring-rose-500/25">
                                <Trash2 class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                            </div>
                            Delete {{ selectedIds.length }} Ticket{{ selectedIds.length !== 1 ? 's' : '' }}?
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-1 leading-relaxed">
                            This action is permanent and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="px-5 py-4 flex flex-col gap-3">
                    <!-- Count summary -->
                    <div class="rounded-xl border border-border bg-muted/50 px-4 py-3 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-background border border-border shadow-sm">
                            <span class="text-sm font-black text-foreground tabular-nums">{{ selectedIds.length }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-foreground leading-snug">
                                {{ selectedIds.length }} ticket{{ selectedIds.length !== 1 ? 's' : '' }} selected
                            </p>
                            <p class="text-[11px] text-muted-foreground">All of their data will be removed.</p>
                        </div>
                    </div>

                    <!-- Warning notice -->
                    <div class="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-500/25 bg-amber-50 dark:bg-amber-500/10 px-4 py-3">
                        <AlertTriangle class="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" />
                        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
                            Handlers, attachments, and history for all selected tickets will be <span class="font-semibold">permanently removed</span>.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <DialogFooter class="px-5 py-4 border-t border-border/50">
                    <div class="flex w-full items-center justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="isBulkDeleteConfirmOpen = false" class="text-xs font-semibold">
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            :disabled="bulkDeleteForm.processing"
                            @click="submitBulkDelete"
                            class="text-xs font-semibold gap-1.5"
                        >
                            <span v-if="!bulkDeleteForm.processing" class="flex items-center gap-1.5">
                                <Trash2 class="h-3.5 w-3.5" /> Delete {{ selectedIds.length }} Ticket{{ selectedIds.length !== 1 ? 's' : '' }}
                            </span>
                            <span v-else class="flex items-center gap-1.5">
                                Deleting… <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                            </span>
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.handler-tooltip-enter-active,
.handler-tooltip-leave-active { transition: opacity 0.12s ease; }
.handler-tooltip-enter-from,
.handler-tooltip-leave-to { opacity: 0; }

.modal-body {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--border)) transparent;
}
.modal-body::-webkit-scrollbar { width: 4px; }
.modal-body::-webkit-scrollbar-track { background: transparent; }
.modal-body::-webkit-scrollbar-thumb { background-color: hsl(var(--border)); border-radius: 9999px; }
.modal-body::-webkit-scrollbar-thumb:hover { background-color: hsl(var(--muted-foreground) / 0.4); }

.handler-list {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--border)) transparent;
}
.handler-list::-webkit-scrollbar { width: 4px; }
.handler-list::-webkit-scrollbar-track { background: transparent; }
.handler-list::-webkit-scrollbar-thumb { background-color: hsl(var(--border)); border-radius: 9999px; }
.handler-list::-webkit-scrollbar-thumb:hover { background-color: hsl(var(--muted-foreground) / 0.4); }
</style>
