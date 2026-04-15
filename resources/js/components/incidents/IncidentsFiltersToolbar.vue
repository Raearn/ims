<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { SlidersHorizontal, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';

type CategoryOpt = {
    id: number;
    name: string;
    icon: string;
    parent_id: number | null;
    filterLabel: string;
    iconComponent: object;
};

type PriorityOpt = { name: string; icon: string; color: string; iconComponent: object };

const props = defineProps<{
    statusOptions: string[];
    currentStatus: string;
    categoryOptions: CategoryOpt[];
    currentCategory: string;
    dateFrom: string;
    dateTo: string;
    priorityOptions: PriorityOpt[];
    currentPriority: string;
    priorityCounts: Record<string, number>;
    selectedCount: number;
    showReset: boolean;
}>();

const emit = defineEmits<{
    'update:currentStatus': [value: string];
    'update:currentCategory': [value: string];
    'update:dateFrom': [value: string];
    'update:dateTo': [value: string];
    'update:currentPriority': [value: string];
    reset: [];
    clearSelection: [];
}>();

const mobileSheetOpen = ref(false);

const sheetTriggerLabel = computed(() => {
    const parts: string[] = [];
    if (props.currentCategory !== 'All') {
        parts.push('category');
    }
    if (props.dateFrom || props.dateTo) {
        parts.push('dates');
    }
    if (props.currentPriority !== 'All') {
        parts.push('priority');
    }
    if (parts.length === 0) {
        return 'Category & dates';
    }
    return `Filters (${parts.length} active)`;
});

function closeSheetAfterPick(): void {
    mobileSheetOpen.value = false;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Mobile: sheet trigger -->
        <div class="flex flex-col gap-2 md:hidden">
            <div class="flex min-w-0 items-center justify-between gap-2">
                <Sheet v-model:open="mobileSheetOpen">
                    <SheetTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 w-full shrink-0 gap-1.5 border-border/60 bg-background/60 px-2.5 text-xs font-semibold shadow-sm backdrop-blur-sm"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5" />
                            <span class="max-w-[7rem] truncate">{{ sheetTriggerLabel }}</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="right" class="flex w-[min(100vw-1rem,22rem)] flex-col gap-0 p-0 sm:max-w-md">
                        <SheetHeader class="border-b border-border/60 px-5 py-4 text-left">
                            <SheetTitle class="text-base font-bold">Filters</SheetTitle>
                            <p class="text-xs text-muted-foreground">Category, date range, and priority</p>
                        </SheetHeader>
                        <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-5 py-4">
                            <div class="grid gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Category</span>
                                <Select
                                    :model-value="currentCategory"
                                    @update:model-value="(v) => emit('update:currentCategory', typeof v === 'string' ? v : 'All')"
                                >
                                    <SelectTrigger
                                        class="h-9 w-full rounded-xl border-border/60 bg-background/80 shadow-none backdrop-blur-sm focus:ring-1 focus:ring-ring"
                                    >
                                        <SelectValue placeholder="Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="All">All categories</SelectItem>
                                        <SelectItem v-for="c in categoryOptions" :key="c.id" :value="String(c.id)">
                                            <span class="flex items-center gap-2">
                                                <component :is="c.iconComponent" class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                <span>{{ c.filterLabel }}</span>
                                            </span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Created between</span>
                                <div class="flex flex-col gap-2 rounded-xl border border-border/60 bg-background/60 px-3 py-2.5 shadow-sm backdrop-blur-sm">
                                    <input
                                        :value="dateFrom"
                                        type="date"
                                        :max="dateTo || undefined"
                                        class="w-full bg-transparent text-xs font-medium text-foreground focus:outline-none [color-scheme:light] dark:[color-scheme:dark]"
                                        @input="emit('update:dateFrom', ($event.target as HTMLInputElement).value)"
                                    />
                                    <span class="text-center text-[10px] text-muted-foreground/50">to</span>
                                    <input
                                        :value="dateTo"
                                        type="date"
                                        :min="dateFrom || undefined"
                                        class="w-full bg-transparent text-xs font-medium text-foreground focus:outline-none [color-scheme:light] dark:[color-scheme:dark]"
                                        @input="emit('update:dateTo', ($event.target as HTMLInputElement).value)"
                                    />
                                    <button
                                        v-if="dateFrom || dateTo"
                                        type="button"
                                        class="text-xs font-semibold text-muted-foreground hover:text-foreground"
                                        @click="
                                            emit('update:dateFrom', '');
                                            emit('update:dateTo', '');
                                        "
                                    >
                                        Clear dates
                                    </button>
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Priority</span>
                                <div class="flex flex-col gap-1.5 rounded-xl border border-border/60 bg-background/60 p-2 shadow-sm backdrop-blur-sm">
                                    <button
                                        type="button"
                                        :class="[
                                            'flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-semibold transition-colors',
                                            currentPriority === 'All'
                                                ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
                                                : 'text-muted-foreground hover:bg-background/60',
                                        ]"
                                        @click="
                                            emit('update:currentPriority', 'All');
                                            closeSheetAfterPick();
                                        "
                                    >
                                        All
                                        <span class="text-[10px] font-bold tabular-nums text-muted-foreground">{{
                                            priorityCounts['All'] ?? 0
                                        }}</span>
                                    </button>
                                    <button
                                        v-for="p in priorityOptions"
                                        :key="p.name"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-semibold transition-colors"
                                        :class="
                                            currentPriority !== p.name
                                                ? 'text-muted-foreground hover:bg-background/60'
                                                : 'shadow-sm ring-1 ring-current/20'
                                        "
                                        :style="currentPriority === p.name ? { color: p.color, backgroundColor: p.color + '1a' } : {}"
                                        @click="
                                            emit('update:currentPriority', p.name);
                                            closeSheetAfterPick();
                                        "
                                    >
                                        <span class="inline-flex items-center gap-2">
                                            <component
                                                :is="p.iconComponent"
                                                class="h-3 w-3 shrink-0"
                                                :style="currentPriority !== p.name ? { color: p.color } : {}"
                                            />
                                            {{ p.name }}
                                        </span>
                                        <span
                                            :class="[
                                                'text-[10px] font-bold tabular-nums',
                                                currentPriority === p.name ? 'opacity-70' : 'text-muted-foreground',
                                            ]"
                                            >{{ priorityCounts[p.name] ?? 0 }}</span
                                        >
                                    </button>
                                </div>
                            </div>
                            <Button
                                v-if="showReset"
                                type="button"
                                variant="outline"
                                class="w-full text-xs font-semibold"
                                @click="emit('reset')"
                            >
                                <X class="mr-1.5 h-3.5 w-3.5" />
                                Reset all filters
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
            <div
                class="flex w-full items-center gap-1 overflow-x-auto rounded-xl border border-border/60 bg-background/60 p-1 shadow-sm backdrop-blur-sm no-scrollbar"
            >
                <button
                    type="button"
                    :class="[
                        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200',
                        currentPriority === 'All'
                            ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
                            : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                    ]"
                    @click="emit('update:currentPriority', 'All')"
                >
                    All
                    <span class="text-[10px] font-bold tabular-nums text-muted-foreground">{{ priorityCounts['All'] ?? 0 }}</span>
                </button>
                <button
                    v-for="p in priorityOptions"
                    :key="p.name"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200"
                    :class="
                        currentPriority !== p.name
                            ? 'text-muted-foreground hover:bg-background/60 hover:text-foreground'
                            : 'shadow-sm ring-1 ring-current/20'
                    "
                    :style="currentPriority === p.name ? { color: p.color, backgroundColor: p.color + '1a' } : {}"
                    @click="emit('update:currentPriority', p.name)"
                >
                    <component
                        :is="p.iconComponent"
                        class="h-3 w-3 shrink-0 transition-colors"
                        :style="currentPriority !== p.name ? { color: p.color } : {}"
                    />
                    {{ p.name }}
                    <span
                        :class="[
                            'text-[10px] font-bold tabular-nums transition-colors',
                            currentPriority === p.name ? 'opacity-70' : 'text-muted-foreground',
                        ]"
                        >{{ priorityCounts[p.name] ?? 0 }}</span
                    >
                </button>
            </div>
        </div>

        <!-- Desktop toolbar -->
        <div class="hidden flex-wrap items-center gap-2 md:flex">
            <div class="hidden w-[min(11rem,42vw)] shrink-0 md:block">
                <Select
                    :model-value="currentCategory"
                    @update:model-value="(v) => emit('update:currentCategory', typeof v === 'string' ? v : 'All')"
                >
                    <SelectTrigger
                        class="h-9 rounded-xl border-border/60 bg-background/60 shadow-none backdrop-blur-sm focus:ring-1 focus:ring-ring hover:border-primary/25"
                    >
                        <SelectValue placeholder="Category" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="All">All categories</SelectItem>
                        <SelectItem v-for="c in categoryOptions" :key="c.id" :value="String(c.id)">
                            <span class="flex items-center gap-2">
                                <component :is="c.iconComponent" class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                <span>{{ c.filterLabel }}</span>
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="hidden flex-1 lg:block" />

            <div
                class="hidden shrink-0 items-center gap-1.5 rounded-xl border border-border/60 bg-background/60 px-2.5 py-1.5 shadow-sm backdrop-blur-sm md:flex"
            >
                <SlidersHorizontal class="h-3 w-3 shrink-0 text-muted-foreground" aria-hidden="true" />
                <input
                    :value="dateFrom"
                    type="date"
                    :max="dateTo || undefined"
                    class="w-[118px] cursor-pointer bg-transparent text-xs font-medium text-foreground [color-scheme:light] dark:[color-scheme:dark]"
                    title="From date"
                    aria-label="Filter from date"
                    @input="emit('update:dateFrom', ($event.target as HTMLInputElement).value)"
                />
                <span class="select-none text-xs text-muted-foreground/50" aria-hidden="true">–</span>
                <input
                    :value="dateTo"
                    type="date"
                    :min="dateFrom || undefined"
                    class="w-[118px] cursor-pointer bg-transparent text-xs font-medium text-foreground [color-scheme:light] dark:[color-scheme:dark]"
                    title="To date"
                    aria-label="Filter to date"
                    @input="emit('update:dateTo', ($event.target as HTMLInputElement).value)"
                />
                <button
                    v-if="dateFrom || dateTo"
                    type="button"
                    aria-label="Clear date range"
                    class="ml-0.5 text-muted-foreground transition-colors hover:text-foreground"
                    @click="
                        emit('update:dateFrom', '');
                        emit('update:dateTo', '');
                    "
                >
                    <X class="h-3 w-3" />
                </button>
            </div>

            <div
                class="hidden w-full items-center gap-1 overflow-x-auto rounded-xl border border-border/60 bg-background/60 p-1 shadow-sm backdrop-blur-sm no-scrollbar md:flex md:w-auto lg:max-w-none"
            >
                <button
                    type="button"
                    :class="[
                        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200',
                        currentPriority === 'All'
                            ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
                            : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                    ]"
                    @click="emit('update:currentPriority', 'All')"
                >
                    All
                    <span class="text-[10px] font-bold tabular-nums text-muted-foreground">{{ priorityCounts['All'] ?? 0 }}</span>
                </button>
                <button
                    v-for="p in priorityOptions"
                    :key="p.name"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-all duration-200"
                    :class="
                        currentPriority !== p.name
                            ? 'text-muted-foreground hover:bg-background/60 hover:text-foreground'
                            : 'shadow-sm ring-1 ring-current/20'
                    "
                    :style="currentPriority === p.name ? { color: p.color, backgroundColor: p.color + '1a' } : {}"
                    @click="emit('update:currentPriority', p.name)"
                >
                    <component
                        :is="p.iconComponent"
                        class="h-3 w-3 shrink-0 transition-colors"
                        :style="currentPriority !== p.name ? { color: p.color } : {}"
                    />
                    {{ p.name }}
                    <span
                        :class="[
                            'text-[10px] font-bold tabular-nums transition-colors',
                            currentPriority === p.name ? 'opacity-70' : 'text-muted-foreground',
                        ]"
                        >{{ priorityCounts[p.name] ?? 0 }}</span
                    >
                </button>
            </div>

            <div v-if="selectedCount > 0" class="flex shrink-0 items-center gap-1.5">
                <span class="inline-flex items-center rounded-lg border border-primary/20 bg-primary/10 px-2.5 py-1.5 text-xs font-bold text-primary">
                    {{ selectedCount }} selected
                </span>
                <button type="button" class="text-xs text-muted-foreground transition-colors hover:text-foreground" @click="emit('clearSelection')">
                    Clear
                </button>
            </div>

            <Transition
                enter-active-class="transition-all duration-200"
                leave-active-class="transition-all duration-150"
                enter-from-class="translate-x-2 opacity-0"
                leave-to-class="translate-x-2 opacity-0"
            >
                <button
                    v-if="showReset"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-border/60 bg-background/60 px-2.5 py-1.5 text-[11px] font-semibold text-muted-foreground shadow-sm backdrop-blur-sm transition-colors hover:border-primary/30 hover:bg-background/80 hover:text-foreground"
                    @click="emit('reset')"
                >
                    <X class="h-3 w-3 shrink-0" aria-hidden="true" />
                    Reset
                </button>
            </Transition>
        </div>

    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
