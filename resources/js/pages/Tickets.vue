<script setup lang="ts">
import type {
    IncidentCategoryOption,
    IncidentPriorityOption,
    IncidentStatusOption,
    IncidentTicketRow,
} from '@/components/incidents/incidentFormTypes';
import IncidentCreateEditDialog from '@/components/incidents/IncidentCreateEditDialog.vue';
import IncidentsFiltersToolbar from '@/components/incidents/IncidentsFiltersToolbar.vue';
import IncidentsKpiStrip from '@/components/incidents/IncidentsKpiStrip.vue';
import IncidentsListPanel from '@/components/incidents/IncidentsListPanel.vue';
import IncidentsPageHeader from '@/components/incidents/IncidentsPageHeader.vue';
import { useIncidentsList } from '@/composables/useIncidentsList';
import { ensureLucideIconsLoaded, lucideAllIconMap, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import { laravelFetch } from '@/lib/laravelFetch';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import TicketDetailModal from '@/components/TicketDetailModal.vue';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import RichTextEditor from '@/components/RichTextEditor.vue';
import {
    AlertTriangle,
    CheckCircle2,
    Circle,
    RefreshCcw,
    Search,
    Trash2,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, toRef, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Incidents',
        href: route('tickets'),
    },
];

const sessionExpiredBanner = ref(false);

const checkCsrfExpired = () => {
    if (sessionStorage.getItem('csrf_expired')) {
        sessionStorage.removeItem('csrf_expired');
        sessionExpiredBanner.value = true;
        setTimeout(() => {
            sessionExpiredBanner.value = false;
        }, 6000);
    }
};

const page = usePage();

const props = defineProps<{
    tickets: IncidentTicketRow[];
    users: { id: number; name: string }[];
    categories: IncidentCategoryOption[];
    priorities: IncidentPriorityOption[];
    statuses: IncidentStatusOption[];
    allTags: string[];
}>();

const {
    search,
    currentStatus,
    currentPriority,
    currentCategory,
    selectedIds,
    dateFrom,
    dateTo,
    sortKey,
    sortDir,
    PAGE_SIZE_OPTIONS,
    pageSize,
    currentPage,
    statusOptions,
    categoryOptions,
    priorityOptions,
    primaryQueueStatusName,
    ticketStats,
    sortedTickets,
    totalPages,
    paginatedTickets,
    pageRange,
    priorityCounts,
    isAllSelected,
    toggleSelectAll,
    toggleTicket,
    toggleSort,
    resetFilters,
} = useIncidentsList(toRef(props, 'tickets'), toRef(props, 'categories'), toRef(props, 'priorities'), toRef(props, 'statuses'));

const listPanelRef = ref<InstanceType<typeof IncidentsListPanel> | null>(null);

const openQueueCount = computed(
    () => ticketStats.value.find((s) => s.status === primaryQueueStatusName.value)?.value ?? 0,
);

const showFiltersReset = computed(
    () =>
        currentPriority.value !== 'All' ||
        currentStatus.value !== 'All' ||
        currentCategory.value !== 'All' ||
        !!search.value ||
        !!dateFrom.value ||
        !!dateTo.value,
);

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

const assignModalTargetStatuses = computed(() => props.statuses.filter((s) => s.handler_requirement === 'required'));

const isCreateModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const selectedTicket = ref<IncidentTicketRow | null>(null);
const editingTicket = ref<IncidentTicketRow | null>(null);
const attachmentPreview = ref<string | null>(null);
const attachmentCompression = ref<{ before: number; after: number } | null>(null);

const openDetailModal = (ticket: IncidentTicketRow) => {
    selectedTicket.value = ticket;
    isDetailModalOpen.value = true;
};

const openEditModal = (ticket: IncidentTicketRow) => {
    editingTicket.value = ticket;
    form.title = ticket.title;
    form.description = ticket.description ?? '';
    form.priority = ticket.priority;
    form.ticket_category_id = ticket.ticketCategoryId ?? props.categories[0]?.id ?? null;
    form.status = ticket.status;
    form.handler_ids = [...ticket.handlerIds];
    form.tags = [...ticket.tags];
    form.solution = ticket.solution ?? '';
    form.attachment = null;
    attachmentPreview.value = ticket.attachmentUrl ?? null;
    attachmentCompression.value = null;
    isCreateModalOpen.value = true;
};

const form = useForm({
    title: '',
    description: '',
    priority: 'Medium',
    ticket_category_id: props.categories[0]?.id ?? (null as number | null),
    status: props.statuses.find((s) => s.handler_requirement === 'none')?.name ?? props.statuses[0]?.name ?? 'Open',
    handler_ids: [] as number[],
    tags: [] as string[],
    solution: '',
    attachment: null as File | null,
});

const isAssignModalOpen = ref(false);
const assigningTicket = ref<IncidentTicketRow | null>(null);
const assignHandlerSearch = ref('');
const assignStatusOverride = ref<string>(
    props.statuses.find((s) => s.handler_requirement === 'required')?.name ?? 'In Progress',
);

const assignForm = useForm({
    handler_ids: [] as number[],
    solution: '',
});

const filteredAssignUsers = computed(() => {
    if (!assignHandlerSearch.value.trim()) {
        return props.users;
    }
    const q = assignHandlerSearch.value.toLowerCase();
    return props.users.filter((u) => u.name.toLowerCase().includes(q));
});

const openAssignModal = (ticket: IncidentTicketRow, defaultStatus?: string) => {
    assigningTicket.value = ticket;
    assignForm.handler_ids = [...ticket.handlerIds];
    assignHandlerSearch.value = '';
    assignStatusOverride.value = defaultStatus ?? firstRequiredStatusName.value;
    isAssignModalOpen.value = true;
};

const submitAssign = () => {
    if (!assigningTicket.value) {
        return;
    }
    const ticket = assigningTicket.value;
    const isQueueTicket = isStatusNoHandlers(ticket.status);
    assignForm
        .transform((data) => ({
            handler_ids: data.handler_ids,
            ...(isQueueTicket ? { status: assignStatusOverride.value } : {}),
            ...(assignStatusOverride.value === 'Resolved' || ticket.status === 'Resolved' ? { solution: data.solution } : {}),
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

const statusProcessing = ref<number | null>(null);

const updateStatus = (ticket: IncidentTicketRow, status: string) => {
    statusProcessing.value = ticket.numericId;
    router.patch(
        route('tickets.status.update', { ticket: ticket.numericId }),
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                statusProcessing.value = null;
            },
        },
    );
};

const isBulkStatusModalOpen = ref(false);
const bulkStatusValue = ref(props.statuses.find((s) => s.handler_requirement === 'required')?.name ?? 'In Progress');
const bulkStatusHandlerSearch = ref('');
const bulkStatusHandlerIds = ref<number[]>([]);
const bulkStatusSolution = ref('');

const filteredBulkStatusUsers = computed(() => {
    if (!bulkStatusHandlerSearch.value.trim()) {
        return props.users;
    }
    const q = bulkStatusHandlerSearch.value.toLowerCase();
    return props.users.filter((u) => u.name.toLowerCase().includes(q));
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

const isDeleteModalOpen = ref(false);
const deleteTarget = ref<IncidentTicketRow | null>(null);
const deleteForm = useForm({});

const openDeleteModal = (ticket: IncidentTicketRow) => {
    deleteTarget.value = ticket;
    isDeleteModalOpen.value = true;
};

const submitDelete = () => {
    if (!deleteTarget.value) {
        return;
    }
    deleteForm.delete(route('tickets.destroy', deleteTarget.value.numericId), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            deleteTarget.value = null;
        },
    });
};

const ALL_STATUSES = computed(() => props.statuses.map((s) => s.name));

const isChangeStatusModalOpen = ref(false);
const changeStatusTicket = ref<IncidentTicketRow | null>(null);
const changeStatusValue = ref('');
const changeStatusHandlerSearch = ref('');

const changeStatusForm = useForm({
    handler_ids: [] as number[],
    solution: '',
});

const filteredChangeStatusUsers = computed(() => {
    if (!changeStatusHandlerSearch.value.trim()) {
        return props.users;
    }
    const q = changeStatusHandlerSearch.value.toLowerCase();
    return props.users.filter((u) => u.name.toLowerCase().includes(q));
});

const changeStatusOptions = computed(() => ALL_STATUSES.value.filter((s) => s !== changeStatusTicket.value?.status));

watch(changeStatusValue, (val) => {
    if (val === 'Open') {
        changeStatusForm.handler_ids = [];
        changeStatusHandlerSearch.value = '';
    }
    if (val !== 'Resolved') {
        changeStatusForm.solution = '';
    }
});

const openChangeStatusModal = (ticket: IncidentTicketRow, defaultStatus?: string) => {
    changeStatusTicket.value = ticket;
    changeStatusHandlerSearch.value = '';

    if (defaultStatus && changeStatusOptions.value.includes(defaultStatus)) {
        changeStatusValue.value = defaultStatus;
    } else {
        changeStatusValue.value =
            changeStatusOptions.value.find((s) => !isStatusNoHandlers(s)) ?? changeStatusOptions.value[0] ?? '';
    }
    if (!isStatusNoHandlers(changeStatusValue.value)) {
        changeStatusForm.handler_ids = [...ticket.handlerIds];
    } else {
        changeStatusForm.handler_ids = [];
    }
    changeStatusForm.solution = '';
    isChangeStatusModalOpen.value = true;
};

const submitChangeStatus = () => {
    if (!changeStatusTicket.value) {
        return;
    }
    const ticket = changeStatusTicket.value;
    changeStatusForm
        .transform((data) => ({
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

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if (
        e.key === '/' &&
        document.activeElement?.tagName !== 'INPUT' &&
        document.activeElement?.tagName !== 'TEXTAREA' &&
        !(document.activeElement as HTMLElement)?.isContentEditable
    ) {
        e.preventDefault();
        listPanelRef.value?.searchInputRef?.focus();
    }
};

const isExporting = ref(false);

const exportToExcel = async () => {
    if (isExporting.value) {
        return;
    }
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

        const sheet = workbook.addWorksheet('Incidents', {
            views: [{ state: 'frozen', ySplit: 1 }],
        });

        sheet.columns = [
            { key: 'ticketId', header: 'Incident ID', width: 13 },
            { key: 'title', header: 'Title', width: 42 },
            { key: 'status', header: 'Status', width: 14 },
            { key: 'priority', header: 'Priority', width: 12 },
            { key: 'category', header: 'Category', width: 14 },
            { key: 'tags', header: 'Tags', width: 25 },
            { key: 'reporter', header: 'Reporter', width: 22 },
            { key: 'handlers', header: 'Handler(s)', width: 28 },
            { key: 'createdAt', header: 'Created At', width: 22 },
            { key: 'resolvedAt', header: 'Resolved At', width: 22 },
            { key: 'solution', header: 'Solution', width: 50 },
        ];

        const headerRow = sheet.getRow(1);
        headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
        headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } };
        headerRow.height = 22;
        headerRow.alignment = { vertical: 'middle', horizontal: 'left' };
        headerRow.eachCell((cell) => {
            cell.border = {
                bottom: { style: 'thin', color: { argb: 'FF334155' } },
            };
        });

        const hexToExcelSoftFill = (hex: string): string | undefined => {
            const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
            if (!m) {
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
            Critical: 'FFFEE2E2',
            High: 'FFFFEEDD',
            Medium: 'FFDBEAFE',
            Low: 'FFF8FAFC',
        };

        const stripHtml = (html: string | null | undefined): string => {
            if (!html) {
                return '—';
            }
            return (
                html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').trim() ||
                '—'
            );
        };

        sortedTickets.value.forEach((t, idx) => {
            const row = sheet.addRow({
                ticketId: t.id,
                title: t.title,
                status: t.status,
                priority: t.priority,
                category: t.category,
                tags: t.tags.join(', ') || '—',
                reporter: t.reporter,
                handlers: t.handlers.map((h) => h.name).join(', ') || '—',
                createdAt: t.createdAtFormatted,
                resolvedAt: t.resolvedAtFormatted ?? '—',
                solution: stripHtml(t.solution),
            });

            row.height = 18;
            row.font = { size: 10 };
            row.alignment = { vertical: 'middle', wrapText: false };

            const rowBg = idx % 2 === 0 ? 'FFFFFFFF' : 'FFF8FAFC';
            row.eachCell({ includeEmpty: true }, (cell) => {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: rowBg } };
                cell.border = {
                    bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } },
                };
            });

            const statusCell = row.getCell('status');
            if (statusColors[t.status]) {
                statusCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: statusColors[t.status] } };
                statusCell.font = { size: 10, bold: true };
            }

            const priorityCell = row.getCell('priority');
            if (priorityColors[t.priority]) {
                priorityCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: priorityColors[t.priority] } };
                priorityCell.font = { size: 10, bold: true };
            }

            if (t.resolvedAtFormatted) {
                const resolvedCell = row.getCell('resolvedAt');
                resolvedCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD1FAE5' } };
                resolvedCell.font = { size: 10, color: { argb: 'FF065F46' } };
            }

            const solutionCell = row.getCell('solution');
            solutionCell.alignment = { vertical: 'top', wrapText: true };
            if (t.solution) {
                solutionCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFBEB' } };
            }
        });

        sheet.autoFilter = { from: 'A1', to: 'K1' };

        const dateStr = new Date().toISOString().slice(0, 10);
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `incidents-${dateStr}.xlsx`;
        a.click();
        URL.revokeObjectURL(url);
    } finally {
        isExporting.value = false;
    }
};

const submit = () => {
    if (form.tags.length === 0) {
        form.setError('tags', 'At least one tag is required.');
        return;
    }

    if (editingTicket.value) {
        form
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(route('tickets.update', { ticket: editingTicket.value!.numericId }), {
                forceFormData: true,
                onSuccess: () => {
                    isCreateModalOpen.value = false;
                    form.reset();
                    attachmentPreview.value = null;
                    attachmentCompression.value = null;
                    editingTicket.value = null;
                },
            });
    } else {
        form.post(route('tickets.store'), {
            forceFormData: true,
            onSuccess: () => {
                isCreateModalOpen.value = false;
                form.reset();
                attachmentPreview.value = null;
                attachmentCompression.value = null;
            },
        });
    }
};

watch(isCreateModalOpen, (val) => {
    if (val && !editingTicket.value) {
        form.reset();
        form.status = defaultNewTicketStatusName.value;
        form.ticket_category_id = props.categories[0]?.id ?? null;
        attachmentPreview.value = null;
        attachmentCompression.value = null;
    }
    if (!val) {
        editingTicket.value = null;
        attachmentPreview.value = null;
        attachmentCompression.value = null;
    }
});

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

const removeFinishListener = router.on('finish', checkCsrfExpired);

onMounted(() => {
    checkCsrfExpired();
    checkQueryForTicket();
    void ensureLucideIconsLoaded();
    document.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown);
    removeFinishListener();
});

watch(() => page.url, () => {
    checkQueryForTicket();
});

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
    if (!found?.color) {
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

const getInitials = (name: string) => {
    if (name === 'Unassigned') {
        return 'UN';
    }
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
};
</script>

<template>
    <Head title="Incidents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                leave-active-class="transition-all duration-200 ease-in"
                enter-from-class="-translate-y-2 opacity-0"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div
                    v-if="sessionExpiredBanner"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/25 dark:bg-amber-500/10"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500 dark:text-amber-400" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">Security token refreshed</p>
                        <p class="mt-0.5 text-xs text-amber-600/80 dark:text-amber-400/70">
                            Your form is still open — just click the button again to complete your action.
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Dismiss"
                        class="shrink-0 text-amber-500 transition-colors hover:text-amber-700 dark:hover:text-amber-300"
                        @click="sessionExpiredBanner = false"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </Transition>

            <div
                class="flex flex-col gap-5 rounded-2xl border border-border/60 bg-gradient-to-b from-muted/40 via-background to-background p-4 shadow-sm ring-1 ring-border/30 dark:from-muted/15 dark:via-card/90 dark:to-card sm:p-5 md:gap-6 md:p-6"
            >
                <IncidentsPageHeader
                    :open-count="openQueueCount"
                    :primary-queue-label="primaryQueueStatusName"
                    :is-exporting="isExporting"
                    :export-disabled="isExporting || sortedTickets.length === 0"
                    @export="exportToExcel"
                    @create="isCreateModalOpen = true"
                />
                <IncidentsKpiStrip
                    :ticket-stats="ticketStats"
                    :current-status="currentStatus"
                    :primary-queue-status-name="primaryQueueStatusName"
                    @update:current-status="(v) => (currentStatus = v)"
                />
            </div>

            <IncidentsFiltersToolbar
                :status-options="statusOptions"
                :current-status="currentStatus"
                :category-options="categoryOptions"
                :current-category="currentCategory"
                :date-from="dateFrom"
                :date-to="dateTo"
                :priority-options="priorityOptions"
                :current-priority="currentPriority"
                :priority-counts="priorityCounts"
                :selected-count="selectedIds.length"
                :show-reset="showFiltersReset"
                @update:current-status="(v) => (currentStatus = v)"
                @update:current-category="(v) => (currentCategory = v)"
                @update:date-from="(v) => (dateFrom = v)"
                @update:date-to="(v) => (dateTo = v)"
                @update:current-priority="(v) => (currentPriority = v)"
                @reset="resetFilters"
                @clear-selection="selectedIds = []"
            />

            <IncidentCreateEditDialog
                v-model:open="isCreateModalOpen"
                :editing-ticket="editingTicket"
                :form="form"
                :categories="categories"
                :priorities="priorities"
                :statuses="statuses"
                :users="users"
                :all-tags="allTags"
                :attachment-preview="attachmentPreview"
                :attachment-compression="attachmentCompression"
                @submit="submit"
                @update:attachment-preview="(v) => (attachmentPreview = v)"
                @update:attachment-compression="(v) => (attachmentCompression = v)"
            />

            <IncidentsListPanel
                ref="listPanelRef"
                :sorted-tickets="sortedTickets"
                :paginated-tickets="paginatedTickets"
                :search="search"
                :current-status="currentStatus"
                :current-priority="currentPriority"
                :current-category="currentCategory"
                :date-from="dateFrom"
                :date-to="dateTo"
                :selected-ids="selectedIds"
                :is-all-selected="isAllSelected"
                :sort-key="sortKey"
                :sort-dir="sortDir"
                :current-page="currentPage"
                :total-pages="totalPages"
                :page-range="pageRange"
                :page-size="pageSize"
                :page-size-options="PAGE_SIZE_OPTIONS"
                :priorities="priorities"
                :statuses="statuses"
                :status-processing="statusProcessing"
                @update:search="(v) => (search = v)"
                @update:page-size="(v) => (pageSize = v)"
                @update:current-page="(v) => (currentPage = v)"
                @toggle-select-all="toggleSelectAll"
                @toggle-ticket="(id, c) => toggleTicket(id, c)"
                @toggle-sort="toggleSort"
                @clear-filters="resetFilters"
                @open-detail="openDetailModal"
                @open-edit="openEditModal"
                @open-assign="(t, s) => openAssignModal(t, s)"
                @open-change-status="openChangeStatusModal"
                @open-delete="openDeleteModal"
                @update-status="(t, s) => updateStatus(t, s)"
            />
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                leave-active-class="transition-all duration-200 ease-in"
                enter-from-class="translate-y-4 opacity-0"
                leave-to-class="translate-y-4 opacity-0"
            >
                <div
                    v-if="selectedIds.length > 0"
                    class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-2xl border border-border/80 bg-background/95 px-4 py-2.5 shadow-2xl shadow-black/20 ring-1 ring-border/30 backdrop-blur-md"
                >
                    <div class="flex items-center gap-2 border-r border-border/50 pr-3">
                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-primary">
                            <span class="text-[9px] font-black leading-none text-primary-foreground">{{ selectedIds.length }}</span>
                        </div>
                        <span class="whitespace-nowrap text-xs font-semibold text-foreground">
                            incident{{ selectedIds.length !== 1 ? 's' : '' }} selected
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-input bg-background px-3 py-1.5 text-xs font-semibold transition-colors hover:bg-muted"
                            @click="openBulkStatusModal"
                        >
                            <RefreshCcw class="h-3 w-3" />
                            Change Status
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs font-semibold text-destructive transition-colors hover:bg-destructive/10"
                            @click="isBulkDeleteConfirmOpen = true"
                        >
                            <Trash2 class="h-3 w-3" />
                            Delete
                        </button>
                    </div>
                    <div class="border-l border-border/50 pl-3">
                        <button
                            type="button"
                            aria-label="Clear selection"
                            class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="selectedIds = []"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <TicketDetailModal
            v-model="isDetailModalOpen"
            :ticket="selectedTicket"
            :priorities="priorities"
            :statuses="statuses"
            @edit="(t) => openEditModal(t as IncidentTicketRow)"
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
                            Applies to <span class="font-semibold text-foreground">{{ selectedIds.length }}</span> selected incident{{ selectedIds.length !== 1 ? 's' : '' }}.
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
                                All handlers will be <span class="font-semibold">removed</span> from the selected incidents when using a status that does not use handlers.
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
                                    <RefreshCcw class="h-3.5 w-3.5" /> Apply to {{ selectedIds.length }} Incident{{ selectedIds.length !== 1 ? 's' : '' }}
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
                            Delete Incident?
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-1 leading-relaxed">
                            This action is permanent and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Body -->
                <div class="px-5 py-4 flex flex-col gap-3">
                    <!-- Incident preview -->
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
                                <Trash2 class="h-3.5 w-3.5" /> Delete Incident
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
                            Delete {{ selectedIds.length }} Incident{{ selectedIds.length !== 1 ? 's' : '' }}?
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
                                {{ selectedIds.length }} incident{{ selectedIds.length !== 1 ? 's' : '' }} selected
                            </p>
                            <p class="text-[11px] text-muted-foreground">All of their data will be removed.</p>
                        </div>
                    </div>

                    <!-- Warning notice -->
                    <div class="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-500/25 bg-amber-50 dark:bg-amber-500/10 px-4 py-3">
                        <AlertTriangle class="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" />
                        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
                            Handlers, attachments, and history for all selected incidents will be <span class="font-semibold">permanently removed</span>.
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
                                <Trash2 class="h-3.5 w-3.5" /> Delete {{ selectedIds.length }} Incident{{ selectedIds.length !== 1 ? 's' : '' }}
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
