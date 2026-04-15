<script setup lang="ts">
import { resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import { CheckCircle2, ChevronRight, HelpCircle } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

export type IncidentCategoryOption = {
    id: number;
    name: string;
    icon: string;
    parent_id: number | null;
};

const props = defineProps<{
    categories: IncidentCategoryOption[];
    modelValue: number | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const expandedRootId = ref<number | null>(null);

const roots = computed((): IncidentCategoryOption[] => props.categories.filter((c) => c.parent_id == null));

const expandedRoot = computed((): IncidentCategoryOption | null => {
    const id = expandedRootId.value;
    if (id == null) {
        return null;
    }
    return props.categories.find((c) => c.id === id && c.parent_id == null) ?? null;
});

const expandedChildren = computed((): IncidentCategoryOption[] => {
    const id = expandedRootId.value;
    if (id == null) {
        return [];
    }
    return props.categories.filter((c) => c.parent_id === id);
});

function childrenOf(parentId: number): IncidentCategoryOption[] {
    return props.categories.filter((c) => c.parent_id === parentId);
}

function iconFor(name: string) {
    return resolveLucideIcon(name, HelpCircle);
}

function select(id: number): void {
    emit('update:modelValue', id);
}

function categoryBelongsToRoot(rootId: number, categoryId: number | null): boolean {
    if (categoryId == null) {
        return false;
    }
    const row = props.categories.find((c) => c.id === categoryId);
    if (!row) {
        return false;
    }
    if (row.id === rootId) {
        return true;
    }
    return row.parent_id === rootId;
}

function syncExpandedRootToModel(id: number | null): void {
    if (id == null) {
        expandedRootId.value = null;
        return;
    }
    const row = props.categories.find((c) => c.id === id);
    if (row == null) {
        expandedRootId.value = null;
        return;
    }
    if (row.parent_id != null) {
        expandedRootId.value = row.parent_id;
        return;
    }
    if (childrenOf(row.id).length > 0) {
        expandedRootId.value = row.id;
        return;
    }
    expandedRootId.value = null;
}

function onRootClick(root: IncidentCategoryOption): void {
    const kids = childrenOf(root.id);
    if (kids.length === 0) {
        select(root.id);
        expandedRootId.value = null;
        void nextTick(() => {
            syncExpandedRootToModel(props.modelValue);
        });
        return;
    }
    if (expandedRootId.value === root.id) {
        expandedRootId.value = null;
        return;
    }
    const keepSelection = categoryBelongsToRoot(root.id, props.modelValue);
    expandedRootId.value = root.id;
    if (!keepSelection) {
        // Replace any selection from another family (e.g. leaf Hardware) with this root.
        // Inertia `useForm` may not persist `null`, so selecting the parent id is reliable.
        select(root.id);
    }
}

function selectParentFromPanel(): void {
    const r = expandedRoot.value;
    if (r) {
        select(r.id);
    }
}

/** Root id for an open "choose a type" panel, or null. */
function expandedPanelRootId(): number | null {
    const e = expandedRootId.value;
    if (e == null) {
        return null;
    }
    return childrenOf(e).length > 0 ? e : null;
}

/**
 * Primary (filled) selection on the top-level grid. When a parent panel is open, only that root may
 * show primary state — avoids a stale modelValue (e.g. Security) staying highlighted while Network is expanded.
 */
function isRootGridPrimarySelected(root: IncidentCategoryOption): boolean {
    const id = props.modelValue;
    const panelRoot = expandedPanelRootId();
    if (panelRoot != null) {
        if (root.id !== panelRoot) {
            return false;
        }
        return categoryBelongsToRoot(panelRoot, id);
    }
    const kids = childrenOf(root.id);
    if (kids.length === 0) {
        return id === root.id;
    }
    return categoryBelongsToRoot(root.id, id);
}

watch(
    () => props.modelValue,
    () => {
        syncExpandedRootToModel(props.modelValue);
    },
    { immediate: true },
);

watch(
    () => props.categories,
    () => {
        syncExpandedRootToModel(props.modelValue);
    },
);
</script>

<template>
    <div class="grid gap-3">
        <div class="grid grid-cols-3 gap-2">
            <button
                v-for="root in roots"
                :key="root.id"
                type="button"
                @click="onRootClick(root)"
                :class="[
                    'group relative flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 p-3 transition-all',
                    isRootGridPrimarySelected(root)
                        ? 'border-primary bg-primary/5 text-primary shadow-sm'
                        : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50',
                    expandedRootId === root.id && childrenOf(root.id).length > 0 ? 'border-primary/40 ring-2 ring-primary/30' : '',
                ]"
            >
                <component :is="iconFor(root.icon)" class="h-4 w-4" />
                <span class="w-full truncate text-center text-[10px] font-bold uppercase">{{ root.name }}</span>
                <ChevronRight
                    v-if="childrenOf(root.id).length > 0"
                    class="absolute bottom-1 right-1 h-3 w-3 text-muted-foreground/70 opacity-80 group-hover:text-primary"
                    :class="expandedRootId === root.id ? 'rotate-90 text-primary' : ''"
                />
                <div v-if="isRootGridPrimarySelected(root)" class="absolute -right-1.5 -top-1.5">
                    <CheckCircle2 class="h-4 w-4 fill-primary text-white" />
                </div>
            </button>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 -translate-y-1 scale-[0.98]"
        >
            <div
                v-if="expandedRoot && expandedChildren.length > 0"
                class="rounded-xl border-2 border-primary/30 bg-muted/40 p-3 shadow-lg shadow-black/5 ring-1 ring-border/60 dark:shadow-black/20"
            >
                <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ expandedRoot.name }} — choose a type</p>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        @click="selectParentFromPanel"
                        :class="[
                            'relative flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 p-3 transition-all',
                            modelValue === expandedRoot.id
                                ? 'border-primary bg-primary/5 text-primary shadow-sm'
                                : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-background/80',
                        ]"
                    >
                        <component :is="iconFor(expandedRoot.icon)" class="h-4 w-4" />
                        <span class="w-full truncate text-center text-[10px] font-bold uppercase">{{ expandedRoot.name }}</span>
                        <div v-if="modelValue === expandedRoot.id" class="absolute -right-1.5 -top-1.5">
                            <CheckCircle2 class="h-4 w-4 fill-primary text-white" />
                        </div>
                    </button>
                    <button
                        v-for="ch in expandedChildren"
                        :key="ch.id"
                        type="button"
                        @click="select(ch.id)"
                        :class="[
                            'relative flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 p-3 transition-all',
                            modelValue === ch.id
                                ? 'border-primary bg-primary/5 text-primary shadow-sm'
                                : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-background/80',
                        ]"
                    >
                        <component :is="iconFor(ch.icon)" class="h-4 w-4" />
                        <span class="w-full truncate text-center text-[10px] font-bold uppercase">{{ ch.name }}</span>
                        <div v-if="modelValue === ch.id" class="absolute -right-1.5 -top-1.5">
                            <CheckCircle2 class="h-4 w-4 fill-primary text-white" />
                        </div>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
