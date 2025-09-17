import { router } from "@inertiajs/react";
import { Dispatch, SetStateAction, useCallback, useEffect, useState } from "react";

type StateSetter<T> = Dispatch<SetStateAction<T>>;

// Overload for when no defaultValue is provided (undefined return type)
export function useQueryState(key: string, defaultValue?: undefined, pushState?: boolean): [string | undefined, StateSetter<string | undefined>];
export function useQueryState(key: string, defaultValue: boolean, pushState?: boolean): [boolean, StateSetter<boolean>];
// Overload for when defaultValue is provided (non-nullable return type)
export function useQueryState(key: string, defaultValue: string, pushState?: boolean): [string, StateSetter<string>];

export function useQueryState(key: string, defaultValue?: string | undefined | boolean, pushState: boolean = false) {
    const getQueryParam = useCallback(() => {
        const queryValue = new URLSearchParams(window.location.search).get(key);

        if (typeof defaultValue === 'boolean') {
            return queryValue === 'true'
        }

        return queryValue ?? defaultValue
    }, [defaultValue, key]);

    const buildNextState = useCallback((value: string | undefined | boolean) => {
        const currentParams = new URLSearchParams(window.location.search);
        console.log('building next state with value:', value);
        if (value === undefined || value === '' || value === false) {
            currentParams.delete(key);
        } else {
            currentParams.set(key, value.toString());
        }

        let nextState = window.location.pathname;

        if (currentParams.toString()) {
            nextState += `?${currentParams.toString()}`;
        }

        return nextState;
    }, [key]);

    const updateQueryParam = useCallback((value: string | undefined | boolean) => {
        if (pushState) {
            console.log('Pushing state:', value);
            const nextState = buildNextState(value);
            // window.history.pushState({}, '', nextState)
            router.visit(nextState)
        } else {
            window.history.replaceState({}, '', buildNextState(value))
        }
    }, [buildNextState, pushState]);

    const [state, setState] = useState<string | undefined | boolean>(() => {
        const initial = getQueryParam();
        window.history.replaceState({}, '', buildNextState(initial));

        return initial;
    });

    const updateState: StateSetter<string | undefined | boolean> = useCallback((value) => {
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