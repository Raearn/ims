export interface TicketDetail {
    numericId: number;
    id: string;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    category: string;
    handlerIds: number[];
    handlers: { id: number; name: string }[];
    reporter: string;
    reporterId: number;
    attachmentUrl: string | null;
    createdAt: string;
    createdAtFormatted: string;
    createdAtRaw: string;
    incidentOccurredAt: string | null;
    incidentOccurredAtFormatted: string | null;
    solution: string | null;
    resolvedInDuration: string | null;
    resolvedAtFormatted: string | null;
    commentsCount: number;
    tags: string[];
}
