<script setup lang="ts">
import TicketDetailModal from '@/components/TicketDetailModal.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TicketDetail } from '@/types/ticketDetail';
import { Head } from '@inertiajs/vue3';
import { FileText, Hash, Lightbulb, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Solution {
    ticket_id: number;
    ticket_title: string;
    solution: string;
    tags: string[];
}

interface TagWithSolutions {
    id: number;
    name: string;
    solutions: Solution[];
}

const props = defineProps<{
    tags: TagWithSolutions[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Solutions', href: route('admin.solutions') },
];

const searchQuery = ref('');
const activeTagId = ref<number | null>(props.tags.length > 0 ? props.tags[0].id : null);

const totalSolutions = computed(() => {
    return props.tags.reduce((acc, tag) => acc + tag.solutions.length, 0);
});

// When searching, switch to the first tag that has matching results
watch(searchQuery, () => {
    if (searchQuery.value) {
        const firstMatchingTag = filteredTagsForSearch.value[0];
        if (firstMatchingTag) {
            activeTagId.value = firstMatchingTag.id;
        }
    }
});

// Create a computed property that only handles search filtering
const filteredTagsForSearch = computed(() => {
    let result = props.tags;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();

        result = result
            .map((tag) => {
                const tagMatches = tag.name.toLowerCase().includes(query);

                const matchingSolutions = tag.solutions.filter(
                    (s) =>
                        s.ticket_title.toLowerCase().includes(query) ||
                        (s.solution && s.solution.toLowerCase().includes(query)) ||
                        (s.tags && s.tags.some((t) => t.toLowerCase().includes(query))) ||
                        `tkt-${1000 + s.ticket_id}`.includes(query),
                );

                return {
                    ...tag,
                    solutions: tagMatches ? tag.solutions : matchingSolutions,
                };
            })
            .filter((tag) => tag.solutions.length > 0);
    }

    return result;
});

// This handles the final output by combining search results and the active tag
const filteredTags = computed(() => {
    if (activeTagId.value !== null) {
        return filteredTagsForSearch.value.filter((tag) => tag.id === activeTagId.value);
    }
    return [];
});

// ── Ticket Detail Modal ────────────────────────────────────────────────────
const isDetailModalOpen = ref(false);
const detailModalTicket = ref<TicketDetail | null>(null);
const detailModalLoading = ref(false);
const detailModalPriorities = ref<{ id: number; name: string; icon: string; color: string }[]>([]);
const detailModalStatuses = ref<{ id: number; name: string; icon: string; color: string; handler_requirement?: string }[]>([]);

async function openTicketDetail(ticketId: number) {
    isDetailModalOpen.value = true;
    detailModalLoading.value = true;
    detailModalTicket.value = null;

    try {
        const res = await fetch(route('tickets.detail-json', { ticket: ticketId }));
        if (res.ok) {
            const data = (await res.json()) as {
                ticket: TicketDetail;
                priorities: typeof detailModalPriorities.value;
                statuses: typeof detailModalStatuses.value;
            };
            detailModalTicket.value = data.ticket;
            detailModalPriorities.value = data.priorities || [];
            detailModalStatuses.value = data.statuses || [];
        } else {
            isDetailModalOpen.value = false;
            console.error('Failed to load ticket details.');
        }
    } catch (e) {
        isDetailModalOpen.value = false;
        console.error(e);
    } finally {
        detailModalLoading.value = false;
    }
}
</script>

<template>
    <Head title="Solutions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
            <!-- Header section -->
            <div class="flex flex-col gap-2 border-b border-border/40 pb-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Lightbulb class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Solutions Knowledge Base</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">Browse successful solutions from resolved tickets.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-start gap-8 md:flex-row">
                <!-- Left Sidebar (Navigation & Search) -->
                <div class="flex w-full shrink-0 flex-col gap-5 md:sticky md:top-6 md:w-72">
                    <div class="group relative">
                        <Search
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition-colors group-focus-within:text-primary"
                        />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search solutions..."
                            class="h-10 border-border/50 bg-card pl-9 shadow-sm transition-all focus-visible:ring-primary/20"
                        />
                    </div>

                    <div class="flex flex-col overflow-hidden rounded-xl border border-border/50 bg-card shadow-sm">
                        <div class="flex items-center justify-between border-b border-border/50 bg-muted/30 px-4 py-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Categories</h3>
                            <Badge variant="secondary" class="h-5 px-1.5 py-0 text-[10px]">{{ filteredTagsForSearch.length }}</Badge>
                        </div>
                        <div class="flex max-h-[calc(100vh-240px)] flex-col gap-0.5 overflow-y-auto p-2">
                            <button
                                v-for="tag in filteredTagsForSearch"
                                :key="tag.id"
                                @click="activeTagId = tag.id"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm transition-all duration-200"
                                :class="activeTagId === tag.id ? 'bg-primary text-primary-foreground shadow-md' : 'text-foreground/80 hover:bg-muted'"
                            >
                                <span class="flex items-center gap-2 truncate pr-2">
                                    <Hash class="h-4 w-4 shrink-0" :class="activeTagId === tag.id ? 'opacity-90' : 'opacity-50'" />
                                    <span class="truncate font-medium">{{ tag.name }}</span>
                                </span>
                                <Badge
                                    :variant="activeTagId === tag.id ? 'secondary' : 'outline'"
                                    class="shrink-0 border-none text-[10px]"
                                    :class="
                                        activeTagId === tag.id
                                            ? 'bg-primary-foreground/20 text-primary-foreground hover:bg-primary-foreground/20'
                                            : 'bg-muted-foreground/10'
                                    "
                                >
                                    {{ tag.solutions.length }}
                                </Badge>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Content (Solutions Feed) -->
                <div class="flex w-full min-w-0 flex-1 flex-col gap-6">
                    <!-- Empty State -->
                    <div
                        v-if="filteredTagsForSearch.length === 0"
                        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/50 bg-card/30 py-24 text-center shadow-sm"
                    >
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted ring-8 ring-muted/30">
                            <Search class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <h3 class="text-lg font-semibold tracking-tight">No solutions found</h3>
                        <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-muted-foreground">
                            We couldn't find any solutions matching your search criteria.
                        </p>
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="mt-5 rounded-full bg-primary/10 px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/20"
                        >
                            Clear search
                        </button>
                    </div>

                    <!-- Tag Groups -->
                    <template v-else>
                        <div
                            v-for="tag in filteredTags"
                            :key="tag.id"
                            class="flex flex-col gap-5 duration-500 animate-in fade-in slide-in-from-bottom-4"
                        >
                            <!-- Group Header -->
                            <div class="flex items-end justify-between border-b border-border/40 pb-3">
                                <div class="flex flex-col gap-1">
                                    <h2 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                                        {{ tag.name }}
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Found {{ tag.solutions.length }} {{ tag.solutions.length === 1 ? 'solution' : 'solutions' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Solutions List -->
                            <div class="flex flex-col gap-4">
                                <Card
                                    v-for="solution in tag.solutions"
                                    :key="solution.ticket_id"
                                    class="group cursor-pointer overflow-hidden bg-card shadow-sm transition-all duration-300 hover:border-primary/30 hover:shadow-md"
                                    @click="openTicketDetail(solution.ticket_id)"
                                >
                                    <div class="p-5 sm:p-6">
                                        <div class="mb-4 flex items-start justify-between gap-4">
                                            <div class="flex items-start gap-3.5">
                                                <div
                                                    class="mt-0.5 shrink-0 rounded-lg bg-emerald-500/10 p-2 text-emerald-600 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-500/20 dark:text-emerald-400"
                                                >
                                                    <FileText class="h-4 w-4" />
                                                </div>
                                                <div class="flex flex-col gap-1.5">
                                                    <h3
                                                        class="text-base font-semibold leading-tight text-foreground/90 transition-colors group-hover:text-primary sm:text-lg"
                                                    >
                                                        {{ solution.ticket_title }}
                                                    </h3>
                                                    <div class="flex items-center gap-2">
                                                        <Badge
                                                            variant="secondary"
                                                            class="bg-muted font-mono text-[10px] font-bold uppercase tracking-wider opacity-80"
                                                        >
                                                            TKT-{{ 1000 + solution.ticket_id }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ml-0 sm:ml-11">
                                            <div class="relative">
                                                <div class="absolute bottom-0 left-0 top-0 w-1 rounded-full bg-emerald-500/20"></div>
                                                <div
                                                    class="prose prose-sm dark:prose-invert prose-p:leading-relaxed max-w-none py-0.5 pl-4 text-muted-foreground/90"
                                                    v-html="solution.solution"
                                                ></div>
                                            </div>

                                            <div
                                                class="mt-5 flex flex-wrap items-center gap-1.5 border-t border-border/40 pt-4"
                                                v-if="solution.tags && solution.tags.length > 0"
                                            >
                                                <Badge
                                                    v-for="t in solution.tags"
                                                    :key="t"
                                                    variant="outline"
                                                    class="border-border/50 bg-muted/30 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                                >
                                                    {{ t }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Ticket Detail Modal -->
        <TicketDetailModal
            v-model="isDetailModalOpen"
            :ticket="detailModalTicket"
            :priorities="detailModalPriorities"
            :statuses="detailModalStatuses"
            :loading="detailModalLoading"
            :show-edit-button="false"
            :show-open-in-tickets-button="true"
        />
    </AppLayout>
</template>
