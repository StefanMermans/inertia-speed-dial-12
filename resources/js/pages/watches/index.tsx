import { FixMatchDialog } from '@/components/watches/fix-match-dialog';
import { MovieWatchList, MovieWatchListSkeleton } from '@/components/watches/movie-watch-list';
import { WatchSection } from '@/components/watches/watch-section';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type MovieWatch, type SeriesProgress, type SharedData } from '@/types';
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { Tv } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Watch Progress', href: route('watches.index') },
];

interface WatchProgressProps {
    continueWatching: SeriesProgress[];
    needsMatch: SeriesProgress[];
    completed: SeriesProgress[];
    stale: SeriesProgress[];
    movies?: MovieWatch[];
}

export default function WatchProgress({ continueWatching, needsMatch, completed, stale }: WatchProgressProps) {
    const { flash } = usePage<SharedData>().props;
    const [fixing, setFixing] = useState<SeriesProgress | null>(null);

    const hasShows = continueWatching.length + needsMatch.length + completed.length + stale.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Watch Progress" />

            <div className="mx-auto w-full max-w-5xl p-4">
                {flash?.success && (
                    <div
                        role="alert"
                        className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"
                    >
                        {flash.success}
                    </div>
                )}

                {hasShows ? (
                    <>
                        <WatchSection title="Continue watching" series={continueWatching} onFixMatch={setFixing} />
                        <WatchSection
                            title="Needs a TMDB match"
                            description="Plex could not be matched automatically, so there is no episode total to measure against."
                            series={needsMatch}
                            onFixMatch={setFixing}
                        />
                        <WatchSection title="Completed" series={completed} onFixMatch={setFixing} />
                        <WatchSection
                            title="Gathering dust"
                            description="Started, but nothing watched in the last 90 days."
                            series={stale}
                            onFixMatch={setFixing}
                        />
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Tv className="size-8 text-muted-foreground" />
                        <h2 className="mt-3 font-medium">Nothing watched yet</h2>
                        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                            Shows appear here as Plex scrobbles them, or you can add what you have already seen by hand.
                        </p>
                        <Link
                            href={route('watches.create')}
                            className="mt-4 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Add a show
                        </Link>
                    </div>
                )}

                <section className="mt-10">
                    <h2 className="mb-3 text-lg font-semibold">Movies</h2>
                    <Deferred data="movies" fallback={<MovieWatchListSkeleton />}>
                        <MoviesSection />
                    </Deferred>
                </section>
            </div>

            <FixMatchDialog series={fixing} onOpenChange={(open) => !open && setFixing(null)} />
        </AppLayout>
    );
}

function MoviesSection() {
    const { movies } = usePage<{ movies: MovieWatch[] }>().props;

    return <MovieWatchList movies={movies} />;
}
