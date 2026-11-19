import { useSyncExternalStore } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'appearance';

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const isBrowser = (): boolean => typeof window !== 'undefined';

const systemDarkModeQuery = (): MediaQueryList | null => (isBrowser() ? window.matchMedia('(prefers-color-scheme: dark)') : null);

const getStoredAppearance = (): Appearance => {
    if (!isBrowser()) {
        return 'system';
    }

    return (localStorage.getItem(STORAGE_KEY) as Appearance | null) || 'system';
};

const applyTheme = (appearance: Appearance): void => {
    const isDark = appearance === 'dark' || (appearance === 'system' && (systemDarkModeQuery()?.matches ?? false));

    document.documentElement.classList.toggle('dark', isDark);
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

const subscribe = (listener: () => void): (() => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = (): Appearance => currentAppearance;

const getServerSnapshot = (): Appearance => 'system';

export function initializeTheme(): void {
    if (!isBrowser()) {
        return;
    }

    currentAppearance = getStoredAppearance();
    applyTheme(currentAppearance);

    systemDarkModeQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;
        localStorage.setItem(STORAGE_KEY, mode);
        applyTheme(mode);
        listeners.forEach((listener) => listener());
    };

    return { appearance, updateAppearance };
}
