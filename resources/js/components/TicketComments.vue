<script setup lang="ts">
import CommentEditor from '@/components/CommentEditor.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { laravelFetch } from '@/lib/laravelFetch';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowBigDown,
    ArrowBigUp,
    Bell,
    BellOff,
    CornerDownRight,
    Crown,
    Headset,
    Mail,
    MessageCirclePlus,
    MessageSquare,
    Pin,
    RotateCcw,
    SendHorizonal,
    ShieldCheck,
    SmilePlus,
    Trash2,
    UserRound,
    Users,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{ ticketId: number; reporterId?: number }>();

interface Reaction {
    emoji: string;
    count: number;
    reacted: boolean;
    users: string[];
}
interface Comment {
    id: number;
    userId: number;
    userName: string;
    userInitials: string;
    userRole: string;
    userEmail: string;
    body: string;
    createdAt: string;
    reactions: Reaction[];
    parentId: number | null;
    isPinned: boolean;
    upvotes: number;
    downvotes: number;
    userVote: 'up' | 'down' | null;
    upvoters: string[];
    downvoters: string[];
}

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);

const comments = ref<Comment[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const isTogglingSubscription = ref(false);
const subscribed = ref(false);
const newBody = ref('');
const openEmojiPickerFor = ref<number | null>(null);
const pendingDeleteId = ref<number | null>(null);
const interactionsModalComment = ref<Comment | null>(null);
const newCommentId = ref<number | null>(null);
const activeUserCard = ref<number | null>(null);
const lastCommentRef = ref<HTMLElement | null>(null);
const editorFocused = ref(false);

const replyingToId = ref<number | null>(null);
const replyBody = ref('');
const isReplying = ref(false);
const sortOrder = ref<'oldest' | 'newest' | 'relevant'>('relevant');
const frozenRelevantOrder = ref<number[]>([]);
const pendingRefresh = ref(false);

const ALLOWED_EMOJIS = ['👍', '👎', '❤️', '😂', '😮', '😢', '🎉', '🔥', '✅', '👀', '💯'];

const AVATAR_COLORS = [
    'bg-violet-500/15 text-violet-500 border-violet-500/20',
    'bg-sky-500/15 text-sky-500 border-sky-500/20',
    'bg-emerald-500/15 text-emerald-500 border-emerald-500/20',
    'bg-orange-500/15 text-orange-500 border-orange-500/20',
    'bg-rose-500/15 text-rose-500 border-rose-500/20',
    'bg-amber-500/15 text-amber-500 border-amber-500/20',
    'bg-teal-500/15 text-teal-500 border-teal-500/20',
    'bg-indigo-500/15 text-indigo-500 border-indigo-500/20',
];

function getAvatarColor(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function getRoleBadgeClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-rose-500/15 text-rose-500 border-rose-500/30';
        case 'supervisor':
            return 'bg-amber-500/15 text-amber-500 border-amber-500/30';
        case 'technical':
            return 'bg-blue-500/15 text-blue-500 border-blue-500/30';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function getRoleIcon(role: string) {
    switch (role) {
        case 'admin':
            return Crown;
        case 'supervisor':
            return ShieldCheck;
        case 'technical':
            return Headset;
        default:
            return UserRound;
    }
}

function getRoleLabel(role: string): string {
    switch (role) {
        case 'admin':
            return 'Admin';
        case 'supervisor':
            return 'Supervisor';
        case 'technical':
            return 'Technical';
        default:
            return 'User';
    }
}

function toggleUserCard(id: number) {
    activeUserCard.value = activeUserCard.value === id ? null : id;
    openEmojiPickerFor.value = null;
}

function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
    return laravelFetch(url, options);
}

async function loadComments() {
    isLoading.value = true;
    try {
        const res = await apiFetch(route('tickets.comments.index', props.ticketId));
        if (res.ok) {
            const data = await res.json();
            subscribed.value = data.subscribed;
            comments.value = data.comments;
            if (sortOrder.value === 'relevant') snapshotRelevantOrder();
        }
    } finally {
        isLoading.value = false;
    }
}

async function toggleSubscription() {
    if (isTogglingSubscription.value) return;
    isTogglingSubscription.value = true;
    try {
        const res = await apiFetch(route('tickets.subscribe.toggle', props.ticketId), { method: 'POST' });
        if (res.ok) {
            const data = await res.json();
            subscribed.value = data.subscribed;
        }
    } finally {
        isTogglingSubscription.value = false;
    }
}

async function submitComment() {
    const body = newBody.value.trim();
    if (!body || body === '<p></p>' || isSubmitting.value) return;

    isSubmitting.value = true;
    try {
        const res = await apiFetch(route('tickets.comments.store', props.ticketId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ body }),
        });

        if (res.ok) {
            const comment = await res.json();
            comments.value.push(comment);
            newBody.value = '';
            editorFocused.value = false;
            newCommentId.value = comment.id;
            // Auto-subscribe since the backend does it too — reflect it in UI
            subscribed.value = true;
            await nextTick();
            lastCommentRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => {
                newCommentId.value = null;
            }, 3000);
        }
    } finally {
        isSubmitting.value = false;
    }
}

function confirmDelete(id: number) {
    pendingDeleteId.value = id;
}
function cancelDelete() {
    pendingDeleteId.value = null;
}

async function deleteComment(id: number) {
    pendingDeleteId.value = null;
    const res = await apiFetch(route('ticket-comments.destroy', id), { method: 'DELETE' });
    if (res.ok) {
        comments.value = comments.value.filter((c) => c.id !== id);
    }
}

async function toggleReaction(commentId: number, emoji: string) {
    openEmojiPickerFor.value = null;
    const res = await apiFetch(route('ticket-comments.reactions.toggle', commentId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ emoji }),
    });

    if (res.ok) {
        const data = await res.json();
        const comment = comments.value.find((c) => c.id === commentId);
        if (!comment) return;
        const existing = comment.reactions.find((r) => r.emoji === emoji);
        if (existing) {
            if (data.count === 0) {
                comment.reactions = comment.reactions.filter((r) => r.emoji !== emoji);
            } else {
                existing.count = data.count;
                existing.reacted = data.reacted;
                existing.users = data.users ?? [];
            }
        } else if (data.count > 0) {
            comment.reactions.push({ emoji, count: data.count, reacted: data.reacted, users: data.users ?? [] });
        }
    }
}

function toggleEmojiPicker(id: number) {
    openEmojiPickerFor.value = openEmojiPickerFor.value === id ? null : id;
}

async function voteComment(commentId: number, type: 'up' | 'down') {
    const res = await apiFetch(route('ticket-comments.vote', commentId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type }),
    });
    if (res.ok) {
        const data = await res.json();
        const comment = comments.value.find((c) => c.id === commentId);
        if (!comment) return;
        comment.upvotes = data.upvotes;
        comment.downvotes = data.downvotes;
        comment.userVote = data.userVote;
        comment.upvoters = data.upvoters ?? [];
        comment.downvoters = data.downvoters ?? [];
        if (sortOrder.value === 'relevant') pendingRefresh.value = true;
    }
}

function canModerateComments(): boolean {
    const user = authUser.value;
    if (!user) return false;
    return user.role === 'admin' || user.role === 'supervisor';
}

function canDelete(comment: Comment): boolean {
    const user = authUser.value;
    if (!user) return false;
    return comment.userId === user.id || canModerateComments();
}

const canPin = computed(() => {
    const user = authUser.value;
    if (!user) return false;
    return canModerateComments() || user.id === props.reporterId;
});

async function pinComment(commentId: number) {
    const res = await apiFetch(route('ticket-comments.pin', commentId), { method: 'POST' });
    if (res.ok) {
        const data = await res.json();
        const c = comments.value.find((c) => c.id === commentId);
        if (c) c.isPinned = data.isPinned;
    }
}

function getUserInitials(): string {
    const user = authUser.value;
    if (!user?.name) return '?';
    return user.name
        .split(' ')
        .map((w: string) => w[0]?.toUpperCase() ?? '')
        .slice(0, 2)
        .join('');
}

function isBodyEmpty(html: string): boolean {
    return !html.trim() || html === '<p></p>' || html === '<p><br></p>';
}

function onBodyClick(e: MouseEvent) {
    const target = e.target as HTMLElement;
    if (target.tagName === 'IMG') {
        window.open((target as HTMLImageElement).src, '_blank', 'noopener,noreferrer');
    }
}

function computeRelevantOrder(topLevel: Comment[]): Comment[] {
    return [...topLevel].sort((a, b) => {
        const netA = a.upvotes - a.downvotes;
        const netB = b.upvotes - b.downvotes;
        if (netB !== netA) return netB - netA;
        const engA = a.reactions.reduce((s, r) => s + r.count, 0) + comments.value.filter((c) => c.parentId === a.id).length;
        const engB = b.reactions.reduce((s, r) => s + r.count, 0) + comments.value.filter((c) => c.parentId === b.id).length;
        return engB - engA || a.id - b.id;
    });
}

function snapshotRelevantOrder(): void {
    const topLevel = comments.value.filter((c) => c.parentId === null);
    frozenRelevantOrder.value = computeRelevantOrder(topLevel).map((c) => c.id);
    pendingRefresh.value = false;
}

watch(sortOrder, (val) => {
    if (val === 'relevant') snapshotRelevantOrder();
    else pendingRefresh.value = false;
});

const topLevelComments = computed(() => {
    const topLevel = comments.value.filter((c) => c.parentId === null);
    let sorted: Comment[];
    if (sortOrder.value === 'newest') {
        sorted = [...topLevel].sort((a, b) => b.id - a.id);
    } else if (sortOrder.value === 'relevant') {
        if (frozenRelevantOrder.value.length) {
            const order = frozenRelevantOrder.value;
            sorted = [...topLevel].sort((a, b) => {
                const ai = order.indexOf(a.id);
                const bi = order.indexOf(b.id);
                const ap = ai === -1 ? order.length : ai;
                const bp = bi === -1 ? order.length : bi;
                return ap - bp;
            });
        } else {
            sorted = computeRelevantOrder(topLevel);
        }
    } else {
        sorted = [...topLevel].sort((a, b) => a.id - b.id);
    }
    const pinned = sorted.filter((c) => c.isPinned);
    const unpinned = sorted.filter((c) => !c.isPinned);
    return [...pinned, ...unpinned];
});

function repliesFor(parentId: number): Comment[] {
    return comments.value.filter((c) => c.parentId === parentId);
}

function startReply(commentId: number) {
    replyingToId.value = commentId;
    replyBody.value = '';
    openEmojiPickerFor.value = null;
    activeUserCard.value = null;
}

function cancelReply() {
    replyingToId.value = null;
    replyBody.value = '';
}

async function submitReply(parentComment: Comment) {
    if (isBodyEmpty(replyBody.value) || isReplying.value) return;
    isReplying.value = true;
    try {
        const res = await apiFetch(route('tickets.comments.store', props.ticketId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ body: replyBody.value, parent_id: parentComment.id }),
        });
        if (res.ok) {
            const reply = await res.json();
            comments.value.push(reply);
            subscribed.value = true;
            replyingToId.value = null;
            replyBody.value = '';
            newCommentId.value = reply.id;
            setTimeout(() => {
                newCommentId.value = null;
            }, 3000);
        }
    } finally {
        isReplying.value = false;
    }
}

function closePopovers() {
    openEmojiPickerFor.value = null;
    activeUserCard.value = null;
}

function openInteractionsModal(comment: Comment) {
    interactionsModalComment.value = comment;
    openEmojiPickerFor.value = null;
    activeUserCard.value = null;
}

onMounted(() => {
    loadComments();
    document.addEventListener('click', closePopovers);
});
onUnmounted(() => {
    document.removeEventListener('click', closePopovers);
});
watch(() => props.ticketId, loadComments);
</script>

<template>
    <div class="relative flex flex-col gap-0">
        <!-- ── Header ──────────────────────────────────────────────── -->
        <div class="mb-4 flex items-center gap-2.5 border-t border-border/40 pt-4">
            <MessageSquare class="h-4 w-4 shrink-0 text-muted-foreground" />
            <span class="text-sm font-semibold text-foreground">Comments</span>

            <!-- Count badge — skeleton while loading -->
            <template v-if="isLoading">
                <div class="h-5 w-7 animate-pulse rounded-full bg-muted" />
            </template>
            <span
                v-else-if="comments.length"
                class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-muted px-1.5 text-[10px] font-bold tabular-nums text-muted-foreground"
                >{{ comments.length }}</span
            >

            <!-- Sort controls -->
            <div v-if="!isLoading && topLevelComments.length > 1" class="ml-1 flex items-center gap-0.5 rounded-lg bg-muted p-0.5">
                <button
                    v-for="opt in [
                        { value: 'relevant', label: 'Relevant' },
                        { value: 'oldest', label: 'Oldest' },
                        { value: 'newest', label: 'Newest' },
                    ] as const"
                    :key="opt.value"
                    type="button"
                    @click="sortOrder = opt.value"
                    :class="[
                        'rounded-md px-2 py-0.5 text-[11px] font-medium transition-all',
                        sortOrder === opt.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ opt.label }}
                </button>
            </div>

            <!-- Subscribe toggle — skeleton while loading -->
            <template v-if="isLoading">
                <div class="ml-auto h-6 w-24 animate-pulse rounded-full bg-muted" />
            </template>
            <button
                v-else
                type="button"
                @click="toggleSubscription"
                :disabled="isTogglingSubscription"
                :title="subscribed ? 'Unsubscribe from comment notifications' : 'Get notified when someone comments'"
                :class="[
                    'ml-auto inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition-all duration-200',
                    subscribed
                        ? 'border-primary/30 bg-primary/10 text-primary hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive'
                        : 'border-border/60 text-muted-foreground hover:border-primary/40 hover:bg-primary/5 hover:text-primary',
                    isTogglingSubscription && 'pointer-events-none cursor-not-allowed opacity-50',
                ]"
            >
                <span v-if="isTogglingSubscription" class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                <BellOff v-else-if="subscribed" class="h-3 w-3" />
                <Bell v-else class="h-3 w-3" />
                {{ subscribed ? 'Subscribed' : 'Subscribe' }}
            </button>
        </div>

        <!-- ── New comment editor ─────────────────────────────────── -->
        <div :class="['flex gap-3 border-b border-border/30 pb-4 transition-all duration-200', editorFocused ? 'mb-1' : 'mb-0']">
            <!-- Current user avatar -->
            <div
                :class="[
                    'mt-0.5 flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-full border text-xs font-bold',
                    authUser ? getAvatarColor(authUser.name) : 'border-border/40 bg-muted text-muted-foreground',
                ]"
            >
                {{ getUserInitials() }}
            </div>

            <div class="flex flex-1 flex-col gap-2">
                <CommentEditor v-model="newBody" :ticket-id="props.ticketId" placeholder="Write a comment…" @focus="editorFocused = true" />

                <!-- Action row — only show when editor has content or is focused -->
                <Transition name="fade">
                    <div v-if="editorFocused || !isBodyEmpty(newBody)" class="flex items-center justify-between">
                        <p class="text-[11px] text-muted-foreground/60">
                            <span v-if="subscribed">You'll be notified of replies.</span>
                            <span v-else>Subscribe to get notified of replies.</span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="editorFocused"
                                type="button"
                                @click="
                                    newBody = '';
                                    editorFocused = false;
                                "
                                class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitComment"
                                :disabled="isSubmitting || isBodyEmpty(newBody)"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <span v-if="isSubmitting" class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                <SendHorizonal v-else class="h-3 w-3" />
                                {{ isSubmitting ? 'Posting…' : 'Post' }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- ── Loading skeleton ───────────────────────────────────── -->
        <div v-if="isLoading" class="mb-2 flex flex-col gap-1" aria-label="Loading comments…">
            <div
                v-for="(item, i) in [
                    { lines: [{ w: 'w-full' }, { w: 'w-4/5' }, { w: 'w-3/5' }], reactions: 2, reply: true },
                    { lines: [{ w: 'w-full' }, { w: 'w-2/3' }], reactions: 0, reply: false },
                    { lines: [{ w: 'w-full' }, { w: 'w-11/12' }, { w: 'w-1/2' }], reactions: 1, reply: true },
                ]"
                :key="i"
                class="flex animate-pulse gap-3 rounded-xl px-2 py-3"
            >
                <!-- Avatar -->
                <div class="mt-0.5 h-8 w-8 shrink-0 rounded-full bg-muted" />

                <!-- Body -->
                <div class="min-w-0 flex-1 space-y-2 pr-10">
                    <!-- Name row: name + role badge + timestamp -->
                    <div class="flex items-center gap-2">
                        <div :class="['h-2.5 rounded-full bg-muted', i === 1 ? 'w-24' : 'w-20']" />
                        <div class="h-4 w-14 rounded-full bg-muted/60" />
                        <div class="h-2 w-10 rounded-full bg-muted/40" />
                    </div>

                    <!-- Body lines -->
                    <div v-for="(line, li) in item.lines" :key="li" :class="['h-2.5 rounded-full bg-muted', line.w]" />

                    <!-- Vote row + optional reaction pills -->
                    <div class="flex items-center gap-2 pt-0.5">
                        <div class="h-6 w-16 rounded-full bg-muted/60" />
                        <div v-for="r in item.reactions" :key="r" class="h-5 w-10 rounded-full bg-muted/50" />
                    </div>
                </div>
            </div>

            <!-- Indented reply skeleton under the first comment -->
            <div class="ml-11 animate-pulse border-l-2 border-border/20 pl-3">
                <div class="flex gap-2.5 rounded-xl px-2 py-2">
                    <div class="mt-0.5 h-6 w-6 shrink-0 rounded-full bg-muted" />
                    <div class="flex-1 space-y-1.5">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-16 rounded-full bg-muted" />
                            <div class="h-3.5 w-12 rounded-full bg-muted/60" />
                        </div>
                        <div class="h-2 w-4/5 rounded-full bg-muted" />
                        <div class="h-2 w-3/5 rounded-full bg-muted/70" />
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Comment list ───────────────────────────────────────── -->
        <div v-else-if="comments.length" class="mb-2 flex flex-col">
            <div
                v-for="(comment, idx) in topLevelComments"
                :key="comment.id"
                :ref="
                    idx === topLevelComments.length - 1
                        ? (el) => {
                              lastCommentRef = el as HTMLElement;
                          }
                        : undefined
                "
            >
                <!-- Top-level comment row -->
                <div
                    :class="[
                        'group relative flex gap-3 rounded-xl px-2 py-2.5 transition-all duration-300',
                        newCommentId === comment.id ? 'bg-primary/5 ring-1 ring-primary/20' : 'hover:bg-muted/30',
                        pendingDeleteId === comment.id ? 'bg-destructive/5 ring-1 ring-destructive/20' : '',
                    ]"
                >
                    <!-- Avatar (clickable) -->
                    <div class="relative shrink-0" @click.stop="toggleUserCard(comment.id)">
                        <div
                            :class="[
                                'flex h-8 w-8 cursor-pointer select-none items-center justify-center rounded-full border text-xs font-bold transition-all hover:ring-2 hover:ring-primary/40 hover:ring-offset-1',
                                getAvatarColor(comment.userName),
                            ]"
                        >
                            {{ comment.userInitials }}
                        </div>

                        <!-- User card popover -->
                        <div
                            v-if="activeUserCard === comment.id"
                            class="absolute left-0 top-10 z-50 flex w-52 flex-col gap-2 rounded-xl border border-border bg-popover p-3 shadow-xl"
                        >
                            <div class="flex items-center gap-2.5">
                                <div
                                    :class="[
                                        'flex h-10 w-10 shrink-0 select-none items-center justify-center rounded-full border text-sm font-bold',
                                        getAvatarColor(comment.userName),
                                    ]"
                                >
                                    {{ comment.userInitials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold leading-tight text-foreground">{{ comment.userName }}</p>
                                    <span
                                        :class="[
                                            'mt-0.5 inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[10px] font-bold',
                                            getRoleBadgeClass(comment.userRole),
                                        ]"
                                    >
                                        <component :is="getRoleIcon(comment.userRole)" class="h-2.5 w-2.5" />
                                        {{ getRoleLabel(comment.userRole) }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="comment.userEmail"
                                class="flex items-center gap-1.5 border-t border-border/40 pt-2 text-[11px] text-muted-foreground"
                            >
                                <Mail class="h-3 w-3 shrink-0" />
                                <span class="truncate">{{ comment.userEmail }}</span>
                            </div>
                            <p v-if="comment.userId === authUser?.id" class="-mt-1 text-[10px] font-medium text-primary">This is you</p>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1 pr-16">
                        <div class="mb-1 flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                @click.stop="toggleUserCard(comment.id)"
                                class="text-sm font-semibold leading-none text-foreground transition-colors hover:text-primary hover:underline"
                            >
                                {{ comment.userName }}
                            </button>
                            <span
                                :class="[
                                    'inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-[9px] font-bold leading-none',
                                    getRoleBadgeClass(comment.userRole),
                                ]"
                            >
                                <component :is="getRoleIcon(comment.userRole)" class="h-2.5 w-2.5" />
                                {{ getRoleLabel(comment.userRole) }}
                            </span>
                            <span class="text-[11px] leading-none text-muted-foreground/70">· {{ comment.createdAt }}</span>
                            <span
                                v-if="newCommentId === comment.id"
                                class="inline-flex items-center rounded-full bg-primary/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-primary"
                                >New</span
                            >
                            <span
                                v-if="comment.isPinned"
                                class="inline-flex items-center gap-0.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-bold leading-none text-amber-600 dark:text-amber-400"
                            >
                                <Pin class="h-2.5 w-2.5" /> Pinned
                            </span>
                        </div>
                        <div
                            class="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed text-foreground/85 [&_img]:mt-2 [&_img]:block [&_img]:max-h-52 [&_img]:w-auto [&_img]:max-w-[min(100%,20rem)] [&_img]:cursor-pointer [&_img]:rounded-lg [&_img]:border [&_img]:border-border/40 [&_img]:object-contain [&_li]:my-0 [&_ol]:my-1 [&_p]:my-0.5 [&_ul]:my-1"
                            @click="onBodyClick"
                            v-html="comment.body"
                        />
                        <!-- Vote row + reactions (same line) -->
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <div class="inline-flex shrink-0 items-center gap-0 overflow-hidden rounded-full border border-border/40 bg-muted/40">
                                <button
                                    type="button"
                                    @click="voteComment(comment.id, 'up')"
                                    :class="[
                                        'flex h-6 w-6 items-center justify-center transition-all duration-150 active:scale-90',
                                        comment.userVote === 'up'
                                            ? 'bg-indigo-500/10 text-indigo-500'
                                            : 'text-muted-foreground hover:bg-indigo-500/10 hover:text-indigo-500',
                                    ]"
                                    title="Upvote"
                                >
                                    <ArrowBigUp class="h-3.5 w-3.5" :fill="comment.userVote === 'up' ? 'currentColor' : 'none'" />
                                </button>
                                <span
                                    :class="[
                                        'min-w-[20px] px-1 text-center text-xs font-bold tabular-nums leading-none',
                                        comment.userVote === 'up'
                                            ? 'text-indigo-500'
                                            : comment.userVote === 'down'
                                              ? 'text-orange-500'
                                              : 'text-foreground/70',
                                    ]"
                                    >{{ comment.upvotes - comment.downvotes }}</span
                                >
                                <button
                                    type="button"
                                    @click="voteComment(comment.id, 'down')"
                                    :class="[
                                        'flex h-6 w-6 items-center justify-center transition-all duration-150 active:scale-90',
                                        comment.userVote === 'down'
                                            ? 'bg-orange-500/10 text-orange-500'
                                            : 'text-muted-foreground hover:bg-orange-500/10 hover:text-orange-500',
                                    ]"
                                    title="Downvote"
                                >
                                    <ArrowBigDown class="h-3.5 w-3.5" :fill="comment.userVote === 'down' ? 'currentColor' : 'none'" />
                                </button>
                            </div>
                            <div v-if="comment.reactions.length" class="flex flex-wrap items-center gap-1">
                                <div v-for="reaction in comment.reactions" :key="reaction.emoji" class="group/rxn relative">
                                    <button
                                        type="button"
                                        @click="toggleReaction(comment.id, reaction.emoji)"
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-all duration-150 active:scale-90',
                                            reaction.reacted
                                                ? 'border-primary/40 bg-primary/10 font-semibold text-primary shadow-sm shadow-primary/10'
                                                : 'border-border/40 bg-muted/40 text-foreground/60 hover:border-border hover:bg-muted hover:text-foreground',
                                        ]"
                                    >
                                        <span class="text-sm leading-none">{{ reaction.emoji }}</span>
                                        <span class="tabular-nums">{{ reaction.count }}</span>
                                    </button>
                                    <!-- Hover tooltip: who reacted -->
                                    <div
                                        class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 -translate-x-1/2 opacity-0 transition-opacity duration-150 group-hover/rxn:opacity-100"
                                    >
                                        <div
                                            class="max-w-[180px] whitespace-nowrap rounded-lg border border-border/60 bg-popover px-2.5 py-1.5 text-[11px] text-popover-foreground shadow-lg"
                                        >
                                            <div class="mb-0.5 text-center font-semibold">{{ reaction.emoji }} {{ reaction.count }}</div>
                                            <div
                                                v-for="(name, i) in reaction.users.slice(0, 5)"
                                                :key="i"
                                                class="truncate leading-tight text-muted-foreground"
                                            >
                                                {{ name }}
                                            </div>
                                            <div v-if="reaction.users.length > 5" class="mt-0.5 text-center text-[10px] text-muted-foreground/60">
                                                +{{ reaction.users.length - 5 }} more
                                            </div>
                                        </div>
                                        <div class="mx-auto -mt-1.5 h-2 w-2 rotate-45 border-b border-r border-border/60 bg-popover"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="pendingDeleteId === comment.id" class="mt-2 flex items-center gap-2 text-xs">
                            <span class="text-muted-foreground">Remove this comment?</span>
                            <button
                                type="button"
                                @click="deleteComment(comment.id)"
                                class="inline-flex items-center gap-1 rounded bg-destructive px-2 py-0.5 text-xs font-medium text-destructive-foreground transition-colors hover:bg-destructive/90"
                            >
                                Yes, delete
                            </button>
                            <button
                                type="button"
                                @click="cancelDelete"
                                class="inline-flex items-center rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/80"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Hover action bar -->
                    <div
                        class="absolute right-2 top-2 flex items-center gap-0.5 rounded-lg border border-border/40 bg-background/90 px-1 py-0.5 opacity-0 shadow-sm backdrop-blur-sm transition-opacity duration-150 group-hover:opacity-100"
                        @click.stop
                    >
                        <!-- Reply -->
                        <button
                            type="button"
                            @click="startReply(comment.id)"
                            class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                            title="Reply"
                        >
                            <CornerDownRight class="h-3.5 w-3.5" />
                        </button>
                        <!-- Pin / Unpin -->
                        <button
                            v-if="canPin"
                            type="button"
                            @click="pinComment(comment.id)"
                            :class="[
                                'flex h-6 w-6 items-center justify-center rounded transition-colors',
                                comment.isPinned
                                    ? 'bg-amber-500/10 text-amber-500 hover:bg-amber-500/20'
                                    : 'text-muted-foreground hover:bg-amber-500/10 hover:text-amber-500',
                            ]"
                            :title="comment.isPinned ? 'Unpin' : 'Pin'"
                        >
                            <Pin class="h-3.5 w-3.5" />
                        </button>
                        <!-- Interactions Info -->
                        <div v-if="comment.upvotes > 0 || comment.downvotes > 0 || comment.reactions.length > 0" class="relative">
                            <button
                                type="button"
                                @click="openInteractionsModal(comment)"
                                class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="View interactions"
                            >
                                <Users class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <!-- Emoji picker -->
                        <div class="relative">
                            <button
                                type="button"
                                @click="toggleEmojiPicker(comment.id)"
                                class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="Add reaction"
                            >
                                <SmilePlus class="h-3.5 w-3.5" />
                            </button>
                            <div
                                v-if="openEmojiPickerFor === comment.id"
                                class="absolute bottom-full right-0 z-50 mb-1 flex gap-0.5 rounded-xl border border-border bg-popover p-1.5 shadow-xl"
                            >
                                <button
                                    v-for="emoji in ALLOWED_EMOJIS"
                                    :key="emoji"
                                    type="button"
                                    @click="toggleReaction(comment.id, emoji)"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg text-base transition-all hover:scale-125 hover:bg-muted active:scale-95"
                                >
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                        <!-- Delete -->
                        <button
                            v-if="canDelete(comment) && pendingDeleteId !== comment.id"
                            type="button"
                            @click="confirmDelete(comment.id)"
                            class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                            title="Delete comment"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Replies (indented under parent) -->
                <div v-if="repliesFor(comment.id).length" class="mb-1 ml-11 mt-0.5 flex flex-col gap-0.5 border-l-2 border-border/20 pl-3">
                    <div
                        v-for="reply in repliesFor(comment.id)"
                        :key="reply.id"
                        :class="[
                            'group relative flex gap-2.5 rounded-xl px-2 py-2 transition-all duration-300',
                            newCommentId === reply.id ? 'bg-primary/5 ring-1 ring-primary/20' : 'hover:bg-muted/30',
                            pendingDeleteId === reply.id ? 'bg-destructive/5 ring-1 ring-destructive/20' : '',
                        ]"
                    >
                        <!-- Avatar -->
                        <div class="relative shrink-0" @click.stop="toggleUserCard(reply.id)">
                            <div
                                :class="[
                                    'flex h-6 w-6 cursor-pointer select-none items-center justify-center rounded-full border text-[10px] font-bold transition-all hover:ring-2 hover:ring-primary/40 hover:ring-offset-1',
                                    getAvatarColor(reply.userName),
                                ]"
                            >
                                {{ reply.userInitials }}
                            </div>
                            <div
                                v-if="activeUserCard === reply.id"
                                class="absolute left-0 top-8 z-50 flex w-52 flex-col gap-2 rounded-xl border border-border bg-popover p-3 shadow-xl"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        :class="[
                                            'flex h-10 w-10 shrink-0 select-none items-center justify-center rounded-full border text-sm font-bold',
                                            getAvatarColor(reply.userName),
                                        ]"
                                    >
                                        {{ reply.userInitials }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold leading-tight text-foreground">{{ reply.userName }}</p>
                                        <span
                                            :class="[
                                                'mt-0.5 inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[10px] font-bold',
                                                getRoleBadgeClass(reply.userRole),
                                            ]"
                                        >
                                            <component :is="getRoleIcon(reply.userRole)" class="h-2.5 w-2.5" />
                                            {{ getRoleLabel(reply.userRole) }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="reply.userEmail"
                                    class="flex items-center gap-1.5 border-t border-border/40 pt-2 text-[11px] text-muted-foreground"
                                >
                                    <Mail class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ reply.userEmail }}</span>
                                </div>
                                <p v-if="reply.userId === authUser?.id" class="-mt-1 text-[10px] font-medium text-primary">This is you</p>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1 pr-14">
                            <div class="mb-0.5 flex flex-wrap items-center gap-1.5">
                                <button
                                    type="button"
                                    @click.stop="toggleUserCard(reply.id)"
                                    class="text-xs font-semibold leading-none text-foreground transition-colors hover:text-primary hover:underline"
                                >
                                    {{ reply.userName }}
                                </button>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-[9px] font-bold leading-none',
                                        getRoleBadgeClass(reply.userRole),
                                    ]"
                                >
                                    <component :is="getRoleIcon(reply.userRole)" class="h-2.5 w-2.5" />
                                    {{ getRoleLabel(reply.userRole) }}
                                </span>
                                <span class="text-[11px] leading-none text-muted-foreground/70">· {{ reply.createdAt }}</span>
                                <span
                                    v-if="newCommentId === reply.id"
                                    class="inline-flex items-center rounded-full bg-primary/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-primary"
                                    >New</span
                                >
                            </div>
                            <div
                                class="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed text-foreground/85 [&_img]:mt-2 [&_img]:block [&_img]:max-h-52 [&_img]:w-auto [&_img]:max-w-[min(100%,20rem)] [&_img]:cursor-pointer [&_img]:rounded-lg [&_img]:border [&_img]:border-border/40 [&_img]:object-contain [&_li]:my-0 [&_ol]:my-1 [&_p]:my-0.5 [&_ul]:my-1"
                                @click="onBodyClick"
                                v-html="reply.body"
                            />
                            <!-- Vote row + reactions (same line) -->
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                <div class="inline-flex shrink-0 items-center gap-0 overflow-hidden rounded-full border border-border/40 bg-muted/40">
                                    <button
                                        type="button"
                                        @click="voteComment(reply.id, 'up')"
                                        :class="[
                                            'flex h-6 w-6 items-center justify-center transition-all duration-150 active:scale-90',
                                            reply.userVote === 'up'
                                                ? 'bg-indigo-500/10 text-indigo-500'
                                                : 'text-muted-foreground hover:bg-indigo-500/10 hover:text-indigo-500',
                                        ]"
                                        title="Upvote"
                                    >
                                        <ArrowBigUp class="h-3.5 w-3.5" :fill="reply.userVote === 'up' ? 'currentColor' : 'none'" />
                                    </button>
                                    <span
                                        :class="[
                                            'min-w-[20px] px-1 text-center text-xs font-bold tabular-nums leading-none',
                                            reply.userVote === 'up'
                                                ? 'text-indigo-500'
                                                : reply.userVote === 'down'
                                                  ? 'text-orange-500'
                                                  : 'text-foreground/70',
                                        ]"
                                        >{{ reply.upvotes - reply.downvotes }}</span
                                    >
                                    <button
                                        type="button"
                                        @click="voteComment(reply.id, 'down')"
                                        :class="[
                                            'flex h-6 w-6 items-center justify-center transition-all duration-150 active:scale-90',
                                            reply.userVote === 'down'
                                                ? 'bg-orange-500/10 text-orange-500'
                                                : 'text-muted-foreground hover:bg-orange-500/10 hover:text-orange-500',
                                        ]"
                                        title="Downvote"
                                    >
                                        <ArrowBigDown class="h-3.5 w-3.5" :fill="reply.userVote === 'down' ? 'currentColor' : 'none'" />
                                    </button>
                                </div>
                                <div v-if="reply.reactions.length" class="flex flex-wrap items-center gap-1">
                                    <div v-for="reaction in reply.reactions" :key="reaction.emoji" class="group/rxn relative">
                                        <button
                                            type="button"
                                            @click="toggleReaction(reply.id, reaction.emoji)"
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-all duration-150 active:scale-90',
                                                reaction.reacted
                                                    ? 'border-primary/40 bg-primary/10 font-semibold text-primary shadow-sm shadow-primary/10'
                                                    : 'border-border/40 bg-muted/40 text-foreground/60 hover:border-border hover:bg-muted hover:text-foreground',
                                            ]"
                                        >
                                            <span class="text-sm leading-none">{{ reaction.emoji }}</span>
                                            <span class="tabular-nums">{{ reaction.count }}</span>
                                        </button>
                                        <!-- Hover tooltip: who reacted -->
                                        <div
                                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 -translate-x-1/2 opacity-0 transition-opacity duration-150 group-hover/rxn:opacity-100"
                                        >
                                            <div
                                                class="max-w-[180px] whitespace-nowrap rounded-lg border border-border/60 bg-popover px-2.5 py-1.5 text-[11px] text-popover-foreground shadow-lg"
                                            >
                                                <div class="mb-0.5 text-center font-semibold">{{ reaction.emoji }} {{ reaction.count }}</div>
                                                <div
                                                    v-for="(name, i) in reaction.users.slice(0, 5)"
                                                    :key="i"
                                                    class="truncate leading-tight text-muted-foreground"
                                                >
                                                    {{ name }}
                                                </div>
                                                <div v-if="reaction.users.length > 5" class="mt-0.5 text-center text-[10px] text-muted-foreground/60">
                                                    +{{ reaction.users.length - 5 }} more
                                                </div>
                                            </div>
                                            <div class="mx-auto -mt-1.5 h-2 w-2 rotate-45 border-b border-r border-border/60 bg-popover"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="pendingDeleteId === reply.id" class="mt-2 flex items-center gap-2 text-xs">
                                <span class="text-muted-foreground">Remove this reply?</span>
                                <button
                                    type="button"
                                    @click="deleteComment(reply.id)"
                                    class="inline-flex items-center gap-1 rounded bg-destructive px-2 py-0.5 text-xs font-medium text-destructive-foreground transition-colors hover:bg-destructive/90"
                                >
                                    Yes, delete
                                </button>
                                <button
                                    type="button"
                                    @click="cancelDelete"
                                    class="inline-flex items-center rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/80"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- Hover action bar for reply -->
                        <div
                            class="absolute right-2 top-1.5 flex items-center gap-0.5 rounded-lg border border-border/40 bg-background/90 px-1 py-0.5 opacity-0 shadow-sm backdrop-blur-sm transition-opacity duration-150 group-hover:opacity-100"
                            @click.stop
                        >
                            <!-- Interactions Info -->
                            <div v-if="reply.upvotes > 0 || reply.downvotes > 0 || reply.reactions.length > 0" class="relative">
                                <button
                                    type="button"
                                    @click="openInteractionsModal(reply)"
                                    class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    title="View interactions"
                                >
                                    <Users class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="relative">
                                <button
                                    type="button"
                                    @click="toggleEmojiPicker(reply.id)"
                                    class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    title="Add reaction"
                                >
                                    <SmilePlus class="h-3.5 w-3.5" />
                                </button>
                                <div
                                    v-if="openEmojiPickerFor === reply.id"
                                    class="absolute bottom-full right-0 z-50 mb-1 flex gap-0.5 rounded-xl border border-border bg-popover p-1.5 shadow-xl"
                                >
                                    <button
                                        v-for="emoji in ALLOWED_EMOJIS"
                                        :key="emoji"
                                        type="button"
                                        @click="toggleReaction(reply.id, emoji)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg text-base transition-all hover:scale-125 hover:bg-muted active:scale-95"
                                    >
                                        {{ emoji }}
                                    </button>
                                </div>
                            </div>
                            <button
                                v-if="canDelete(reply) && pendingDeleteId !== reply.id"
                                type="button"
                                @click="confirmDelete(reply.id)"
                                class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                title="Delete reply"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Inline reply editor -->
                <Transition name="fade">
                    <div v-if="replyingToId === comment.id" class="mb-2 ml-11 mt-1 flex items-start gap-2.5">
                        <!-- User avatar -->
                        <div
                            :class="[
                                'mt-0.5 flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                authUser ? getAvatarColor(authUser.name) : 'border-border/40 bg-muted text-muted-foreground',
                            ]"
                        >
                            {{ getUserInitials() }}
                        </div>
                        <div class="flex flex-1 flex-col gap-2">
                            <!-- Replying-to context pill -->
                            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <CornerDownRight class="h-3 w-3 shrink-0 text-primary/60" />
                                <span
                                    >Replying to <span class="font-semibold text-foreground">{{ comment.userName }}</span></span
                                >
                            </div>
                            <CommentEditor v-model="replyBody" :ticket-id="props.ticketId" placeholder="Write a reply…" @focus="() => {}" />
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    @click="cancelReply"
                                    class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    @click="submitReply(comment)"
                                    :disabled="isReplying || isBodyEmpty(replyBody)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <span v-if="isReplying" class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    <CornerDownRight v-else class="h-3 w-3" />
                                    {{ isReplying ? 'Replying…' : 'Reply' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- ── Empty state ────────────────────────────────────────── -->
        <div v-else-if="!isLoading" class="mb-2 flex flex-col items-center gap-3 py-8">
            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border/40 bg-muted/60">
                <MessageCirclePlus class="h-6 w-6 text-muted-foreground/60" />
            </div>
            <div class="text-center">
                <p class="text-sm font-medium text-foreground/70">No comments yet</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Be the first to comment above</p>
            </div>
        </div>

        <!-- ── Floating Refresh (relevant sort only) ───────────────── -->
        <Transition name="float-btn">
            <div v-if="pendingRefresh" class="pointer-events-none sticky bottom-4 mt-3 flex justify-center">
                <button
                    type="button"
                    @click="snapshotRelevantOrder"
                    class="pointer-events-auto inline-flex items-center gap-1.5 rounded-full bg-primary px-3.5 py-1.5 text-xs font-semibold text-primary-foreground shadow-lg transition-all duration-150 hover:opacity-90 active:scale-95"
                >
                    <RotateCcw class="h-3 w-3" />
                    Refresh order
                </button>
            </div>
        </Transition>
    </div>

    <!-- Interactions Modal -->
    <Dialog
        :open="interactionsModalComment !== null"
        @update:open="
            (val) => {
                if (!val) interactionsModalComment = null;
            }
        "
    >
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-[425px]">
            <DialogHeader class="border-b border-border/40 bg-muted/20 p-4 pb-0">
                <DialogTitle class="flex items-center gap-2 text-base font-bold">
                    <Users class="h-4 w-4 text-muted-foreground" />
                    Interactions
                </DialogTitle>
                <DialogDescription class="mb-3 mt-1 text-xs text-muted-foreground">
                    People who reacted to <span class="font-medium text-foreground">{{ interactionsModalComment?.userName }}</span
                    >'s comment.
                </DialogDescription>
            </DialogHeader>

            <div class="bg-background px-4 py-3" v-if="interactionsModalComment">
                <Tabs defaultValue="all" class="w-full">
                    <TabsList class="hide-scrollbar h-9 w-full flex-nowrap justify-start overflow-x-auto rounded-lg bg-muted/40 p-1">
                        <TabsTrigger value="all" class="h-7 rounded-md px-3 text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm"
                            >All</TabsTrigger
                        >
                        <TabsTrigger
                            v-if="interactionsModalComment.upvotes > 0"
                            value="upvotes"
                            class="flex h-7 items-center gap-1.5 rounded-md px-3 text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm"
                        >
                            <ArrowBigUp class="h-3.5 w-3.5 text-indigo-500" />
                            <span class="tabular-nums">{{ interactionsModalComment.upvotes }}</span>
                        </TabsTrigger>
                        <TabsTrigger
                            v-if="interactionsModalComment.downvotes > 0"
                            value="downvotes"
                            class="flex h-7 items-center gap-1.5 rounded-md px-3 text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm"
                        >
                            <ArrowBigDown class="h-3.5 w-3.5 text-orange-500" />
                            <span class="tabular-nums">{{ interactionsModalComment.downvotes }}</span>
                        </TabsTrigger>
                        <TabsTrigger
                            v-for="rxn in interactionsModalComment.reactions"
                            :key="rxn.emoji"
                            :value="rxn.emoji"
                            class="flex h-7 items-center gap-1.5 rounded-md px-3 text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm"
                        >
                            <span>{{ rxn.emoji }}</span>
                            <span class="tabular-nums">{{ rxn.count }}</span>
                        </TabsTrigger>
                    </TabsList>

                    <div class="custom-scrollbar mt-4 max-h-[40vh] overflow-y-auto pr-2">
                        <!-- All Tab -->
                        <TabsContent value="all" class="mt-0 flex flex-col gap-4 focus-visible:outline-none">
                            <div v-if="interactionsModalComment.upvotes > 0">
                                <h4 class="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                    <ArrowBigUp class="h-3.5 w-3.5 text-indigo-500" /> Upvotes
                                </h4>
                                <div class="flex flex-col gap-1.5">
                                    <div
                                        v-for="user in interactionsModalComment.upvoters"
                                        :key="user"
                                        class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                                    >
                                        <div
                                            :class="[
                                                'flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                                getAvatarColor(user),
                                            ]"
                                        >
                                            {{
                                                user
                                                    .split(' ')
                                                    .map((w: string) => w[0]?.toUpperCase() ?? '')
                                                    .slice(0, 2)
                                                    .join('')
                                            }}
                                        </div>
                                        <span class="text-sm font-medium text-foreground">{{ user }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="interactionsModalComment.downvotes > 0">
                                <h4 class="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                    <ArrowBigDown class="h-3.5 w-3.5 text-orange-500" /> Downvotes
                                </h4>
                                <div class="flex flex-col gap-1.5">
                                    <div
                                        v-for="user in interactionsModalComment.downvoters"
                                        :key="user"
                                        class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                                    >
                                        <div
                                            :class="[
                                                'flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                                getAvatarColor(user),
                                            ]"
                                        >
                                            {{
                                                user
                                                    .split(' ')
                                                    .map((w: string) => w[0]?.toUpperCase() ?? '')
                                                    .slice(0, 2)
                                                    .join('')
                                            }}
                                        </div>
                                        <span class="text-sm font-medium text-foreground">{{ user }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-for="rxn in interactionsModalComment.reactions" :key="rxn.emoji">
                                <h4 class="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                    <span class="text-base leading-none">{{ rxn.emoji }}</span> Reactions
                                </h4>
                                <div class="flex flex-col gap-1.5">
                                    <div
                                        v-for="user in rxn.users"
                                        :key="user"
                                        class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                                    >
                                        <div
                                            :class="[
                                                'flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                                getAvatarColor(user),
                                            ]"
                                        >
                                            {{
                                                user
                                                    .split(' ')
                                                    .map((w: string) => w[0]?.toUpperCase() ?? '')
                                                    .slice(0, 2)
                                                    .join('')
                                            }}
                                        </div>
                                        <span class="text-sm font-medium text-foreground">{{ user }}</span>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>

                        <!-- Specific Tabs -->
                        <TabsContent v-if="interactionsModalComment.upvotes > 0" value="upvotes" class="mt-0 focus-visible:outline-none">
                            <div class="flex flex-col gap-1.5">
                                <div
                                    v-for="user in interactionsModalComment.upvoters"
                                    :key="user"
                                    class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                                >
                                    <div
                                        :class="[
                                            'flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                            getAvatarColor(user),
                                        ]"
                                    >
                                        {{
                                            user
                                                .split(' ')
                                                .map((w: string) => w[0]?.toUpperCase() ?? '')
                                                .slice(0, 2)
                                                .join('')
                                        }}
                                    </div>
                                    <span class="text-sm font-medium text-foreground">{{ user }}</span>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent v-if="interactionsModalComment.downvotes > 0" value="downvotes" class="mt-0 focus-visible:outline-none">
                            <div class="flex flex-col gap-1.5">
                                <div
                                    v-for="user in interactionsModalComment.downvoters"
                                    :key="user"
                                    class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                                >
                                    <div
                                        :class="[
                                            'flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                            getAvatarColor(user),
                                        ]"
                                    >
                                        {{
                                            user
                                                .split(' ')
                                                .map((w: string) => w[0]?.toUpperCase() ?? '')
                                                .slice(0, 2)
                                                .join('')
                                        }}
                                    </div>
                                    <span class="text-sm font-medium text-foreground">{{ user }}</span>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent
                            v-for="rxn in interactionsModalComment.reactions"
                            :key="rxn.emoji"
                            :value="rxn.emoji"
                            class="mt-0 focus-visible:outline-none"
                        >
                            <div class="flex flex-col gap-1.5">
                                <div
                                    v-for="user in rxn.users"
                                    :key="user"
                                    class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40"
                                >
                                    <div
                                        :class="[
                                            'flex h-6 w-6 shrink-0 select-none items-center justify-center rounded-full border text-[10px] font-bold',
                                            getAvatarColor(user),
                                        ]"
                                    >
                                        {{
                                            user
                                                .split(' ')
                                                .map((w: string) => w[0]?.toUpperCase() ?? '')
                                                .slice(0, 2)
                                                .join('')
                                        }}
                                    </div>
                                    <span class="text-sm font-medium text-foreground">{{ user }}</span>
                                </div>
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.comment-enter-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.comment-leave-active {
    transition: all 0.2s ease-in;
}
.comment-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
.comment-leave-to {
    opacity: 0;
    transform: translateX(-8px) scale(0.98);
}

.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.float-btn-enter-active,
.float-btn-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.float-btn-enter-from,
.float-btn-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.95);
}
</style>
