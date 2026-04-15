<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Lightbulb, Search, Hash, FileText } from 'lucide-vue-next';
import TicketDetailModal from '@/components/TicketDetailModal.vue';
import type { TicketDetail } from '@/types/ticketDetail';

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
        
        result = result.map(tag => {
            const tagMatches = tag.name.toLowerCase().includes(query);
            
            const matchingSolutions = tag.solutions.filter(s => 
                s.ticket_title.toLowerCase().includes(query) || 
                (s.solution && s.solution.toLowerCase().includes(query)) ||
                (s.tags && s.tags.some(t => t.toLowerCase().includes(query))) ||
                (`tkt-${1000 + s.ticket_id}`).includes(query)
            );
            
            return {
                ...tag,
                solutions: tagMatches ? tag.solutions : matchingSolutions
            };
        }).filter(tag => tag.solutions.length > 0);
    }

    return result;
});

// This handles the final output by combining search results and the active tag
const filteredTags = computed(() => {
    if (activeTagId.value !== null) {
        return filteredTagsForSearch.value.filter(tag => tag.id === activeTagId.value);
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
            const data = await res.json() as { 
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
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
            
            <!-- Header section -->
            <div class="flex flex-col gap-2 border-b border-border/40 pb-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Lightbulb class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Solutions Knowledge Base</h1>
                        <p class="text-sm text-muted-foreground mt-0.5">
                            Browse successful solutions from resolved tickets.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-8 items-start">
                
                <!-- Left Sidebar (Navigation & Search) -->
                <div class="w-full md:w-72 shrink-0 flex flex-col gap-5 md:sticky md:top-6">
                    <div class="relative group">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                        <Input 
                            v-model="searchQuery" 
                            type="search" 
                            placeholder="Search solutions..." 
                            class="pl-9 bg-card shadow-sm border-border/50 focus-visible:ring-primary/20 transition-all h-10"
                        />
                    </div>

                    <div class="flex flex-col rounded-xl border border-border/50 bg-card shadow-sm overflow-hidden">
                        <div class="bg-muted/30 px-4 py-3 border-b border-border/50 flex items-center justify-between">
                            <h3 class="font-semibold text-xs text-muted-foreground uppercase tracking-wider">Categories</h3>
                            <Badge variant="secondary" class="text-[10px] px-1.5 py-0 h-5">{{ filteredTagsForSearch.length }}</Badge>
                        </div>
                        <div class="p-2 flex flex-col gap-0.5 overflow-y-auto max-h-[calc(100vh-240px)]">
                            <button 
                                v-for="tag in filteredTagsForSearch" 
                                :key="tag.id"
                                @click="activeTagId = tag.id"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all duration-200"
                                :class="activeTagId === tag.id ? 'bg-primary text-primary-foreground shadow-md' : 'text-foreground/80 hover:bg-muted'"
                            >
                                <span class="flex items-center gap-2 truncate pr-2">
                                    <Hash class="h-4 w-4 shrink-0" :class="activeTagId === tag.id ? 'opacity-90' : 'opacity-50'" />
                                    <span class="truncate font-medium">{{ tag.name }}</span>
                                </span>
                                <Badge 
                                    :variant="activeTagId === tag.id ? 'secondary' : 'outline'" 
                                    class="text-[10px] shrink-0 border-none"
                                    :class="activeTagId === tag.id ? 'bg-primary-foreground/20 text-primary-foreground hover:bg-primary-foreground/20' : 'bg-muted-foreground/10'"
                                >
                                    {{ tag.solutions.length }}
                                </Badge>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Content (Solutions Feed) -->
                <div class="flex-1 w-full min-w-0 flex flex-col gap-6">
                    
                    <!-- Empty State -->
                    <div v-if="filteredTagsForSearch.length === 0" class="flex flex-col items-center justify-center py-24 text-center border border-border/50 rounded-2xl bg-card/30 border-dashed shadow-sm">
                        <div class="h-14 w-14 rounded-full bg-muted flex items-center justify-center mb-4 ring-8 ring-muted/30">
                            <Search class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <h3 class="text-lg font-semibold tracking-tight">No solutions found</h3>
                        <p class="text-sm text-muted-foreground mt-1.5 max-w-sm leading-relaxed">
                            We couldn't find any solutions matching your search criteria.
                        </p>
                        <button v-if="searchQuery" @click="searchQuery = ''" class="mt-5 text-sm bg-primary/10 text-primary hover:bg-primary/20 px-4 py-2 rounded-full font-medium transition-colors">
                            Clear search
                        </button>
                    </div>

                    <!-- Tag Groups -->
                    <template v-else>
                        <div v-for="tag in filteredTags" :key="tag.id" class="flex flex-col gap-5 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <!-- Group Header -->
                            <div class="flex items-end justify-between border-b border-border/40 pb-3">
                                <div class="flex flex-col gap-1">
                                    <h2 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                                        {{ tag.name }}
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Found {{ tag.solutions.length }} {{ tag.solutions.length === 1 ? 'solution' : 'solutions' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Solutions List -->
                            <div class="flex flex-col gap-4">
                                <Card v-for="solution in tag.solutions" :key="solution.ticket_id" 
                                      class="group overflow-hidden shadow-sm hover:shadow-md hover:border-primary/30 transition-all duration-300 cursor-pointer bg-card"
                                      @click="openTicketDetail(solution.ticket_id)">
                                    
                                    <div class="p-5 sm:p-6">
                                        <div class="flex items-start justify-between gap-4 mb-4">
                                            <div class="flex items-start gap-3.5">
                                                <div class="mt-0.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 p-2 rounded-lg shrink-0 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                                                    <FileText class="h-4 w-4" />
                                                </div>
                                                <div class="flex flex-col gap-1.5">
                                                    <h3 class="font-semibold text-base sm:text-lg leading-tight text-foreground/90 group-hover:text-primary transition-colors">
                                                        {{ solution.ticket_title }}
                                                    </h3>
                                                    <div class="flex items-center gap-2">
                                                        <Badge variant="secondary" class="font-mono text-[10px] uppercase font-bold tracking-wider opacity-80 bg-muted">
                                                            TKT-{{ 1000 + solution.ticket_id }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="ml-0 sm:ml-11">
                                            <div class="relative">
                                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500/20 rounded-full"></div>
                                                <div class="prose prose-sm max-w-none text-muted-foreground/90 dark:prose-invert prose-p:leading-relaxed pl-4 py-0.5" v-html="solution.solution"></div>
                                            </div>
                                            
                                            <div class="flex flex-wrap items-center gap-1.5 mt-5 pt-4 border-t border-border/40" v-if="solution.tags && solution.tags.length > 0">
                                                <Badge v-for="t in solution.tags" :key="t" variant="outline" class="text-[10px] font-medium px-2 py-0.5 text-muted-foreground bg-muted/30 border-border/50">
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