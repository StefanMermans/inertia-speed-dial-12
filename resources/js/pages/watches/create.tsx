import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Loader2, Search, Tv } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Add Watch', href: route('watches.create') },
];

interface SearchResult {
    id: number;
    name: string;
    first_air_date: string | null;
    overview: string | null;
    poster_path: string | null;
}

interface Episode {
    id: number;
    name: string;
    episode_number: number;
    season_number: number;
    air_date: string | null;
}

interface Season {
    id: number;
    name: string;
    season_number: number;
    episodes: Episode[];
}

interface TvDetails {
    id: number;
    name: string;
    first_air_date: string | null;
    overview: string | null;
    poster_path: string | null;
    number_of_seasons: number;
    number_of_episodes: number;
    external_ids: {
        imdb_id: string | null;
        tvdb_id: number | null;
    };
}

interface ShowData {
    details: TvDetails;
    seasons: Season[];
}

export default function Create() {
    const { flash } = usePage<SharedData>().props;
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [showData, setShowData] = useState<ShowData | null>(null);
    const [loadingShow, setLoadingShow] = useState(false);
    const [showError, setShowError] = useState<string | null>(null);
    const [selectedSeasons, setSelectedSeasons] = useState<Set<number>>(new Set());
    const [submitting, setSubmitting] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);
    const abortRef = useRef<AbortController | null>(null);
    const showAbortRef = useRef<AbortController | null>(null);

    const search = useCallback((q: string) => {
        if (q.length < 2) {
            setResults([]);
            setSearchError(null);
            return;
        }

        abortRef.current?.abort();
        abortRef.current = new AbortController();

        setSearching(true);
        setSearchError(null);

        fetch(route('watches.search-tv') + `?query=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: abortRef.current.signal,
        })
            .then((res) => {
                if (!res.ok) throw new Error(`Search failed: ${res.status}`);
                return res.json();
            })
            .then((data) => {
                setResults(data.results ?? []);
                setSearching(false);
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                setResults([]);
                setSearchError('Failed to search. Please try again.');
                setSearching(false);
            });
    }, []);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);

        debounceRef.current = setTimeout(() => search(query), 300);

        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            abortRef.current?.abort();
        };
    }, [query, search]);

    function selectShow(id: number) {
        showAbortRef.current?.abort();
        showAbortRef.current = new AbortController();

        setLoadingShow(true);
        setShowError(null);

        fetch(route('watches.show-tv', { tmdbId: id }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: showAbortRef.current.signal,
        })
            .then((res) => {
                if (!res.ok) throw new Error(`Failed to load show: ${res.status}`);
                return res.json();
            })
            .then((data: ShowData) => {
                setShowData(data);
                setSelectedSeasons(new Set(data.seasons.map((s) => s.season_number)));
                setLoadingShow(false);
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                setShowError('Failed to load show details. Please try again.');
                setLoadingShow(false);
            });
    }

    function toggleSeason(seasonNumber: number) {
        setSelectedSeasons((prev) => {
            const next = new Set(prev);
            if (next.has(seasonNumber)) {
                next.delete(seasonNumber);
            } else {
                next.add(seasonNumber);
            }
            return next;
        });
    }

    function markAsWatched() {
        if (!showData) return;

        const episodes = showData.seasons
            .filter((s) => selectedSeasons.has(s.season_number))
            .flatMap((s) =>
                s.episodes.map((ep) => ({
                    tmdb_id: ep.id,
                    title: ep.name,
                    season_number: ep.season_number,
                    episode_number: ep.episode_number,
                })),
            );

        if (episodes.length === 0) return;

        const year = showData.details.first_air_date ? parseInt(showData.details.first_air_date.substring(0, 4)) : null;

        setSubmitting(true);

        router.post(
            route('watches.mark-series'),
            {
                tmdb_id: showData.details.id,
                title: showData.details.name,
                year,
                poster_path: showData.details.poster_path,
                imdb_id: showData.details.external_ids.imdb_id,
                tvdb_id: showData.details.external_ids.tvdb_id,
                episodes,
            },
            {
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setShowData(null);
                    setQuery('');
                    setResults([]);
                },
                onError: () => {
                    setShowError('Failed to mark series as watched. Please try again.');
                },
            },
        );
    }

    function goBack() {
        setShowData(null);
        setShowError(null);
    }

    const selectedEpisodeCount = showData
        ? showData.seasons.filter((s) => selectedSeasons.has(s.season_number)).reduce((sum, s) => sum + s.episodes.length, 0)
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Watch" />

            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="mb-6 text-2xl font-bold">Mark TV Series as Watched</h1>

                {flash?.success && (
                    <div
                        role="alert"
                        className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
                    >
                        {flash.success}
                    </div>
                )}

                {!showData ? (
                    <div>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                type="text"
                                placeholder="Search for a TV show..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                className="pl-10"
                                autoFocus
                                aria-label="Search for a TV show"
                            />
                        </div>

                        {searchError && (
                            <p role="alert" className="text-destructive mt-4 text-center text-sm">
                                {searchError}
                            </p>
                        )}

                        {searching && (
                            <div className="mt-4 space-y-3" aria-busy="true" aria-label="Loading search results">
                                {[1, 2, 3].map((i) => (
                                    <div key={i} className="flex gap-3">
                                        <Skeleton className="h-24 w-16 shrink-0 rounded" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-4 w-1/2" />
                                            <Skeleton className="h-3 w-1/4" />
                                            <Skeleton className="h-3 w-3/4" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {!searching && results.length > 0 && (
                            <div className="mt-4 space-y-2" role="listbox" aria-label="Search results">
                                {results.map((result) => (
                                    <button
                                        key={result.id}
                                        role="option"
                                        aria-selected={false}
                                        onClick={() => selectShow(result.id)}
                                        disabled={loadingShow}
                                        className="hover:bg-accent flex w-full gap-3 rounded-lg p-3 text-left transition-colors"
                                    >
                                        {result.poster_path ? (
                                            <img
                                                src={`https://image.tmdb.org/t/p/w92${result.poster_path}`}
                                                alt={result.name}
                                                className="h-24 w-16 shrink-0 rounded object-cover"
                                            />
                                        ) : (
                                            <div className="bg-muted flex h-24 w-16 shrink-0 items-center justify-center rounded">
                                                <Tv className="text-muted-foreground size-6" />
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{result.name}</p>
                                            {result.first_air_date && (
                                                <p className="text-muted-foreground text-sm">{result.first_air_date.substring(0, 4)}</p>
                                            )}
                                            {result.overview && <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{result.overview}</p>}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}

                        {!searching && query.length >= 2 && results.length === 0 && !searchError && (
                            <p className="text-muted-foreground mt-4 text-center text-sm">No results found.</p>
                        )}

                        {loadingShow && (
                            <div className="mt-4 flex items-center justify-center">
                                <Loader2 className="text-muted-foreground size-6 animate-spin" />
                                <span className="text-muted-foreground ml-2 text-sm">Loading show details...</span>
                            </div>
                        )}

                        {showError && !loadingShow && (
                            <p role="alert" className="text-destructive mt-4 text-center text-sm">
                                {showError}
                            </p>
                        )}
                    </div>
                ) : (
                    <div>
                        <Button variant="ghost" size="sm" onClick={goBack} className="mb-4">
                            <ArrowLeft className="mr-1 size-4" />
                            Back to search
                        </Button>

                        <div className="mb-6 flex gap-4">
                            {showData.details.poster_path ? (
                                <img
                                    src={`https://image.tmdb.org/t/p/w185${showData.details.poster_path}`}
                                    alt={showData.details.name}
                                    className="h-48 w-32 shrink-0 rounded-lg object-cover"
                                />
                            ) : (
                                <div className="bg-muted flex h-48 w-32 shrink-0 items-center justify-center rounded-lg">
                                    <Tv className="text-muted-foreground size-8" />
                                </div>
                            )}
                            <div>
                                <h2 className="text-xl font-bold">{showData.details.name}</h2>
                                {showData.details.first_air_date && (
                                    <p className="text-muted-foreground text-sm">{showData.details.first_air_date.substring(0, 4)}</p>
                                )}
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {showData.details.number_of_seasons} season{showData.details.number_of_seasons !== 1 ? 's' : ''} &middot;{' '}
                                    {showData.details.number_of_episodes} episodes
                                </p>
                                {showData.details.overview && <p className="mt-2 text-sm">{showData.details.overview}</p>}
                            </div>
                        </div>

                        {showError && (
                            <p role="alert" className="text-destructive mb-4 text-center text-sm">
                                {showError}
                            </p>
                        )}

                        <div className="mb-4">
                            <h3 className="mb-3 text-lg font-semibold">Select Seasons</h3>
                            <div className="space-y-2" role="group" aria-label="Season selection">
                                {showData.seasons.map((season) => (
                                    <label
                                        key={season.season_number}
                                        className="hover:bg-accent flex cursor-pointer items-center gap-3 rounded-lg p-3 transition-colors"
                                    >
                                        <Checkbox
                                            checked={selectedSeasons.has(season.season_number)}
                                            onCheckedChange={() => toggleSeason(season.season_number)}
                                        />
                                        <div className="flex-1">
                                            <span className="font-medium">{season.name}</span>
                                            <span className="text-muted-foreground ml-2 text-sm">
                                                {season.episodes.length} episode{season.episodes.length !== 1 ? 's' : ''}
                                            </span>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <Button onClick={markAsWatched} disabled={submitting || selectedEpisodeCount === 0} className="w-full">
                            {submitting ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                    Marking as watched...
                                </>
                            ) : (
                                `Mark ${selectedEpisodeCount} Episode${selectedEpisodeCount !== 1 ? 's' : ''} as Watched`
                            )}
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
