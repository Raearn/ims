<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Bell, Check, CheckCheck, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';

interface NotificationData {
    type: string;
    ticket_id: number;
    ticket_title: string;
    message: string;
    old_status?: string;
    new_status?: string;
}

interface Notification {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}

const page = usePage<{ unreadNotificationsCount: number }>();

const open = ref(false);
const loading = ref(false);
const markingAll = ref(false);
const notifications = ref<Notification[]>([]);
const unreadCount = ref(page.props.unreadNotificationsCount ?? 0);

async function fetchNotifications() {
    loading.value = true;
    try {
        const { data } = await axios.get<Notification[]>('/notifications');
        notifications.value = data;
        unreadCount.value = data.filter((n) => !n.read_at).length;
    } finally {
        loading.value = false;
    }
}

async function markAsRead(notification: Notification) {
    if (notification.read_at) {
        return;
    }
    await axios.post(`/notifications/${notification.id}/read`);
    notification.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
}

async function markAllAsRead() {
    markingAll.value = true;
    try {
        await axios.post('/notifications/read-all');
        notifications.value.forEach((n) => {
            n.read_at = n.read_at ?? new Date().toISOString();
        });
        unreadCount.value = 0;
    } finally {
        markingAll.value = false;
    }
}

function onOpenChange(val: boolean) {
    open.value = val;
    if (val) {
        fetchNotifications();
    }
}

function notificationIcon(type: string): string {
    if (type === 'ticket_assigned') return '🎯';
    if (type === 'ticket_status_changed') return '🔄';
    if (type === 'sla_breached') return '⚠️';
    return '🔔';
}

onMounted(() => {
    unreadCount.value = page.props.unreadNotificationsCount ?? 0;
});
</script>

<template>
    <DropdownMenu :open="open" @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 p-0">
            <!-- Header -->
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold">Notifications</h3>
                    <Badge v-if="unreadCount > 0" variant="secondary" class="h-5 px-1.5 text-xs">
                        {{ unreadCount }}
                    </Badge>
                </div>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 text-xs text-muted-foreground hover:text-foreground"
                    :disabled="markingAll"
                    @click="markAllAsRead"
                >
                    <Loader2 v-if="markingAll" class="h-3 w-3 animate-spin" />
                    <CheckCheck v-else class="h-3 w-3" />
                    Mark all read
                </Button>
            </div>

            <!-- List -->
            <div class="max-h-[360px] overflow-y-auto">
                <div v-if="loading" class="flex items-center justify-center py-8">
                    <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                </div>

                <div v-else-if="notifications.length === 0" class="flex flex-col items-center gap-2 py-10 text-center">
                    <Bell class="h-8 w-8 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No notifications yet</p>
                </div>

                <div v-else>
                    <button
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="flex w-full items-start gap-3 border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted/50"
                        :class="{ 'bg-blue-50/60 dark:bg-blue-950/20': !notification.read_at }"
                        @click="markAsRead(notification)"
                    >
                        <span class="mt-0.5 shrink-0 text-base leading-none">
                            {{ notificationIcon(notification.data.type) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm leading-snug" :class="{ 'font-medium': !notification.read_at }">
                                {{ notification.data.message }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ notification.created_at }}</p>
                        </div>
                        <Check
                            v-if="notification.read_at"
                            class="mt-1 h-3.5 w-3.5 shrink-0 text-muted-foreground/50"
                        />
                        <span
                            v-else
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-500"
                        />
                    </button>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
