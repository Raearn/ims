import type {
    IncidentCategoryOption,
    IncidentPriorityOption,
    IncidentStatusOption,
    IncidentTicketRow,
} from '@/components/incidents/incidentFormTypes';
import { lucideAllIconMap, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import { ticketMatchesCategorySubtreeFilter } from '@/lib/ticketCategoryFilter';
import { Circle, HelpCircle, Ticket } from 'lucide-vue-next';
import type { Ref } from 'vue';
import { computed, ref, watch } from 'vue';

export type TicketStatRow = {
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

export type SortKey = 'id' | 'title' | 'status' | 'priority' | 'createdAt' | 'reporter' | 'handlers';

export function useIncidentsList(
    tickets: Ref<IncidentTicketRow[]>,
    categories: Ref<IncidentCategoryOption[]>,
    priorities: Ref<IncidentPriorityOption[]>,
    statuses: Ref<IncidentStatusOption[]>,
) {
    const search = ref('');
    const currentStatus = ref('All');
    const currentPriority = ref('All');
    const currentCategory = ref('All');
    const selectedIds = ref<number[]>([]);
    const dateFrom = ref('');
    const dateTo = ref('');
    const sortKey = ref<SortKey | null>(null);
    const sortDir = ref<'asc' | 'desc'>('asc');

    const PAGE_SIZE_OPTIONS = [5, 10, 25, 50] as const;
    const pageSize = ref(10);
    const currentPage = ref(1);

    const statusOptions = computed(() => ['All', ...statuses.value.map((s) => s.name)]);

    const categoryOptions = computed(() =>
        categories.value.map((c) => ({
            ...c,
            filterLabel: c.parent_id != null ? `↳ ${c.name}` : c.name,
            iconComponent: resolveLucideIcon(c.icon, HelpCircle),
        })),
    );

    const priorityOptions = computed(() =>
        priorities.value.map((p) => ({
            ...p,
            iconComponent: resolveLucideIcon(p.icon, Circle),
        })),
    );

    const primaryQueueStatusName = computed(() => statuses.value.find((s) => s.handler_requirement === 'none')?.name ?? 'Open');

    const searchDateFiltered = computed(() => {
        let base = tickets.value;

        if (dateFrom.value) {
            base = base.filter((t) => t.createdAtRaw >= dateFrom.value);
        }
        if (dateTo.value) {
            base = base.filter((t) => t.createdAtRaw <= dateTo.value);
        }

        if (search.value.trim()) {
            const q = search.value.toLowerCase().trim();
            base = base.filter(
                (t) =>
                    t.id.toLowerCase().includes(q) ||
                    t.title.toLowerCase().includes(q) ||
                    t.reporter.toLowerCase().includes(q) ||
                    t.handlers.some((h) => h.name.toLowerCase().includes(q)) ||
                    (t.tags ?? []).some((tag) => tag.toLowerCase().includes(q)),
            );
        }

        if (currentCategory.value !== 'All') {
            const filterId = Number(currentCategory.value);
            if (Number.isFinite(filterId)) {
                base = base.filter((t) => ticketMatchesCategorySubtreeFilter(categories.value, filterId, t.ticketCategoryId, t.category));
            }
        }

        return base;
    });

    const preStatusFiltered = computed(() => {
        let base = searchDateFiltered.value;
        if (currentPriority.value !== 'All') {
            base = base.filter((t) => t.priority === currentPriority.value);
        }
        return base;
    });

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
        const statusRows: TicketStatRow[] = statuses.value.map((s) => ({
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

    const prePriorityFiltered = computed(() => {
        let base = searchDateFiltered.value;
        if (currentStatus.value !== 'All') {
            base = base.filter((t) => t.status === currentStatus.value);
        }
        return base;
    });

    const priorityOrder = computed(() => Object.fromEntries(priorities.value.map((p, i) => [p.name, priorities.value.length - i])));
    const statusOrder = computed(() => Object.fromEntries(statuses.value.map((s, i) => [s.name, i + 1])));

    const sortedTickets = computed(() => {
        let base = preStatusFiltered.value;

        if (currentStatus.value !== 'All') {
            base = base.filter((t) => t.status === currentStatus.value);
        }

        if (!sortKey.value) {
            return base;
        }
        return [...base].sort((a, b) => {
            let aVal: unknown = a[sortKey.value!];
            let bVal: unknown = b[sortKey.value!];
            if (sortKey.value === 'priority') {
                aVal = priorityOrder.value[aVal as string] ?? 0;
                bVal = priorityOrder.value[bVal as string] ?? 0;
            }
            if (sortKey.value === 'status') {
                aVal = statusOrder.value[aVal as string] ?? 0;
                bVal = statusOrder.value[bVal as string] ?? 0;
            }
            if (sortKey.value === 'handlers') {
                aVal = (a.handlers as { id: number; name: string }[]).length;
                bVal = (b.handlers as { id: number; name: string }[]).length;
            }
            if ((aVal as number | string) < (bVal as number | string)) {
                return sortDir.value === 'asc' ? -1 : 1;
            }
            if ((aVal as number | string) > (bVal as number | string)) {
                return sortDir.value === 'asc' ? 1 : -1;
            }
            return 0;
        });
    });

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
        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }
        const pages: (number | '...')[] = [1];
        if (cur > 3) {
            pages.push('...');
        }
        for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) {
            pages.push(i);
        }
        if (cur < total - 2) {
            pages.push('...');
        }
        pages.push(total);
        return pages;
    });

    const priorityCounts = computed(() => {
        const base = prePriorityFiltered.value;
        const counts: Record<string, number> = { All: base.length };
        for (const t of base) {
            counts[t.priority] = (counts[t.priority] ?? 0) + 1;
        }
        return counts;
    });

    const isAllSelected = computed(() => sortedTickets.value.length > 0 && sortedTickets.value.every((t) => selectedIds.value.includes(t.numericId)));

    const toggleSelectAll = () => {
        if (isAllSelected.value) {
            selectedIds.value = [];
        } else {
            selectedIds.value = sortedTickets.value.map((t) => t.numericId);
        }
    };

    const toggleTicket = (numericId: number, checked: boolean) => {
        if (checked) {
            if (!selectedIds.value.includes(numericId)) {
                selectedIds.value = [...selectedIds.value, numericId];
            }
        } else {
            selectedIds.value = selectedIds.value.filter((id) => id !== numericId);
        }
    };

    const toggleSort = (key: SortKey) => {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDir.value = 'asc';
        }
    };

    const resetFilters = () => {
        currentPriority.value = 'All';
        currentStatus.value = 'All';
        currentCategory.value = 'All';
        search.value = '';
        dateFrom.value = '';
        dateTo.value = '';
    };

    return {
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
        searchDateFiltered,
        preStatusFiltered,
        ticketStats,
        prePriorityFiltered,
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
    };
}
