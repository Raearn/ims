<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- Parent passes Inertia `useForm()` proxy; intentional shared mutation */
import IncidentCategoryPicker from '@/components/IncidentCategoryPicker.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { compressImage } from '@/lib/utils';
import { ensureLucideIconsLoaded, lucideAllIconMap, resolveLucideIcon } from '@/composables/useLucideIconRegistry';
import type { InertiaForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    Info,
    Plus,
    Save,
    Search,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type {
    IncidentCategoryOption,
    IncidentPriorityOption,
    IncidentStatusOption,
    IncidentTicketRow,
} from './incidentFormTypes';

export type IncidentMainForm = {
    title: string;
    description: string;
    priority: string;
    ticket_category_id: number | null;
    status: string;
    handler_ids: number[];
    tags: string[];
    solution: string;
    attachment: File | null;
};

const props = defineProps<{
    open: boolean;
    editingTicket: IncidentTicketRow | null;
    form: InertiaForm<IncidentMainForm>;
    categories: IncidentCategoryOption[];
    priorities: IncidentPriorityOption[];
    statuses: IncidentStatusOption[];
    users: { id: number; name: string }[];
    allTags: string[];
    attachmentPreview: string | null;
    attachmentCompression: { before: number; after: number } | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [];
    'update:attachmentPreview': [value: string | null];
    'update:attachmentCompression': [value: { before: number; after: number } | null];
}>();

const handlerSearch = ref('');
const tagSearchInput = ref('');
const isDraggingOver = ref(false);

void ensureLucideIconsLoaded();

function isStatusNoHandlers(statusName: string): boolean {
    const row = props.statuses.find((s) => s.name === statusName);
    return row?.handler_requirement === 'none';
}

function isStatusHandlerRequired(statusName: string): boolean {
    const row = props.statuses.find((s) => s.name === statusName);
    return row?.handler_requirement === 'required';
}

const handlerRequired = computed(() => isStatusHandlerRequired(props.form.status));

const selectedCategoryLabel = computed((): string => {
    const id = props.form.ticket_category_id;
    if (id == null) {
        return '';
    }
    return props.categories.find((c) => c.id === id)?.name ?? '';
});

function onTicketCategoryIdUpdate(value: number | null): void {
    props.form.ticket_category_id = value;
}

const filteredTags = computed(() => {
    if (!tagSearchInput.value.trim()) {
        return props.allTags;
    }
    const q = tagSearchInput.value.toLowerCase();
    return props.allTags.filter((t) => t.toLowerCase().includes(q));
});

const filteredUsers = computed(() => {
    if (!handlerSearch.value.trim()) {
        return props.users;
    }
    const q = handlerSearch.value.toLowerCase();
    return props.users.filter((u) => u.name.toLowerCase().includes(q));
});

const priorityOptions = computed(() =>
    props.priorities.map((p) => ({
        ...p,
        iconComponent: resolveLucideIcon(p.icon, Circle),
    })),
);

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

const setAttachmentFile = async (file: File | null) => {
    if (!file) {
        props.form.attachment = null;
        emit('update:attachmentPreview', null);
        emit('update:attachmentCompression', null);
        return;
    }
    const compressed = await compressImage(file);
    props.form.attachment = compressed;
    emit('update:attachmentPreview', URL.createObjectURL(compressed));
    emit(
        'update:attachmentCompression',
        compressed !== file ? { before: file.size, after: compressed.size } : null,
    );
};

const onAttachmentChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    void setAttachmentFile(file);
};

const onAttachmentDrop = (e: DragEvent) => {
    isDraggingOver.value = false;
    const file = e.dataTransfer?.files?.[0] ?? null;
    if (file && file.type.startsWith('image/')) {
        void setAttachmentFile(file);
    }
};

const removeAttachment = () => {
    props.form.attachment = null;
    emit('update:attachmentPreview', null);
    emit('update:attachmentCompression', null);
};

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

watch(
    () => props.open,
    (val) => {
        if (!val) {
            handlerSearch.value = '';
            tagSearchInput.value = '';
            isDraggingOver.value = false;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[92dvh] w-[calc(100vw-1.5rem)] flex-col overflow-hidden border-none p-0 shadow-2xl sm:max-w-7xl">
            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
                <div class="border-b border-primary/10 bg-primary/5 px-5 pb-4 pt-5">
                    <DialogHeader>
                        <div class="mb-2">
                            <Badge
                                variant="outline"
                                class="border-primary/20 bg-primary/10 px-2 py-0 text-[10px] font-bold uppercase tracking-wider text-primary"
                            >
                                {{ editingTicket ? 'Edit Incident' : 'Incident Report' }}
                            </Badge>
                        </div>
                        <DialogTitle class="text-lg font-bold tracking-tight">
                            {{ editingTicket ? 'Update incident' : 'New incident' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground/80">
                            {{
                                editingTicket
                                    ? 'Update details, tags, category, priority, status, and handlers in one place.'
                                    : 'Describe the issue, add tags, and set category, priority, and status before launching.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div class="modal-body min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                        <div class="grid gap-5">
                            <div class="grid gap-2">
                                <Label for="incident-title" class="text-xs font-bold uppercase tracking-wider text-muted-foreground"
                                    >Incident Title</Label
                                >
                                <Input
                                    id="incident-title"
                                    v-model="form.title"
                                    placeholder="e.g., Network outage in Office A"
                                    required
                                    class="py-5 text-sm"
                                />
                                <span v-if="form.errors.title" class="text-xs font-medium text-destructive">{{ form.errors.title }}</span>
                            </div>
                            <div class="grid gap-2">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    Description <span class="font-normal normal-case text-muted-foreground/60">(optional)</span>
                                </Label>
                                <RichTextEditor v-model="form.description" placeholder="Provide more context about the issue…" />
                                <span v-if="form.errors.description" class="text-xs font-medium text-destructive">{{
                                    form.errors.description
                                }}</span>
                            </div>

                            <div class="grid gap-2">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    Tags <span class="ml-1 text-destructive">*</span>
                                </Label>

                                <div v-if="form.tags.length > 0" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tag in form.tags"
                                        :key="tag"
                                        class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 py-1 pl-2 pr-1 text-[11px] font-semibold text-primary"
                                    >
                                        {{ tag }}
                                        <button
                                            type="button"
                                            class="ml-0.5 flex h-4 w-4 items-center justify-center rounded-full transition-colors hover:bg-primary/20"
                                            @click="form.tags = form.tags.filter((t) => t !== tag)"
                                        >
                                            <X class="h-2.5 w-2.5" />
                                        </button>
                                    </span>
                                </div>

                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        v-model="tagSearchInput"
                                        type="text"
                                        placeholder="Search or add tags… (press enter to create)"
                                        class="w-full rounded-lg border border-input bg-transparent py-2 pl-8 pr-3 text-xs shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        @keydown.enter.prevent="
                                            () => {
                                                if (tagSearchInput.trim() !== '') {
                                                    const tag =
                                                        filteredTags.length > 0 &&
                                                        allTags.some((t) => t.toLowerCase() === tagSearchInput.trim().toLowerCase())
                                                            ? filteredTags[0]
                                                            : tagSearchInput.trim();
                                                    if (!form.tags.includes(tag)) {
                                                        form.tags.push(tag);
                                                    }
                                                    tagSearchInput = '';
                                                }
                                            }
                                        "
                                    />
                                </div>

                                <div
                                    class="handler-list max-h-36 divide-y divide-border/40 overflow-y-auto rounded-lg border border-border/50 bg-muted/10"
                                >
                                    <button
                                        v-for="tag in filteredTags"
                                        :key="tag"
                                        type="button"
                                        :class="[
                                            'flex w-full items-center gap-2.5 px-3 py-2 text-left transition-colors',
                                            form.tags.includes(tag) ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/50',
                                        ]"
                                        @click="
                                            () => {
                                                form.tags = form.tags.includes(tag)
                                                    ? form.tags.filter((t) => t !== tag)
                                                    : [...form.tags, tag];
                                            }
                                        "
                                    >
                                        <span class="truncate text-xs font-medium">{{ tag }}</span>
                                        <CheckCircle2 v-if="form.tags.includes(tag)" class="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />
                                    </button>

                                    <button
                                        v-if="
                                            tagSearchInput.trim() !== '' &&
                                            !allTags.some((t) => t.toLowerCase() === tagSearchInput.trim().toLowerCase())
                                        "
                                        type="button"
                                        class="flex w-full items-center gap-2.5 bg-primary/5 px-3 py-2 text-left text-primary transition-colors hover:bg-primary/10"
                                        @click="
                                            () => {
                                                if (!form.tags.includes(tagSearchInput.trim())) {
                                                    form.tags.push(tagSearchInput.trim());
                                                }
                                                tagSearchInput = '';
                                            }
                                        "
                                    >
                                        <span class="truncate text-xs font-medium">Create "{{ tagSearchInput.trim() }}"</span>
                                        <Plus class="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />
                                    </button>

                                    <div
                                        v-if="filteredTags.length === 0 && tagSearchInput.trim() === ''"
                                        class="px-4 py-6 text-center text-xs italic text-muted-foreground/60"
                                    >
                                        Type to search or create a new tag.
                                    </div>
                                </div>

                                <span v-if="form.errors.tags" class="text-xs font-medium text-destructive">{{ form.errors.tags }}</span>
                                <span v-else-if="form.tags.length === 0" class="text-xs italic text-muted-foreground/60"
                                    >At least one tag is required.</span
                                >
                            </div>

                            <div class="grid gap-2">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    Attachment <span class="font-normal normal-case text-muted-foreground/60">(optional · image · max 4 MB)</span>
                                </Label>
                                <div v-if="attachmentPreview" class="relative overflow-hidden rounded-lg border border-border/50 bg-muted/20">
                                    <img :src="attachmentPreview" alt="preview" class="max-h-40 w-full object-contain" />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full border border-border/60 bg-background/90 text-muted-foreground shadow-sm transition-colors hover:text-destructive"
                                        @click="removeAttachment"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                    <div
                                        v-if="attachmentCompression"
                                        class="absolute bottom-2 left-2 flex items-center gap-1 rounded-full border border-emerald-500/30 bg-background/90 px-2 py-0.5 text-[10px] shadow-sm backdrop-blur-sm"
                                    >
                                        <span class="text-muted-foreground line-through">{{ (attachmentCompression.before / 1024).toFixed(0) }}KB</span>
                                        <span class="text-muted-foreground/50">→</span>
                                        <span class="font-semibold text-emerald-500">{{ (attachmentCompression.after / 1024).toFixed(0) }}KB</span>
                                        <span class="text-muted-foreground/70">·</span>
                                        <span class="font-semibold text-emerald-500"
                                            >-{{ Math.round((1 - attachmentCompression.after / attachmentCompression.before) * 100) }}%</span
                                        >
                                    </div>
                                </div>
                                <label
                                    v-else
                                    :class="[
                                        'group flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-5 transition-all',
                                        isDraggingOver
                                            ? 'scale-[1.01] border-primary bg-primary/8'
                                            : 'border-muted-foreground/20 bg-muted/10 hover:border-primary/40 hover:bg-primary/5',
                                    ]"
                                    @dragover.prevent="isDraggingOver = true"
                                    @dragleave.prevent="isDraggingOver = false"
                                    @drop.prevent="onAttachmentDrop"
                                >
                                    <div
                                        :class="[
                                            'flex h-8 w-8 items-center justify-center rounded-full transition-colors',
                                            isDraggingOver ? 'bg-primary/15' : 'bg-muted/60 group-hover:bg-primary/10',
                                        ]"
                                    >
                                        <Upload
                                            :class="[
                                                'h-4 w-4 transition-colors',
                                                isDraggingOver ? 'text-primary' : 'text-muted-foreground group-hover:text-primary',
                                            ]"
                                        />
                                    </div>
                                    <div class="text-center">
                                        <p
                                            :class="[
                                                'text-xs font-semibold transition-colors',
                                                isDraggingOver ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground',
                                            ]"
                                        >
                                            {{ isDraggingOver ? 'Drop to attach' : 'Click or drag & drop an image' }}
                                        </p>
                                        <p class="mt-0.5 text-[10px] text-muted-foreground/60">PNG, JPG, GIF, WEBP · max 4 MB</p>
                                    </div>
                                    <input type="file" accept="image/*" class="sr-only" @change="onAttachmentChange" />
                                </label>
                                <span v-if="form.errors.attachment" class="text-xs font-medium text-destructive">{{ form.errors.attachment }}</span>
                            </div>
                        </div>

                        <div class="grid gap-5">
                            <div class="grid gap-3">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Category</Label>
                                <IncidentCategoryPicker
                                    :key="`incident-category-${form.ticket_category_id ?? 'none'}`"
                                    :model-value="form.ticket_category_id"
                                    :categories="categories"
                                    @update:modelValue="onTicketCategoryIdUpdate"
                                />
                                <span v-if="form.errors.ticket_category_id" class="text-xs font-medium text-destructive">{{
                                    form.errors.ticket_category_id
                                }}</span>
                            </div>

                            <div class="grid gap-3">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Priority Level</Label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="prio in priorityOptions"
                                        :key="prio.name"
                                        type="button"
                                        :class="[
                                            'group relative flex items-center gap-2.5 rounded-xl border-2 p-3 transition-all',
                                            form.priority === prio.name
                                                ? 'border-primary bg-primary/5 shadow-sm'
                                                : 'border-muted hover:border-primary/30 hover:bg-muted/50',
                                        ]"
                                        @click="form.priority = prio.name"
                                    >
                                        <div :class="['rounded-lg p-1.5', form.priority === prio.name ? 'bg-background shadow-sm' : 'bg-muted/60']">
                                            <component :is="prio.iconComponent" class="h-3.5 w-3.5" :style="{ color: prio.color }" />
                                        </div>
                                        <div class="flex min-w-0 flex-col items-start">
                                            <span
                                                :class="[
                                                    'text-xs font-bold',
                                                    form.priority === prio.name ? 'text-primary' : 'text-muted-foreground',
                                                ]"
                                                >{{ prio.name }}</span
                                            >
                                            <span class="mt-0.5 truncate text-[10px] leading-none text-muted-foreground/60">
                                                {{
                                                    prio.name === 'Critical'
                                                        ? 'Immediate'
                                                        : prio.name === 'High'
                                                          ? 'Fast response'
                                                          : prio.name === 'Medium'
                                                            ? 'Standard'
                                                            : 'Non-urgent'
                                                }}
                                            </span>
                                        </div>
                                        <CheckCircle2
                                            v-if="form.priority === prio.name"
                                            class="ml-auto h-3.5 w-3.5 shrink-0 fill-primary text-white"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Status</Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="s in statuses.map((x) => x.name)"
                                        :key="s"
                                        type="button"
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-1.5 text-[11px] font-bold transition-all',
                                            form.status === s
                                                ? 'border-current shadow-sm'
                                                : 'border-muted text-muted-foreground hover:border-primary/30 hover:bg-muted/50',
                                        ]"
                                        :style="form.status === s ? getStatusStyle(s) : {}"
                                        @click="form.status = s"
                                    >
                                        <component :is="getStatusIcon(s)" class="h-3 w-3" />
                                        {{ s }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="!isStatusNoHandlers(form.status)" class="grid gap-2">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    Handlers
                                    <span v-if="handlerRequired" class="ml-1 text-destructive">*</span>
                                    <span v-else class="font-normal normal-case text-muted-foreground/60">(optional)</span>
                                </Label>
                                <div v-if="form.handler_ids.length > 0" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="id in form.handler_ids"
                                        :key="id"
                                        class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 py-0.5 pl-1.5 pr-1 text-[11px] font-semibold text-primary"
                                    >
                                        <span
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-primary text-[9px] font-bold text-primary-foreground"
                                        >
                                            {{ getInitials(users.find((u) => u.id === id)?.name ?? '') }}
                                        </span>
                                        {{ users.find((u) => u.id === id)?.name }}
                                        <button
                                            type="button"
                                            class="ml-0.5 rounded-full p-0.5 transition-colors hover:bg-primary/20"
                                            @click="form.handler_ids = form.handler_ids.filter((i) => i !== id)"
                                        >
                                            <X class="h-2.5 w-2.5" />
                                        </button>
                                    </span>
                                </div>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        v-model="handlerSearch"
                                        type="text"
                                        placeholder="Search handlers…"
                                        class="w-full rounded-lg border border-input bg-transparent py-2 pl-8 pr-3 text-xs shadow-sm placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    />
                                </div>
                                <div
                                    class="handler-list max-h-36 divide-y divide-border/40 overflow-y-auto rounded-lg border border-border/50 bg-muted/10"
                                >
                                    <button
                                        v-for="user in filteredUsers"
                                        :key="user.id"
                                        type="button"
                                        :class="[
                                            'flex w-full items-center gap-2.5 px-3 py-2 text-left transition-colors',
                                            form.handler_ids.includes(user.id)
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-foreground hover:bg-muted/50',
                                        ]"
                                        @click="
                                            form.handler_ids = form.handler_ids.includes(user.id)
                                                ? form.handler_ids.filter((i) => i !== user.id)
                                                : [...form.handler_ids, user.id]
                                        "
                                    >
                                        <div
                                            :class="[
                                                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold',
                                                form.handler_ids.includes(user.id)
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border/50 bg-muted',
                                            ]"
                                        >
                                            {{ getInitials(user.name) }}
                                        </div>
                                        <span class="truncate text-xs font-medium">{{ user.name }}</span>
                                        <CheckCircle2
                                            v-if="form.handler_ids.includes(user.id)"
                                            class="ml-auto h-3.5 w-3.5 shrink-0 text-primary"
                                        />
                                    </button>
                                    <div v-if="filteredUsers.length === 0" class="px-3 py-4 text-center text-xs italic text-muted-foreground/60">
                                        No handlers found.
                                    </div>
                                </div>
                                <span v-if="form.errors.handler_ids" class="text-xs font-medium text-destructive">{{ form.errors.handler_ids }}</span>
                            </div>

                            <div v-if="form.status === 'Resolved'" class="grid gap-2">
                                <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    Solution <span class="ml-1 text-destructive">*</span>
                                </Label>
                                <RichTextEditor v-model="form.solution" placeholder="Describe how the issue was resolved…" />
                                <span v-if="form.errors.solution" class="text-xs font-medium text-destructive">{{ form.errors.solution }}</span>
                            </div>

                            <div class="flex items-start gap-3 rounded-xl border border-muted bg-muted/30 p-3.5">
                                <Info class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    <template v-if="editingTicket">
                                        Incident will be updated to <span class="font-bold text-foreground">{{ selectedCategoryLabel }}</span> /
                                        <span class="font-bold text-foreground">{{ form.priority }}</span> priority /
                                        <span class="font-bold text-foreground">{{ form.status }}</span
                                        >.
                                    </template>
                                    <template v-else>
                                        Your incident will be assigned to the <span class="font-bold text-foreground">{{ selectedCategoryLabel }}</span>
                                        team with <span class="font-bold text-foreground">{{ form.priority }}</span> priority.
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-border/50 bg-muted/20 px-5 py-4">
                    <div class="flex w-full items-center justify-end gap-2">
                        <Button type="button" variant="outline" class="text-xs font-bold" @click="emit('update:open', false)">Cancel</Button>
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                !form.title ||
                                form.ticket_category_id == null ||
                                (handlerRequired && form.handler_ids.length === 0)
                            "
                            class="gap-1.5 text-xs font-bold shadow-md shadow-primary/20"
                        >
                            <span v-if="!form.processing" class="flex items-center gap-1.5">
                                <template v-if="editingTicket">Save Changes <Save class="h-3.5 w-3.5" /></template>
                                <template v-else>Launch Incident <Plus class="h-3.5 w-3.5" /></template>
                            </span>
                            <span v-else class="flex items-center gap-1.5">
                                Processing <span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                            </span>
                        </Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
