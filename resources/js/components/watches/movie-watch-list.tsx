import { Skeleton } from '@/components/ui/skeleton';
import { type MovieWatch } from '@/types';

export function MovieWatchListSkeleton() {
    return (
        <div className="space-y-2" aria-busy="true" aria-label="Loading watched movies">
            {[1, 2, 3, 4].map((row) => (
                <div key={row} className="flex items-center justify-between rounded-lg border p-4">
                    <Skeleton className="h-4 w-48 animate-pulse" />
                    <Skeleton className="h-3 w-24 animate-pulse" />
                </div>
            ))}
        </div>
    );
}

export function MovieWatchList({ movies }: { movies: MovieWatch[] }) {
    if (movies.length === 0) {
        return <p className="text-muted-foreground text-sm">No movies watched yet.</p>;
    }

    return (
        <div className="space-y-2">
            {movies.map((movie) => (
                <div key={movie.id} className="flex items-center justify-between gap-4 rounded-lg border p-4">
                    <p className="truncate font-medium">
                        {movie.title}
                        {movie.year && <span className="text-muted-foreground ml-2 font-normal">{movie.year}</span>}
                    </p>
                    <time dateTime={movie.watched_at} className="text-muted-foreground shrink-0 text-sm">
                        {new Date(movie.watched_at).toLocaleDateString()}
                    </time>
                </div>
            ))}
        </div>
    );
}
