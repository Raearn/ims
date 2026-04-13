export type IncidentTicketRow = {
    numericId: number;
    id: string;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    category: string;
    ticketCategoryId: number | null;
    handlerIds: number[];
    handlers: { id: number; name: string }[];
    reporter: string;
    reporterId: number;
    createdAt: string;
    createdAtFormatted: string;
    createdAtRaw: string;
    solution: string | null;
    resolvedInDuration: string | null;
    resolvedAtFormatted: string | null;
    attachmentUrl: string | null;
    commentsCount: number;
    tags: string[];
};

export type IncidentCategoryOption = { id: number; name: string; icon: string; parent_id: number | null };

export type IncidentPriorityOption = { id: number; name: string; icon: string; color: string };

export type IncidentStatusOption = {
    id: number;
    name: string;
    icon: string;
    color: string;
    handler_requirement: 'none' | 'optional' | 'required';
};
