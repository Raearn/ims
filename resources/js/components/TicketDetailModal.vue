<script setup lang="ts">
import TicketDetailBody from '@/components/TicketDetailBody.vue';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import type { TicketDetail } from '@/types/ticketDetail';

export type { TicketDetail };

withDefaults(
    defineProps<{
        modelValue: boolean;
        ticket: TicketDetail | null;
        priorities: { id: number; name: string; icon: string; color: string }[];
        statuses: { id: number; name: string; icon: string; color: string; handler_requirement?: string }[];
        loading?: boolean;
        showEditButton?: boolean;
        showOpenInTicketsButton?: boolean;
    }>(),
    {
        statuses: () => [],
        loading: false,
        showEditButton: true,
        showOpenInTicketsButton: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    edit: [ticket: TicketDetail];
}>();
</script>

<template>
    <Dialog :open="modelValue" @update:open="emit('update:modelValue', $event)">
        <DialogContent class="flex max-h-[92dvh] flex-col overflow-hidden border-none p-0 shadow-2xl sm:max-w-[580px]">
            <TicketDetailBody
                :ticket="ticket"
                :priorities="priorities"
                :statuses="statuses"
                :loading="loading"
                :show-edit-button="showEditButton"
                :show-open-in-tickets-button="showOpenInTicketsButton"
                :visible="modelValue"
                variant="modal"
                @close="emit('update:modelValue', false)"
                @edit="emit('edit', $event)"
            />
        </DialogContent>
    </Dialog>
</template>
