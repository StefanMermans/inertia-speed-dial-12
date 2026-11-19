import { useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;

const mobileMediaQuery = typeof window === 'undefined' ? null : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

const subscribe = (onChange: () => void): (() => void) => {
    mobileMediaQuery?.addEventListener('change', onChange);

    return () => mobileMediaQuery?.removeEventListener('change', onChange);
};

const isBelowBreakpoint = (): boolean => mobileMediaQuery?.matches ?? false;

const getServerSnapshot = (): boolean => false;

export function useIsMobile(): boolean {
    return useSyncExternalStore(subscribe, isBelowBreakpoint, getServerSnapshot);
}
