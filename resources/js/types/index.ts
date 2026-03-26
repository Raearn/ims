import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    name?: string;
    badge?: number | null;
}

export interface SharedData {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    openTicketsCount: number | null;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    role: 'admin' | 'supervisor' | 'technical';
}

export type BreadcrumbItemType = BreadcrumbItem;
