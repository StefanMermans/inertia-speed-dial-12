import { Dispatch, SetStateAction, useCallback, useEffect, useState } from "react";

type StateSetter<T> = Dispatch<SetStateAction<T>>;

// Overload for when no defaultValue is provided (undefined return type)
export function useQueryState(key: string, defaultValue?: undefined, pushState?: boolean): [string | undefined, StateSetter<string | undefined>];
// Overload for when defaultValue is provided (non-nullable return type)
export function useQueryState(key: string, defaultValue: string, pushState?: boolean): [string, StateSetter<string>];

export function useQueryState(key: string, defaultValue?: string | undefined, pushState: boolean = false) {
    const getQueryParam = useCallback(() => {
        return new URLSearchParams(window.location.search).get(key) ?? defaultValue
    }, [defaultValue, key]);

    const buildNextState = useCallback((value: string | undefined) => {
        const currentParams = new URLSearchParams(window.location.search);
        if (value === undefined || value === '') {
            currentParams.delete(key);
        } else {
            currentParams.set(key, value);
        }

        let nextState = window.location.pathname;

        if (currentParams.toString()) {
            nextState += `?${currentParams.toString()}`;
        }

        return [null, '', nextState] as const
    }, [key]);

    const updateQueryParam = useCallback((value: string | undefined) => {
        if (pushState) {
            window.history.pushState(...buildNextState(value))
        } else {
            window.history.replaceState(...buildNextState(value))
        }
    }, [buildNextState, pushState]);

    const [state, setState] = useState<string | undefined>(() => {
        const initial = getQueryParam();
        window.history.replaceState(...buildNextState(initial));

        return initial;
    });

    const updateState: StateSetter<string | undefined> = useCallback((value) => {
        setState(prevState => {
            const newValue = typeof value === 'function' ? value(prevState) : value;
            updateQueryParam(newValue);

            return newValue;
        });
    }, [updateQueryParam]);

    useEffect(() => {
        const handleStateChange = () => setState(getQueryParam())

        window.addEventListener('popstate', handleStateChange);
        window.addEventListener('pushstate', handleStateChange);
        window.addEventListener('replacestate', handleStateChange);    

        return () => {
            window.removeEventListener('popstate', handleStateChange);
            window.removeEventListener('pushstate', handleStateChange);
            window.removeEventListener('replacestate', handleStateChange);
        }
    }, [defaultValue, getQueryParam, key]);

    return [state, updateState] as const;
}