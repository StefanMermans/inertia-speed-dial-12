import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface Flash {
    success?: string;
    error?: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: Flash;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export type WatchProgressStatus = 'in_progress' | 'completed' | 'stale' | 'unmatched';

export interface SeriesProgress {
    id: number;
    title: string;
    year: number | null;
    poster_path: string | null;
    watched_episodes: number;
    total_episodes: number | null;
    progress: number | null;
    status: WatchProgressStatus;
    needs_review: boolean;
    tmdb_status: string | null;
    last_season_number: number | null;
    last_episode_number: number | null;
    last_watched_at: string;
}

export interface MovieWatch {
    id: number;
    title: string;
    year: number | null;
    tmdb_id: number | null;
    watched_at: string;
}
