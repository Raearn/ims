<script setup lang="ts">
import type {
    IncidentCategoryOption,
    IncidentPriorityOption,
    IncidentStatusOption,
    IncidentTicketRow,
} from '@/components/incidents/incidentFormTypes';
import IncidentsFiltersToolbar from '@/components/incidents/IncidentsFiltersToolbar.vue';
import IncidentsKpiStrip from '@/components/incidents/IncidentsKpiStrip.vue';
import IncidentsListPanel from '@/components/incidents/IncidentsListPanel.vue';
import TicketDetailModal from '@/components/TicketDetailModal.vue';
import { useIncidentsList } from '@/composables/useIncidentsList';
import { ensureLucideIconsLoaded } from '@/composables/useLucideIconRegistry';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Folder } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, toRef, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Incidents',
        href: route('home'),
    },
];

const page = usePage();

const props = defineProps<{
    tickets: IncidentTicketRow[];
    categories: IncidentCategoryOption[];
    priorities: IncidentPriorityOption[];
    statuses: IncidentStatusOption[];
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

const openQueueCount = computed(() => ticketStats.value.find((s) => s.status === primaryQueueStatusName.value)?.value ?? 0);

const showFiltersReset = computed(
    () =>
        currentPriority.value !== 'All' ||
        currentStatus.value !== 'All' ||
        currentCategory.value !== 'All' ||
        !!search.value ||
        !!dateFrom.value ||
        !!dateTo.value,
);

const statusProcessing = ref<number | null>(null);
const isDetailModalOpen = ref(false);
const selectedTicket = ref<IncidentTicketRow | null>(null);

const openDetailModal = (ticket: IncidentTicketRow) => {
    selectedTicket.value = ticket;
    isDetailModalOpen.value = true;
};

const checkQueryForTicket = () => {
    const params = new URLSearchParams(window.location.search);
    const ticketId = params.get('ticket_id');
    let urlChanged = false;

    if (ticketId) {
        const ticket = props.tickets.find((t) => t.numericId === parseInt(ticketId, 10));
        if (ticket) {
            openDetailModal(ticket);
        }
        params.delete('ticket_id');
        urlChanged = true;
    }

    if (urlChanged) {
        const path = window.location.pathname;
        const newUrl = path + (params.toString() ? `?${params.toString()}` : '');
        window.history.replaceState({}, '', newUrl);
    }
};

const removeFinishListener = router.on('finish', checkQueryForTicket);

onMounted(() => {
    checkQueryForTicket();
    void ensureLucideIconsLoaded();
});

onUnmounted(() => {
    removeFinishListener();
});

watch(
    () => page.url,
    () => {
        checkQueryForTicket();
    },
);
</script>

<template>
    <Head title="Incidents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
            <div
                class="flex flex-col gap-5 rounded-2xl border border-border/60 bg-gradient-to-b from-muted/40 via-background to-background p-4 shadow-sm ring-1 ring-border/30 dark:from-muted/15 dark:via-card/90 dark:to-card sm:p-5 md:gap-6 md:p-6"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Folder class="h-6 w-6 shrink-0 text-primary" aria-hidden="true" />
                            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Incidents</h2>
                            <span
                                v-if="openQueueCount > 0"
                                class="inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-500"
                            >
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute h-full w-full animate-ping rounded-full bg-rose-400 opacity-75" />
                                    <span class="relative h-1.5 w-1.5 rounded-full bg-rose-500" />
                                </span>
                                {{ openQueueCount }} {{ primaryQueueStatusName.toLowerCase() }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Incidents you reported or are assigned to handle. View details and join the conversation — changes to status or assignment
                            are done by admins and supervisors.
                        </p>
                    </div>
                </div>
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
                :selected-count="0"
                :show-reset="showFiltersReset"
                @update:current-status="(v) => (currentStatus = v)"
                @update:current-category="(v) => (currentCategory = v)"
                @update:date-from="(v) => (dateFrom = v)"
                @update:date-to="(v) => (dateTo = v)"
                @update:current-priority="(v) => (currentPriority = v)"
                @reset="resetFilters"
                @clear-selection="() => {}"
            />

            <IncidentsListPanel
                ref="listPanelRef"
                readonly
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
            />

            <TicketDetailModal
                v-model="isDetailModalOpen"
                :ticket="selectedTicket"
                :priorities="priorities"
                :statuses="statuses"
                :show-edit-button="false"
            />
        </div>
    </AppLayout>
</template>
