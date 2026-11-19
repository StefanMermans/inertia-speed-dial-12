import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TmdbPoster } from './tmdb-poster';

const DEBOUNCE_MS = 300;
const MIN_QUERY_LENGTH = 2;

export interface TmdbSearchResult {
    id: number;
    name: string;
    first_air_date: string | null;
    overview: string | null;
    poster_path: string | null;
}

interface TmdbSeriesSearchProps {
    initialQuery?: string;
    onSelect: (result: TmdbSearchResult) => void;
    disabled?: boolean;
}

export function TmdbSeriesSearch({ initialQuery = '', onSelect, disabled = false }: TmdbSeriesSearchProps) {
    const [query, setQuery] = useState(initialQuery);
    const [results, setResults] = useState<TmdbSearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const changeQuery = (nextQuery: string) => {
        setQuery(nextQuery);

        if (nextQuery.trim().length < MIN_QUERY_LENGTH) {
            abortRef.current?.abort();
            setResults([]);
            setSearching(false);
        }
    };

    useEffect(() => {
        if (query.trim().length < MIN_QUERY_LENGTH) {
            return;
        }

        const timeout = window.setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            setSearching(true);
            setError(null);

            fetch(route('watches.search-tv', { query: query.trim() }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Search failed.');
                    }

                    return response.json();
                })
                .then((data) => setResults(data.results ?? []))
                .catch((caught: Error) => {
                    if (caught.name === 'AbortError') {
                        return;
                    }

                    setError('Could not search TMDB. Try again.');
                    setResults([]);
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setSearching(false);
                    }
                });
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timeout);
    }, [query]);

    useEffect(() => () => abortRef.current?.abort(), []);

    return (
        <div>
            <div className="relative">
                <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => changeQuery(event.target.value)}
                    placeholder="Search TMDB for the right show…"
                    className="pl-9"
                    aria-label="Search TMDB"
                    autoFocus
                />
            </div>

            {error && (
                <p role="alert" className="text-destructive mt-3 text-sm">
                    {error}
                </p>
            )}

            {searching && (
                <div className="mt-3 space-y-3" aria-busy="true" aria-label="Loading search results">
                    {[1, 2, 3].map((row) => (
                        <div key={row} className="flex gap-3">
                            <Skeleton className="h-20 w-14 shrink-0 rounded" />
                            <div className="flex-1 space-y-2">
                                <Skeleton className="h-4 w-1/2" />
                                <Skeleton className="h-3 w-1/4" />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {!searching && results.length > 0 && (
                <div className="mt-3 max-h-80 space-y-1 overflow-y-auto" role="listbox" aria-label="Search results">
                    {results.map((result) => (
                        <button
                            key={result.id}
                            type="button"
                            role="option"
                            aria-selected={false}
                            disabled={disabled}
                            onClick={() => onSelect(result)}
                            className="hover:bg-accent flex w-full gap-3 rounded-lg p-2 text-left transition-colors disabled:opacity-50"
                        >
                            <TmdbPoster path={result.poster_path} alt={result.name} size="w92" className="h-20 w-14 shrink-0" />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{result.name}</p>
                                {result.first_air_date && <p className="text-muted-foreground text-sm">{result.first_air_date.substring(0, 4)}</p>}
                                {result.overview && <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{result.overview}</p>}
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {!searching && query.trim().length >= MIN_QUERY_LENGTH && results.length === 0 && !error && (
                <p className="text-muted-foreground mt-3 text-sm">No results found.</p>
            )}
        </div>
    );
}
