<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import TicketDetailModal from '@/components/TicketDetailModal.vue';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
    Info,
    HelpCircle,
    Pencil,
    Save,
    Upload,
    SlidersHorizontal,
    ChevronRight,
    Lock,
    RefreshCcw,
    Download,
    MessageSquare,
} from 'lucide-vue-next';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { ensureLucideIconsLoaded, lucideAllIconMap, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import { Ticket, TicketCheck, Loader, X, ChevronsUpDown, ChevronUp, ChevronDown, ChevronLeft } from 'lucide-vue-next';
import { compressImage } from '@/lib/utils';
import { laravelFetch } from '@/lib/laravelFetch';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tickets',
        href: route('tickets'),
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
const page = usePage();

const checkQueryForTicket = () => {
    const params = new URLSearchParams(window.location.search);
    const ticketId = params.get('ticket_id');
    const create = params.get('create');
    
    let urlChanged = false;

    if (ticketId) {
        const ticket = props.tickets.find((t) => t.numericId === parseInt(ticketId));
        if (ticket) {
            openDetailModal(ticket);
        }
        params.delete('ticket_id');
        urlChanged = true;
    }

    if (create === 'true') {
        isCreateModalOpen.value = true;
        params.delete('create');
        urlChanged = true;
    }
    
    if (urlChanged) {
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }
};

onMounted(() => {
    checkCsrfExpired();
    checkQueryForTicket();
    void ensureLucideIconsLoaded();
});

watch(() => page.url, () => {
    checkQueryForTicket();
});

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
        commentsCount: number;
        tags: string[];
    }[];
    users: {
        id: number;
        name: string;
    }[];
    categories: { id: number; name: string; icon: string }[];
    priorities: { id: number; name: string; icon: string; color: string }[];
    statuses: {
        id: number;
        name: string;
        icon: string;
        color: string;
        handler_requirement: 'none' | 'optional' | 'required';
    }[];
    allTags: string[];
}>();

type StatusHandlerRequirement = 'none' | 'optional' | 'required';

function statusHandlerRequirement(statusName: string): StatusHandlerRequirement {
    const row = props.statuses.find((s) => s.name === statusName);
    return row?.handler_requirement ?? 'required';
}

function isStatusNoHandlers(statusName: string): boolean {
    return statusHandlerRequirement(statusName) === 'none';
}

function isStatusHandlerRequired(statusName: string): boolean {
    return statusHandlerRequirement(statusName) === 'required';
}

function isStatusHandlerOptional(statusName: string): boolean {
    return statusHandlerRequirement(statusName) === 'optional';
}

const defaultNewTicketStatusName = computed(
    () => props.statuses.find((s) => s.handler_requirement === 'none')?.name ?? props.statuses[0]?.name ?? 'Open',
);

const firstRequiredStatusName = computed(
    () => props.statuses.find((s) => s.handler_requirement === 'required')?.name ?? props.statuses[1]?.name ?? 'In Progress',
);

const primaryQueueStatusName = computed(
    () => props.statuses.find((s) => s.handler_requirement === 'none')?.name ?? 'Open',
);

const assignModalTargetStatuses = computed(() => props.statuses.filter((s) => s.handler_requirement === 'required'));

const isCreateModalOpen = ref(false);
const currentStep = ref(1);

const isDetailModalOpen = ref(false);
const selectedTicket = ref<typeof props.tickets[0] | null>(null);
const editingTicket = ref<typeof props.tickets[0] | null>(null);
const attachmentPreview = ref<string | null>(null);
const attachmentCompression = ref<{ before: number; after: number } | null>(null);

const isDraggingOver = ref(false);

const setAttachmentFile = async (file: File | null) => {
    if (!file) {
        form.attachment = null;
        attachmentPreview.value = null;
        attachmentCompression.value = null;
        return;
    }
    const compressed = await compressImage(file);
    form.attachment = compressed;
    attachmentPreview.value = URL.createObjectURL(compressed);
    attachmentCompression.value = compressed !== file
        ? { before: file.size, after: compressed.size }
        : null;
};

const onAttachmentChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    setAttachmentFile(file);
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
    attachmentCompression.value = null;
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
    form.tags = [...ticket.tags];
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
    status: props.statuses.find((s) => s.handler_requirement === 'none')?.name ?? props.statuses[0]?.name ?? 'Open',
    handler_ids: [] as number[],
    tags: [] as string[],
    solution: '',
    attachment: null as File | null,
});

const handlerRequired = computed(() => isStatusHandlerRequired(form.status));

const isEmptyHtml = (html: string): boolean => !html.replace(/<[^>]*>/g, '').trim();

const handlerSearch = ref('');
const tagSearchInput = ref('');

const filteredTags = computed(() => {
    if (!tagSearchInput.value.trim()) return props.allTags;
    const q = tagSearchInput.value.toLowerCase();
    return props.allTags.filter(t => t.toLowerCase().includes(q));
});

// ── Assign Handler modal ───────────────────────────────────────────────────
const isAssignModalOpen = ref(false);
const assigningTicket = ref<typeof props.tickets[0] | null>(null);
const assignHandlerSearch = ref('');
// When the ticket is Open, admin must also pick a new status before saving
const assignStatusOverride = ref<string>(
    props.statuses.find((s) => s.handler_requirement === 'required')?.name ?? 'In Progress',
);

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
const openAssignModal = (ticket: typeof props.tickets[0], defaultStatus?: string) => {
    assigningTicket.value = ticket;
    assignForm.handler_ids = [...ticket.handlerIds];
    assignHandlerSearch.value = '';
    assignStatusOverride.value = defaultStatus ?? firstRequiredStatusName.value;
    isAssignModalOpen.value = true;
};

const submitAssign = () => {
    if (!assigningTicket.value) return;
    const ticket = assigningTicket.value;
    const isQueueTicket = isStatusNoHandlers(ticket.status);
    assignForm
        .transform(data => ({
            handler_ids: data.handler_ids,
            ...(isQueueTicket ? { status: assignStatusOverride.value } : {}),
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
        assignStatusOverride.value = firstRequiredStatusName.value;
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
const bulkStatusValue = ref(
    props.statuses.find((s) => s.handler_requirement === 'required')?.name ?? 'In Progress',
);
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
    bulkStatusValue.value = firstRequiredStatusName.value;
    bulkStatusHandlerIds.value = [];
    bulkStatusHandlerSearch.value = '';
    isBulkStatusModalOpen.value = true;
};

watch(bulkStatusValue, (val) => {
    if (isStatusNoHandlers(val)) {
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
            handler_ids: isStatusNoHandlers(bulkStatusValue.value) ? [] : bulkStatusHandlerIds.value,
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
const ALL_STATUSES = computed(() => props.statuses.map((s) => s.name));

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
    ALL_STATUSES.value.filter(s => s !== changeStatusTicket.value?.status)
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

const openChangeStatusModal = (ticket: typeof props.tickets[0], defaultStatus?: string) => {
    changeStatusTicket.value = ticket;
    changeStatusHandlerSearch.value = '';
    
    if (defaultStatus && changeStatusOptions.value.includes(defaultStatus)) {
        changeStatusValue.value = defaultStatus;
    } else {
        // Default to the first status that can carry handlers so the watcher never clears pre-existing handlers
        changeStatusValue.value =
            changeStatusOptions.value.find((s) => !isStatusNoHandlers(s)) ?? changeStatusOptions.value[0] ?? '';
    }
    if (! isStatusNoHandlers(changeStatusValue.value)) {
        changeStatusForm.handler_ids = [...ticket.handlerIds];
    } else {
        changeStatusForm.handler_ids = [];
    }
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
        void laravelFetch(route('tickets.export-excel-audit'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_count: sortedTickets.value.length }),
        }).catch(() => {});

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
            { key: 'tags',       header: 'Tags',         width: 25 },
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

        const hexToExcelSoftFill = (hex: string): string | undefined => {
            const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
            if (! m) {
                return undefined;
            }
            const n = parseInt(m[1], 16);
            const r = (n >> 16) & 255;
            const g = (n >> 8) & 255;
            const b = n & 255;
            const mix = (c: number) => Math.round(c * 0.12 + 255 * 0.88);
            const h = (x: number) => x.toString(16).padStart(2, '0').toUpperCase();
            return `FF${h(mix(r))}${h(mix(g))}${h(mix(b))}`;
        };
        const statusColors: Record<string, string> = {};
        for (const s of props.statuses) {
            const argb = hexToExcelSoftFill(s.color);
            if (argb) {
                statusColors[s.name] = argb;
            }
        }
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
                tags:      t.tags.join(', ') || '—',
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
        sheet.autoFilter = { from: 'A1', to: 'K1' };

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
    // Form submission validation
    if (form.tags.length === 0) {
        form.setError('tags', 'At least one tag is required.');
        return;
    }

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
        form.status = defaultNewTicketStatusName.value;
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
const currentCategory = ref('All');
const selectedIds = ref<number[]>([]);

const statusOptions = computed(() => ['All', ...props.statuses.map((s) => s.name)]);

const categoryOptions = computed(() =>
    props.categories.map((c) => ({
        ...c,
        iconComponent: resolveLucideIcon(c.icon, HelpCircle),
    })),
);

const priorityOptions = computed(() =>
    props.priorities.map((p) => ({
        ...p,
        iconComponent: resolveLucideIcon(p.icon, Circle),
    })),
);

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

const priorityOrder = computed(() =>
    Object.fromEntries(props.priorities.map((p, i) => [p.name, props.priorities.length - i])),
);
const statusOrder = computed(() =>
    Object.fromEntries(props.statuses.map((s, i) => [s.name, i + 1])),
);

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
            t.handlers.some(h => h.name.toLowerCase().includes(q)) ||
            (t.tags ?? []).some((tag) => tag.toLowerCase().includes(q))
        );
    }

    if (currentCategory.value !== 'All') {
        base = base.filter(t => t.category === currentCategory.value);
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

/** Stat card row: either the fixed “All” summary or a DB-driven status */
type TicketStatRow = {
    label: string;
    status: string;
    value: number;
    icon: object;
    accentHex?: string;
    colorClass: string;
    bgClass: string;
    borderActive: string;
    glowClass: string;
    ringClass: string;
};

const ticketStats = computed((): TicketStatRow[] => {
    void lucideAllIconMap.value;
    const allCount = preStatusFiltered.value.length;
    const allRow: TicketStatRow = {
        label: 'Total',
        status: 'All',
        value: allCount,
        icon: Ticket,
        colorClass: 'text-primary',
        bgClass: 'bg-primary/10',
        borderActive: 'border-primary/40',
        glowClass: 'shadow-primary/20',
        ringClass: 'ring-primary/20',
    };
    const statusRows: TicketStatRow[] = props.statuses.map((s) => ({
        label: s.name,
        status: s.name,
        value: preStatusFiltered.value.filter((t) => t.status === s.name).length,
        icon: resolveLucideIcon(s.icon, Circle),
        accentHex: s.color,
        colorClass: '',
        bgClass: '',
        borderActive: '',
        glowClass: '',
        ringClass: '',
    }));
    return [allRow, ...statusRows];
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
        if (sortKey.value === 'priority') { aVal = priorityOrder.value[aVal] ?? 0; bVal = priorityOrder.value[bVal] ?? 0; }
        if (sortKey.value === 'status') { aVal = statusOrder.value[aVal] ?? 0; bVal = statusOrder.value[bVal] ?? 0; }
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

watch([search, currentStatus, currentPriority, currentCategory, dateFrom, dateTo, sortKey, sortDir, pageSize], () => {
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

const statusOptionsLocal = computed(() => {
    void lucideAllIconMap.value;
    return props.statuses.map((s) => ({
        ...s,
        iconComponent: resolveLucideIcon(s.icon, Circle),
    }));
});

function getStatusMeta(status: string) {
    return statusOptionsLocal.value.find((s) => s.name === status);
}

function getStatusStyle(status: string): Record<string, string> {
    const found = getStatusMeta(status);
    if (! found?.color) {
        return {};
    }
    return {
        backgroundColor: found.color + '26',
        color: found.color,
        borderColor: found.color + '40',
    };
}

function getStatusIcon(status: string) {
    return getStatusMeta(status)?.iconComponent ?? Circle;
}

function statusStripeGradientStyle(status: string): Record<string, string> {
    const c = getStatusMeta(status)?.color ?? '#94a3b8';
    return {
        background: `linear-gradient(to bottom, ${c}cc, ${c}66, transparent)`,
    };
}

function statCardActiveAccentStyle(hex: string): Record<string, string> {
    return {
        borderColor: `${hex}99`,
        boxShadow: `0 4px 14px ${hex}35, 0 0 0 2px ${hex}30`,
    };
}

const getPriorityIcon = (priority: string) => {
    const found = priorityOptions.value.find((p) => p.name === priority);
    return found?.iconComponent ?? Circle;
};

const getPriorityStyle = (priority: string): Record<string, string> => {
    const found = props.priorities.find((p) => p.name === priority);
    if (! found) { return {}; }
    return {
        backgroundColor: found.color + '26',
        color: found.color,
        borderColor: found.color + '40',
    };
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
                            v-if="ticketStats.find(s => s.status === primaryQueueStatusName)?.value ?? 0 > 0"
                            class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 border border-rose-500/20 px-2 py-0.5 text-[10px] font-bold text-rose-500"
                        >
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute h-full w-full rounded-full bg-rose-400 opacity-75" />
                                <span class="relative rounded-full h-1.5 w-1.5 bg-rose-500" />
                            </span>
                            {{ ticketStats.find(s => s.status === primaryQueueStatusName)?.value }} open
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
                            placeholder="Search tickets, tags, reporters…"
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

                                        <!-- Tags -->
                                        <div class="grid gap-2">
                                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                                Tags <span class="ml-1 text-destructive">*</span>
                                            </Label>
                                            
                                            <div v-if="form.tags.length > 0" class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="tag in form.tags"
                                                    :key="tag"
                                                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 border border-primary/20 pl-2 pr-1 py-1 text-[11px] font-semibold text-primary"
                                                >
                                                    {{ tag }}
                                                    <button type="button" @click="form.tags = form.tags.filter(t => t !== tag)" class="ml-0.5 h-4 w-4 flex items-center justify-center rounded-full hover:bg-primary/20 transition-colors">
                                                        <X class="h-2.5 w-2.5" />
                                                    </button>
                                                </span>
                                            </div>

                                            <div class="relative">
                                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                                                <input
                                                    v-model="tagSearchInput"
                                                    type="text"
                                                    placeholder="Search or add tags… (press enter to create)"
                                                    @keydown.enter.prevent="() => {
                                                        if (tagSearchInput.trim() !== '') {
                                                            const tag = filteredTags.length > 0 && props.allTags.some(t => t.toLowerCase() === tagSearchInput.trim().toLowerCase()) ? filteredTags[0] : tagSearchInput.trim();
                                                            if (!form.tags.includes(tag)) {
                                                                form.tags.push(tag);
                                                            }
                                                            tagSearchInput = '';
                                                        }
                                                    }"
                                                    class="w-full rounded-lg border border-input bg-transparent pl-8 pr-3 py-2 text-xs shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                />
                                            </div>
                                            
                                            <div class="handler-list max-h-36 overflow-y-auto rounded-lg border border-border/50 divide-y divide-border/40 bg-muted/10">
                                                <button
                                                    v-for="tag in filteredTags"
                                                    :key="tag"
                                                    type="button"
                                                    @click="() => {
                                                        form.tags = form.tags.includes(tag) ? form.tags.filter(t => t !== tag) : [...form.tags, tag];
                                                    }"
                                                    :class="['w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors', form.tags.includes(tag) ? 'bg-primary/10 text-primary' : 'hover:bg-muted/50 text-foreground']"
                                                >
                                                    <span class="text-xs font-medium truncate">{{ tag }}</span>
                                                    <CheckCircle2 v-if="form.tags.includes(tag)" class="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />
                                                </button>
                                                
                                                <button
                                                    v-if="tagSearchInput.trim() !== '' && !props.allTags.some(t => t.toLowerCase() === tagSearchInput.trim().toLowerCase())"
                                                    type="button"
                                                    @click="() => {
                                                        if (!form.tags.includes(tagSearchInput.trim())) form.tags.push(tagSearchInput.trim());
                                                        tagSearchInput = '';
                                                    }"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors bg-primary/5 hover:bg-primary/10 text-primary"
                                                >
                                                    <span class="text-xs font-medium truncate">Create "{{ tagSearchInput.trim() }}"</span>
                                                    <Plus class="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />
                                                </button>

                                                <div v-if="filteredTags.length === 0 && tagSearchInput.trim() === ''" class="px-4 py-6 text-center text-xs text-muted-foreground/60 italic">
                                                    Type to search or create a new tag.
                                                </div>
                                            </div>

                                            <span v-if="form.errors.tags" class="text-xs text-destructive font-medium">{{ form.errors.tags }}</span>
                                            <span v-else-if="form.tags.length === 0" class="text-xs text-muted-foreground/60 italic">At least one tag is required.</span>
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
                                                <!-- Compression badge -->
                                                <div
                                                    v-if="attachmentCompression"
                                                    class="absolute bottom-2 left-2 flex items-center gap-1 rounded-full bg-background/90 border border-emerald-500/30 px-2 py-0.5 text-[10px] shadow-sm backdrop-blur-sm"
                                                >
                                                    <span class="text-muted-foreground line-through">{{ (attachmentCompression.before / 1024).toFixed(0) }}KB</span>
                                                    <span class="text-muted-foreground/50">→</span>
                                                    <span class="font-semibold text-emerald-500">{{ (attachmentCompression.after / 1024).toFixed(0) }}KB</span>
                                                    <span class="text-muted-foreground/70">·</span>
                                                    <span class="font-semibold text-emerald-500">-{{ Math.round((1 - attachmentCompression.after / attachmentCompression.before) * 100) }}%</span>
                                                </div>
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
                                                    v-for="cat in categoryOptions"
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
                                                    <component :is="cat.iconComponent" class="h-4 w-4" />
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
                                                    v-for="prio in priorityOptions"
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
                                                        <component :is="prio.iconComponent" class="h-3.5 w-3.5" :style="{ color: prio.color }" />
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
                                                    v-for="s in statusOptions.filter(s => s !== 'All')"
                                                    :key="s"
                                                    type="button"
                                                    @click="form.status = s"
                                                    :class="[
                                                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold border-2 transition-all',
                                                        form.status === s
                                                            ? 'border-current shadow-sm'
                                                            : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                                    ]"
                                                    :style="form.status === s ? getStatusStyle(s) : {}"
                                                >
                                                    <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                                    {{ s }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Handlers -->
                                        <div v-if="!isStatusNoHandlers(form.status)" class="grid gap-2">
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
                                                :disabled="form.processing || (handlerRequired && form.handler_ids.length === 0)"
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
                            ? stat.accentHex
                                ? 'border shadow-md ring-2 ring-transparent'
                                : ['border shadow-md ring-2', stat.borderActive, stat.glowClass, stat.ringClass]
                            : 'border-border/60 hover:border-border'
                    ]"
                    :style="currentStatus === stat.status && stat.accentHex ? statCardActiveAccentStyle(stat.accentHex) : {}"
                >
                    <!-- Active pip -->
                    <div
                        class="absolute left-0 inset-y-3 w-[3px] rounded-r-full transition-all duration-200"
                        :class="[
                            currentStatus === stat.status && !stat.accentHex ? stat.bgClass.replace('/10', '') : '',
                            currentStatus === stat.status ? '' : 'opacity-0',
                        ]"
                        :style="currentStatus === stat.status && stat.accentHex ? { backgroundColor: stat.accentHex } : {}"
                    />

                    <!-- Icon + value row -->
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-110 relative"
                            :class="stat.accentHex ? '' : stat.bgClass"
                            :style="stat.accentHex ? { backgroundColor: stat.accentHex + '26' } : {}"
                        >
                            <component
                                :is="stat.icon"
                                class="h-4 w-4"
                                :class="stat.accentHex ? '' : stat.colorClass"
                                :style="stat.accentHex ? { color: stat.accentHex } : {}"
                            />
                            <span
                                v-if="stat.status === primaryQueueStatusName && stat.value > 0"
                                class="absolute inset-0 rounded-lg animate-ping opacity-30"
                                :class="stat.accentHex ? '' : stat.bgClass"
                                :style="stat.accentHex ? { backgroundColor: stat.accentHex + '40' } : {}"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide truncate">{{ stat.label }}</p>
                            <p
                                class="text-2xl font-bold leading-none tabular-nums mt-0.5"
                                :class="stat.accentHex ? '' : stat.colorClass"
                                :style="stat.accentHex ? { color: stat.accentHex } : {}"
                            >{{ stat.value }}</p>
                        </div>
                    </div>

                    <!-- Percentage bar (hidden for "Total") -->
                    <template v-if="stat.status !== 'All'">
                        <div class="h-1 w-full rounded-full bg-muted/50 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-700 ease-out"
                                :class="stat.accentHex ? '' : stat.bgClass.replace('/10', '/70')"
                                :style="{
                                    width: `${ticketStats[0].value > 0 ? Math.round((stat.value / ticketStats[0].value) * 100) : 0}%`,
                                    ...(stat.accentHex ? { backgroundColor: stat.accentHex + 'b3' } : {}),
                                }"
                            />
                        </div>
                        <span
                            class="text-[10px] font-semibold tabular-nums"
                            :class="stat.accentHex ? '' : stat.colorClass + '/70'"
                            :style="stat.accentHex ? { color: stat.accentHex + 'cc' } : {}"
                        >
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
                        v-for="status in statusOptions"
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

                <!-- Category filter -->
                <div class="w-[min(11rem,42vw)] shrink-0">
                    <Select
                        :model-value="currentCategory"
                        @update:model-value="(v) => (currentCategory = typeof v === 'string' ? v : 'All')"
                    >
                        <SelectTrigger class="h-9 rounded-xl border-border/50 bg-muted/60 shadow-none focus:ring-1 focus:ring-ring">
                            <SelectValue placeholder="Category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="All">
                                All categories
                            </SelectItem>
                            <SelectItem
                                v-for="c in categoryOptions"
                                :key="c.name"
                                :value="c.name"
                            >
                                <span class="flex items-center gap-2">
                                    <component :is="c.iconComponent" class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    <span>{{ c.name }}</span>
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
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
                        v-for="p in priorityOptions"
                        :key="p.name"
                        @click="currentPriority = p.name"
                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200 shrink-0"
                        :class="currentPriority !== p.name ? 'text-muted-foreground hover:text-foreground hover:bg-background/60' : 'shadow-sm ring-1 ring-current/20'"
                        :style="currentPriority === p.name ? { color: p.color, backgroundColor: p.color + '1a' } : {}"
                    >
                        <component
                            :is="p.iconComponent"
                            class="h-3 w-3 shrink-0 transition-colors"
                            :style="currentPriority !== p.name ? { color: p.color } : {}"
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
                        v-if="currentPriority !== 'All' || currentStatus !== 'All' || currentCategory !== 'All' || search || dateFrom || dateTo"
                        @click="currentPriority = 'All'; currentStatus = 'All'; currentCategory = 'All'; search = ''; dateFrom = ''; dateTo = ''"
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
                            v-if="search || currentStatus !== 'All' || currentPriority !== 'All' || currentCategory !== 'All' || dateFrom || dateTo"
                            @click="search = ''; currentStatus = 'All'; currentPriority = 'All'; currentCategory = 'All'; dateFrom = ''; dateTo = ''"
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
                            <div class="w-1 shrink-0 rounded-none" :style="statusStripeGradientStyle(ticket.status)" />

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
                                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 border']" :style="getStatusStyle(ticket.status)">
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
                                                        @click="isStatusNoHandlers(ticket.status) ? openAssignModal(ticket, 'Resolved') : updateStatus(ticket, 'Resolved')"
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
                                        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase border" :style="getPriorityStyle(ticket.priority)">
                                            <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                            {{ ticket.priority }}
                                        </span>
                                        <span class="inline-flex items-center rounded-md bg-muted/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase tracking-tight border border-border/40">
                                            {{ ticket.category }}
                                        </span>
                                        <Badge v-for="tag in ticket.tags" :key="tag" variant="secondary" class="px-1.5 py-0.5 text-[9px] font-medium border-border/50">
                                            {{ tag }}
                                        </Badge>
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
                                            <template v-if="ticket.commentsCount > 0">
                                                <MessageSquare class="h-3 w-3" />
                                                <span class="mr-1">{{ ticket.commentsCount }}</span>
                                            </template>
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
                                                <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase border" :style="getPriorityStyle(ticket.priority)">
                                                    <component :is="getPriorityIcon(ticket.priority)" class="h-2.5 w-2.5" />
                                                    {{ ticket.priority }}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground/40">·</span>
                                                <span class="text-[10px] font-medium text-muted-foreground/70 uppercase tracking-tight">{{ ticket.category }}</span>
                                                <Badge v-for="tag in ticket.tags" :key="tag" variant="secondary" class="ml-1 px-1.5 py-0 text-[9px] font-medium border-border/50">
                                                    {{ tag }}
                                                </Badge>
                                                <span class="text-[10px] text-muted-foreground/40">·</span>
                                                <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                    <Clock class="h-2.5 w-2.5" />
                                                    {{ ticket.createdAt }}
                                                </div>
                                                <template v-if="ticket.commentsCount > 0">
                                                    <span class="text-[10px] text-muted-foreground/40">·</span>
                                                    <div class="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                                        <MessageSquare class="h-2.5 w-2.5" />
                                                        {{ ticket.commentsCount }}
                                                    </div>
                                                </template>
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
                                        <Badge variant="outline" :class="['inline-flex items-center gap-1 whitespace-nowrap text-[10px] font-bold px-2 py-1 border']" :style="getStatusStyle(ticket.status)">
                                            <component :is="getStatusIcon(ticket.status)" class="h-3 w-3 shrink-0" />
                                            {{ ticket.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3.5 text-right pr-5" @click.stop>
                                        <!-- Inline quick actions (visible on hover) -->
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                v-if="ticket.status !== 'Resolved' && ticket.status !== 'Closed'"
                                                @click.stop="isStatusNoHandlers(ticket.status) ? openAssignModal(ticket, 'Resolved') : updateStatus(ticket, 'Resolved')"
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
                                                        @click="isStatusNoHandlers(ticket.status) ? openAssignModal(ticket, 'Resolved') : updateStatus(ticket, 'Resolved')"
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

        <TicketDetailModal
            v-model="isDetailModalOpen"
            :ticket="selectedTicket"
            :priorities="priorities"
            :statuses="statuses"
            @edit="(t) => openEditModal(t as typeof props.tickets[0])"
        />

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
                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border']" :style="getStatusStyle(assigningTicket.status)">
                                <component :is="getStatusIcon(assigningTicket.status)" class="h-3 w-3" />
                                {{ assigningTicket.status }}
                            </Badge>
                            <!-- Arrow + new status preview (Open tickets only) -->
                            <template v-if="isStatusNoHandlers(assigningTicket.status)">
                                <span class="text-muted-foreground/40 text-xs">→</span>
                                <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border']" :style="getStatusStyle(assignStatusOverride)">
                                    <component :is="getStatusIcon(assignStatusOverride)" class="h-3 w-3" />
                                    {{ assignStatusOverride }}
                                </Badge>
                            </template>
                        </div>
                        <DialogTitle class="text-base font-bold tracking-tight leading-snug flex items-center gap-2">
                            <UserPlus class="h-4 w-4 text-primary shrink-0" />
                            {{ isStatusNoHandlers(assigningTicket.status) ? 'Assign Handler & Update Status' : 'Assign Handlers' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground/80 truncate mt-0.5">
                            {{ assigningTicket.title }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="modal-body flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-5">

                    <!-- ① Status picker — only for Open tickets -->
                    <div v-if="isStatusNoHandlers(assigningTicket.status)" class="flex flex-col gap-2.5">
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
                                :style="assignStatusOverride === s.name ? getStatusStyle(s.name) : {}"
                            >
                                <component :is="getStatusIcon(s.name)" class="h-3 w-3" />
                                {{ s.name }}
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
                                :disabled="assignForm.processing || assignForm.handler_ids.length === 0"
                                @click="submitAssign"
                                class="text-xs font-bold gap-1.5 shadow-sm shadow-primary/20"
                            >
                                <span v-if="!assignForm.processing" class="flex items-center gap-1.5">
                                    <UserPlus class="h-3.5 w-3.5" />
                                    {{ isStatusNoHandlers(assigningTicket.status) ? 'Assign & Update' : 'Save Handlers' }}
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
                            <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border']" :style="getStatusStyle(changeStatusTicket.status)">
                                <component :is="getStatusIcon(changeStatusTicket.status)" class="h-3 w-3" />
                                {{ changeStatusTicket.status }}
                            </Badge>
                            <template v-if="changeStatusValue">
                                <span class="text-muted-foreground/40 text-xs">→</span>
                                <Badge variant="outline" :class="['inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 border']" :style="getStatusStyle(changeStatusValue)">
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
                                        ? 'border-current shadow-sm scale-[1.03]'
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                ]"
                                :style="changeStatusValue === s ? getStatusStyle(s) : {}"
                            >
                                <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <!-- ② Handler section — hidden when new status is Open -->
                    <div v-if="changeStatusValue && !isStatusNoHandlers(changeStatusValue)" class="h-px bg-border/50" />

                    <!-- Currently assigned tags -->
                    <div v-if="changeStatusValue && !isStatusNoHandlers(changeStatusValue)" class="flex flex-col gap-2">
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
                                {{ isStatusHandlerOptional(changeStatusValue) ? 'No handlers assigned. Optionally add one below.' : 'At least one handler is required for this status.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Search + user list -->
                    <div v-if="changeStatusValue && !isStatusNoHandlers(changeStatusValue)" class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            Update Handlers
                            <span v-if="isStatusHandlerOptional(changeStatusValue)" class="normal-case font-normal text-muted-foreground/50">(optional)</span>
                            <span v-else-if="isStatusHandlerRequired(changeStatusValue)" class="inline-flex items-center rounded-md bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-500">Required</span>
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
                                :disabled="changeStatusForm.processing || !changeStatusValue || (isStatusHandlerRequired(changeStatusValue) && changeStatusForm.handler_ids.length === 0)"
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
                                        ? 'border-current shadow-sm scale-[1.03]'
                                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50'
                                ]"
                                :style="bulkStatusValue === s ? getStatusStyle(s) : {}"
                            >
                                <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                {{ s }}
                            </button>
                        </div>
                        <!-- Open notice -->
                        <div v-if="isStatusNoHandlers(bulkStatusValue)" class="flex items-start gap-2.5 rounded-xl border border-rose-500/20 bg-rose-500/5 px-3.5 py-2.5">
                            <AlertTriangle class="h-3.5 w-3.5 text-rose-500 shrink-0 mt-0.5" />
                            <p class="text-xs text-rose-600 dark:text-rose-400 leading-relaxed">
                                All handlers will be <span class="font-semibold">removed</span> from the selected tickets when using a status that does not use handlers.
                            </p>
                        </div>
                    </div>

                    <!-- ② Handler section — hidden when new status is Open -->
                    <div v-if="!isStatusNoHandlers(bulkStatusValue)" class="h-px bg-border/50" />

                    <!-- Currently selected handler tags -->
                    <div v-if="!isStatusNoHandlers(bulkStatusValue)" class="flex flex-col gap-2">
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
                                {{ isStatusHandlerOptional(bulkStatusValue) ? 'No handlers. Optionally assign one below.' : 'At least one handler is required for this status.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Search + user list -->
                    <div v-if="!isStatusNoHandlers(bulkStatusValue)" class="flex flex-col gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            Assign Handlers
                            <span v-if="isStatusHandlerOptional(bulkStatusValue)" class="normal-case font-normal text-muted-foreground/50">(optional)</span>
                            <span v-else-if="isStatusHandlerRequired(bulkStatusValue)" class="inline-flex items-center rounded-md bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-500">Required</span>
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
                            <template v-if="!isStatusNoHandlers(bulkStatusValue)">
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
                                :disabled="bulkStatusForm.processing || !bulkStatusValue || (isStatusHandlerRequired(bulkStatusValue) && bulkStatusHandlerIds.length === 0)"
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
</style>
